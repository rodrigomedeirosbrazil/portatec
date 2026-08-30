<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendDeviceCommandRequest extends FormRequest
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
            'device_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:toggle,push_button'],
            'pin' => ['required', 'string'],
        ];
    }
}
