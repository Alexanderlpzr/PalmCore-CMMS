<?php

namespace App\Domain\Inventory\Enums;

enum InventoryCountStatus: string
{
    case Open = 'open';
    case Applied = 'applied';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Contando',
            self::Applied => 'Aplicado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Applied => 'success',
            self::Cancelled => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
