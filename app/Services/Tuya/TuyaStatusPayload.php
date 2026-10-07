<?php

declare(strict_types=1);

namespace App\Services\Tuya;

/**
 * Regra única de como o status Tuya é gravado em `devices.tuya_status_payload`.
 *
 * Eventos MQTT trazem só os DPs que mudaram (AGENTS.md §11.5), então o status é mesclado por
 * `code` em vez de substituído. DPs sem `code` e os sensíveis nunca são gravados.
 */
final class TuyaStatusPayload
{
    /**
     * `ble_unlock_check` carrega em claro o código que abre a fechadura por Bluetooth
     * (AGENTS.md §11.6.1); `check_code_set` é o DP que grava esse código.
     */
    public const SENSITIVE_CODES = ['ble_unlock_check', 'check_code_set'];

    /**
     * @param  array<int|string, mixed>  $current
     * @param  array<int|string, mixed>  $incoming
     * @return list<array{code: string, value: mixed, t: int|null}>
     */
    public static function merge(array $current, array $incoming): array
    {
        $byCode = [];

        foreach ($current as $entry) {
            $code = self::storableCode($entry);

            if ($code !== null) {
                $byCode[$code] = [
                    'code' => $code,
                    'value' => $entry['value'] ?? null,
                    't' => self::timestamp($entry['t'] ?? null),
                ];
            }
        }

        foreach ($incoming as $entry) {
            $code = self::storableCode($entry);

            if ($code === null) {
                continue;
            }

            $value = $entry['value'] ?? null;
            $t = self::timestamp($entry['t'] ?? null);
            $previous = $byCode[$code] ?? null;

            // O snapshot do /devices/detail não traz `t`. Valor igual não é mudança; valor
            // diferente foi percebido agora.
            if ($t === null && $previous !== null) {
                $t = $previous['value'] === $value ? $previous['t'] : (int) now()->getTimestampMs();
            }

            $byCode[$code] = ['code' => $code, 'value' => $value, 't' => $t];
        }

        return array_values($byCode);
    }

    private static function storableCode(mixed $entry): ?string
    {
        if (! is_array($entry)) {
            return null;
        }

        $code = $entry['code'] ?? null;

        if (! is_string($code) || $code === '' || in_array($code, self::SENSITIVE_CODES, true)) {
            return null;
        }

        return $code;
    }

    private static function timestamp(mixed $t): ?int
    {
        return is_numeric($t) ? (int) $t : null;
    }
}
