<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\AccessCodePinAvailableRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
            'pin' => [
                'nullable',
                'string',
                'max:6',
                new AccessCodePinAvailableRule(
                    (int) $this->input('placeId'),
                    Carbon::parse((string) $this->input('start')),
                    $this->filled('end') ? Carbon::parse((string) $this->input('end')) : null,
                ),
            ],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }
}
