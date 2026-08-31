<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTuyaConnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Security: only the selected device IDs travel from the client. Every
     * other device attribute (name, category, productId, icon, status...)
     * is read back from the server-side session — never trusted from the
     * request payload — see `TuyaConnectController::store()`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['string'],
        ];
    }
}
