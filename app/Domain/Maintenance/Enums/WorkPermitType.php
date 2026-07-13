<?php

namespace App\Domain\Maintenance\Enums;

/**
 * Los permisos de trabajo de alto riesgo de una planta de proceso.
 *
 * No son una categoría documental: cada uno existe porque alguien se murió sin él.
 * En una extractora conviven vapor a presión, espacios confinados (digestores,
 * esterilizadores, tanques de lodos) y trabajo en caliente junto a fibra y cuesco.
 */
enum WorkPermitType: string
{
    case HotWork = 'hot_work';
    case ConfinedSpace = 'confined_space';
    case LockoutTagout = 'lockout_tagout';
    case WorkAtHeight = 'work_at_height';
    case ElectricalWork = 'electrical_work';

    public function label(): string
    {
        return match ($this) {
            self::HotWork => 'Trabajo en caliente',
            self::ConfinedSpace => 'Espacio confinado',
            self::LockoutTagout => 'Bloqueo y etiquetado (LOTO)',
            self::WorkAtHeight => 'Trabajo en altura',
            self::ElectricalWork => 'Trabajo eléctrico',
        };
    }

    /**
     * Un permiso de espacio confinado sin puntos de aislamiento no es un permiso:
     * el equipo sigue energizado mientras alguien está adentro.
     */
    public function requiresIsolation(): bool
    {
        return match ($this) {
            self::ConfinedSpace, self::LockoutTagout, self::ElectricalWork => true,
            self::HotWork, self::WorkAtHeight => false,
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
