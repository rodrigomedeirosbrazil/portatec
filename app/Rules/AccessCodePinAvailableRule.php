<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\AccessCode\AccessCodeConflictChecker;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Spec §7. Aplica-se só quando o PIN foi digitado à mão — o gerado já sai sem
 * conflito por construção.
 */
class AccessCodePinAvailableRule implements ValidationRule
{
    public function __construct(
        private int $placeId,
        private CarbonInterface $start,
        private ?CarbonInterface $end,
        private ?int $ignoreAccessCodeId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $conflicts = app(AccessCodeConflictChecker::class)->conflicts(
            $this->placeId,
            $value,
            $this->start,
            $this->end,
            $this->ignoreAccessCodeId,
        );

        if ($conflicts) {
            $fail(trans('app.access_code_pin_conflict'));
        }
    }
}
