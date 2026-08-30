<?php

declare(strict_types=1);

namespace App\Services\Tuya\DTOs;

class TuyaDeviceDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
        public readonly bool $online,
        public readonly ?string $productId = null,
        public readonly ?string $productName = null,
        public readonly ?string $icon = null,
        /** @var array<array{code: string, value: mixed}> */
        public readonly array $status = [],
    ) {}

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'ms' => 'Fechadura',
            'kg' => 'Interruptor',
            'cz' => 'Tomada',
            'pc' => 'Régua de energia',
            'mcs' => 'Sensor de abertura',
            'pir' => 'Sensor de presença',
            'clkg' => 'Interruptor de cortina',
            'mc' => 'Controlador de porta/janela',
            'tdkg' => 'Controlador de portão',
            default => $this->category,
        };
    }

    public static function isAccessCategory(string $category): bool
    {
        return in_array($category, ['ms', 'kg', 'cz', 'mcs', 'mc', 'tdkg', 'clkg', 'pc'], true);
    }
}
