<?php

declare(strict_types=1);

namespace App\Services\Tuya\DTOs;

use App\Models\Device;
use Carbon\CarbonImmutable;

/**
 * O que a tela mostra de uma fechadura Tuya, derivado de `devices.tuya_status_payload`.
 * Cada campo pode ser `null`: o modelo pode não expor o DP, ou ele ainda não foi reportado.
 */
final readonly class TuyaLockStatus
{
    /** Alarmes que pedem atenção. Tentativas erradas (`wrong_*`) e `key_in` seriam ruído. */
    public const RELEVANT_ALERTS = [
        'pry',
        'shock',
        'low_battery',
        'power_off',
        'too_hot',
        'unclosed_time',
        'tongue_bad',
        'tongue_not_out',
    ];

    private const ALERT_WINDOW_HOURS = 24;

    public function __construct(
        public ?bool $locked,
        public ?int $battery,
        public ?string $alert,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromDevice(Device $device): ?self
    {
        if (! $device->isTuyaLock()) {
            return null;
        }

        $entries = collect($device->tuya_status_payload ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && is_string($entry['code'] ?? null))
            ->keyBy('code');

        $motor = $entries->get('lock_motor_state');
        $motorValue = $motor['value'] ?? null;

        return new self(
            // O DP 47 é `true` quando o motor está ABERTO (observado no hardware).
            locked: is_bool($motorValue) ? ! $motorValue : null,
            battery: self::battery($entries->get('residual_electricity')['value'] ?? null),
            alert: self::recentAlert($entries->get('alarm_lock')),
            updatedAt: self::time($motor['t'] ?? null),
        );
    }

    /** @return array{locked: bool|null, battery: int|null, alert: string|null, updated_at: string|null} */
    public function toArray(): array
    {
        return [
            'locked' => $this->locked,
            'battery' => $this->battery,
            'alert' => $this->alert,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    private static function battery(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $level = (int) $value;

        return $level >= 0 && $level <= 100 ? $level : null;
    }

    /** @param array<string, mixed>|null $entry */
    private static function recentAlert(?array $entry): ?string
    {
        $value = $entry['value'] ?? null;
        $at = self::time($entry['t'] ?? null);

        if (! in_array($value, self::RELEVANT_ALERTS, true) || $at === null) {
            return null;
        }

        return $at->greaterThanOrEqualTo(now()->subHours(self::ALERT_WINDOW_HOURS)) ? $value : null;
    }

    private static function time(mixed $milliseconds): ?CarbonImmutable
    {
        return is_numeric($milliseconds)
            ? CarbonImmutable::createFromTimestampMs((int) $milliseconds)
            : null;
    }
}
