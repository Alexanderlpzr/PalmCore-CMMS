<?php

namespace App\Domain\Commercial\Enums;

/**
 * Lo que la extractora vende por cargue: el aceite crudo de palma (CPO) y el aceite de
 * palmiste (PKO). La torta de palmiste sale de la maquila y se lleva aparte.
 */
enum CommercialProduct: string
{
    case Cpo = 'cpo';
    case Pko = 'pko';

    public function label(): string
    {
        return match ($this) {
            self::Cpo => 'Aceite de palma (CPO)',
            self::Pko => 'Palmiste (PKO)',
        };
    }

    public function shortLabel(): string
    {
        return strtoupper($this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
