<?php

namespace App\Domain\Assets\Enums;

enum EquipmentPriority: string
{
    case P1 = 'p1';
    case P2 = 'p2';
    case P3 = 'p3';
    case P4 = 'p4';

    public function label(): string
    {
        return match ($this) {
            self::P1 => 'P1 — Inmediato',
            self::P2 => 'P2 — Urgente',
            self::P3 => 'P3 — Normal',
            self::P4 => 'P4 — Programado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::P1 => 'danger',
            self::P2 => 'warning',
            self::P3 => 'info',
            self::P4 => 'gray',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::P1 => 'Atención inmediata — impacto en producción',
            self::P2 => 'Atención en menos de 24 horas',
            self::P3 => 'Atención en la semana',
            self::P4 => 'Programar en el siguiente período',
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
