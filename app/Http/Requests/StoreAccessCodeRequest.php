<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessCodeRequest extends FormRequest
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
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'pin' => ['nullable', 'string', 'max:6'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }
}
