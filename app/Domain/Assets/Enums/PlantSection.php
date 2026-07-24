<?php

namespace App\Domain\Assets\Enums;

/**
 * Sección de la planta donde ocurre el paro, tal como El Pajuil la escribe en su
 * planilla «REGISTROS DE PAROS». Es la etapa del proceso, no el equipo: un paro de
 * planta (sin equipo) igual tiene sección (ej. «Planta general», «Generación
 * eléctrica»).
 */
enum PlantSection: string
{
    case GeneracionElectrica = 'generacion_electrica';
    case Extraccion = 'extraccion';
    case Palmisteria = 'palmisteria';
    case Esterilizacion = 'esterilizacion';
    case Clarificacion = 'clarificacion';
    case PlantaGeneral = 'planta_general';

    public function label(): string
    {
        return match ($this) {
            self::GeneracionElectrica => 'Generación eléctrica',
            self::Extraccion => 'Extracción',
            self::Palmisteria => 'Palmistería',
            self::Esterilizacion => 'Esterilización',
            self::Clarificacion => 'Clarificación',
            self::PlantaGeneral => 'Planta general',
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
