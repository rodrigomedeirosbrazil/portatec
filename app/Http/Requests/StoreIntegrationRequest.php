<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Platform;
use App\Rules\AirbnbIcalUrlRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Porte 1:1 das regras de `App\Livewire\Integrations\Create::rules()`. A
 * verificação de vínculo do usuário ao place (hoje em `Create::save()`)
 * permanece no controller, junto da autorização.
 */
class StoreIntegrationRequest extends FormRequest
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
        $rules = [
            'platformId' => ['required', 'integer', 'exists:platforms,id'],
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'externalId' => ['required', 'string', 'max:2000', 'url'],
        ];

        $platform = Platform::find($this->input('platformId'));

        if ($platform?->slug === 'airbnb') {
            $rules['externalId'][] = new AirbnbIcalUrlRule;
        }

        return $rules;
    }
}
