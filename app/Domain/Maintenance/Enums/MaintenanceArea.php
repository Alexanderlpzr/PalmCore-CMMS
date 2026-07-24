<?php

namespace App\Domain\Maintenance\Enums;

/**
 * «Área de Mtto» de la planilla de OT: la disciplina que hizo el trabajo.
 */
enum MaintenanceArea: string
{
    case Mecanico = 'mecanico';
    case Electrico = 'electrico';
    case Instrumentacion = 'instrumentacion';

    public function label(): string
    {
        return match ($this) {
            self::Mecanico => 'Mecánico',
            self::Electrico => 'Eléctrico',
            self::Instrumentacion => 'Instrumentación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mecanico => 'warning',
            self::Electrico => 'info',
            self::Instrumentacion => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => [...$options, $case->value => $case->label()],
            [],
        );
    }
}
