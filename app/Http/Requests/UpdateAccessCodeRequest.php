<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\AccessCodePinAvailableRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateAccessCodeRequest extends FormRequest
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
        /** @var \App\Models\AccessCode $accessCode */
        $accessCode = $this->route('accessCode');

        return [
            'pin' => [
                'required',
                'string',
                'max:6',
                new AccessCodePinAvailableRule(
                    (int) $accessCode->place_id,
                    Carbon::parse((string) $this->input('start')),
                    $this->filled('end') ? Carbon::parse((string) $this->input('end')) : null,
                    (int) $accessCode->id,
                ),
            ],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }
}
