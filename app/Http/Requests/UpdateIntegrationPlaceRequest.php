<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\AirbnbIcalUrlRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Porte 1:1 das regras de `App\Livewire\Integrations\Edit::updateExternalId()`.
 * A autorização dos models `{integration}` / `{place}` vindos da URL, e a
 * checagem de que o place pertence à integração, permanecem no controller
 * (`IntegrationPlaceController@update`).
 */
class UpdateIntegrationPlaceRequest extends FormRequest
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
            'externalId' => ['required', 'string', 'max:2000', 'url'],
        ];

        $integration = $this->route('integration');

        if ($integration?->platform?->slug === 'airbnb') {
            $rules['externalId'][] = new AirbnbIcalUrlRule;
        }

        return $rules;
    }
}
