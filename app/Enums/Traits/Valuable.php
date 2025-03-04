<?php

namespace App\Enums\Traits;

trait Valuable
{
    use Comparable;

    /**
     * @return array<int, mixed>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, mixed>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Retorna um array associativo de valores e nomes.
     * Este método pode ser sobrescrito nas classes que usam este trait
     * para fornecer traduções personalizadas.
     */
    public static function toArray(): array
    {
        return array_combine(self::values(), self::names());
    }

    /**
     * Retorna o label para o valor atual.
     * Este método pode ser sobrescrito nas classes que usam este trait
     * para fornecer traduções personalizadas.
     */
    public function label(): string
    {
        return $this->name;
    }
}
