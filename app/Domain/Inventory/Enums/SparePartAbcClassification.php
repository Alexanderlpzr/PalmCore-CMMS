<?php

namespace App\Domain\Inventory\Enums;

enum SparePartAbcClassification: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A — Alto valor / Alta rotación',
            self::B => 'B — Valor medio',
            self::C => 'C — Bajo valor / Baja rotación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::A => 'danger',
            self::B => 'warning',
            self::C => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
