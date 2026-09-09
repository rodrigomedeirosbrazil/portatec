<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevicePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Spec §6: e-mail exato. `exists` numa string completa não permite
     * enumerar — sem o endereço inteiro, nada é encontrado.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => trans('app.user_email_not_found'),
            'email.email' => trans('app.user_email_not_found'),
        ];
    }
}
