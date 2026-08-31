<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlaceCloneRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'additionalMembers' => ['array'],
            'additionalMembers.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'additionalMembers.*.role' => ['required', 'string', 'in:admin,host'],
            'additionalMembers.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
