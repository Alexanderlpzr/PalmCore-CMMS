<?php

namespace App\Domain\Inventory\Enums;

/**
 * Qué entra en un conteo. «Lo que toca» es el conteo cíclico: cada repuesto según su
 * clase ABC —A cada mes, B cada trimestre, C cada semestre— o el que nunca se ha contado.
 */
enum InventoryCountScope: string
{
    case Due = 'due';
    case All = 'all';
    case Location = 'location';
    case ClassA = 'class_a';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Lo que toca contar (cíclico por ABC)',
            self::All => 'Todo el almacén',
            self::Location => 'Una ubicación',
            self::ClassA => 'Solo los clase A',
        };
    }

    /** Cada cuántos días se cuenta un repuesto según su clase ABC. Sin clase cuenta como C. */
    public static function cycleDays(?SparePartAbcClassification $class): int
    {
        return match ($class) {
            SparePartAbcClassification::A => 30,
            SparePartAbcClassification::B => 90,
            default => 180,
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
