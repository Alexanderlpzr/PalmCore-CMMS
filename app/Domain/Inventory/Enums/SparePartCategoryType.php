<?php

namespace App\Domain\Inventory\Enums;

enum SparePartCategoryType: string
{
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';
    case Instrumentation = 'instrumentation';
    case Lubrication = 'lubrication';
    case Consumable = 'consumable';
    case Safety = 'safety';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mechanical => 'Mecánico',
            self::Electrical => 'Eléctrico',
            self::Instrumentation => 'Instrumentación',
            self::Lubrication => 'Lubricación',
            self::Consumable => 'Consumible',
            self::Safety => 'Seguridad',
            self::Other => 'Otro',
        };
    }

    /** El prefijo del código que asigna el sistema: MEC-0001, ELE-0001… */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Mechanical => 'MEC',
            self::Electrical => 'ELE',
            self::Instrumentation => 'INS',
            self::Lubrication => 'LUB',
            self::Consumable => 'CON',
            self::Safety => 'SEG',
            self::Other => 'OTR',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mechanical => 'blue',
            self::Electrical => 'yellow',
            self::Instrumentation => 'purple',
            self::Lubrication => 'green',
            self::Consumable => 'gray',
            self::Safety => 'red',
            self::Other => 'gray',
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
