<?php

namespace App\Domain\Maintenance\Enums;

/**
 * «Proceso» de la planilla de OT: la etapa de la planta donde se hizo el trabajo.
 * Es una lista propia de OT, más amplia que la Sección de Paros (incluye PTAI,
 * Recepción y Generación de vapor).
 */
enum PlantProcess: string
{
    case Recepcion = 'recepcion';
    case Esterilizacion = 'esterilizacion';
    case Extraccion = 'extraccion';
    case Clarificacion = 'clarificacion';
    case Palmisteria = 'palmisteria';
    case GeneracionVapor = 'generacion_vapor';
    case GeneracionElectrica = 'generacion_electrica';
    case Ptai = 'ptai';
    case PlantaGeneral = 'planta_general';

    public function label(): string
    {
        return match ($this) {
            self::Recepcion => 'Recepción',
            self::Esterilizacion => 'Esterilización',
            self::Extraccion => 'Extracción',
            self::Clarificacion => 'Clarificación',
            self::Palmisteria => 'Palmistería',
            self::GeneracionVapor => 'Generación de vapor',
            self::GeneracionElectrica => 'Generación eléctrica',
            self::Ptai => 'PTAI',
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
