<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra compartilhada de validação da URL iCal do Airbnb.
 *
 * Porta 1:1 a validação hoje duplicada em `App\Livewire\Integrations\Create::save()`
 * e `App\Livewire\Integrations\Edit::updateExternalId()`. Deve ser aplicada apenas
 * quando `platform.slug === 'airbnb'` — a decisão de quando aplicar continua do
 * lado de quem monta as regras de validação (Create/Edit e seus futuros
 * FormRequests), não desta classe.
 */
class AirbnbIcalUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (str_contains($value, '/hosting/reservations/details/')) {
            $fail(trans('app.airbnb_reservation_details_url_error'));

            return;
        }

        $path = parse_url($value, PHP_URL_PATH) ?? '';

        if (! str_ends_with($path, '.ics')) {
            $fail(trans('app.airbnb_ics_url_error'));
        }
    }
}
