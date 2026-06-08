<?php

namespace App\Domain\Assets\Enums;

enum EquipmentStatus: string
{
    case Active           = 'active';
    case Inactive         = 'inactive';
    case UnderMaintenance = 'under_maintenance';
    case Retired          = 'retired';
    case Disposed         = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Active           => 'Activo',
            self::Inactive         => 'Inactivo',
            self::UnderMaintenance => 'En Mantenimiento',
            self::Retired          => 'Retirado',
            self::Disposed         => 'Dado de Baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active           => 'success',
            self::Inactive         => 'gray',
            self::UnderMaintenance => 'warning',
            self::Retired          => 'danger',
            self::Disposed         => 'gray',
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
