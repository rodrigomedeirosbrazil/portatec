<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePlaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'role' => ['required', 'string', 'in:admin,host'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $place = $this->route('place');
            $userId = User::query()->where('email', $this->input('email'))->value('id');

            if (! $place instanceof Place || $userId === null) {
                return;
            }

            $alreadyMember = PlaceUser::query()
                ->where('place_id', $place->id)
                ->where('user_id', $userId)
                ->exists();

            if ($alreadyMember) {
                $validator->errors()->add('email', __('app.member_already_in_place'));
            }
        });
    }
}
