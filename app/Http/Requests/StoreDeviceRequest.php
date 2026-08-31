<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
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
            'placeIds' => ['required', 'array', 'min:1'],
            'placeIds.*' => ['required', 'integer', 'exists:places,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'in:portatec,tuya'],
            'external_device_id' => ['nullable', 'string', 'max:255'],
            'default_pin' => ['nullable', 'string', 'digits:6'],
        ];
    }
}
