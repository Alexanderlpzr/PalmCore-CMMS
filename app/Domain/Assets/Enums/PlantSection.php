<?php

namespace App\Domain\Assets\Enums;

/**
 * Sección de la planta donde ocurre el paro, tal como El Pajuil la escribe en su
 * planilla «REGISTROS DE PAROS». Es la etapa del proceso, no el equipo: un paro de
 * planta (sin equipo) igual tiene sección (ej. «Planta general», «Generación
 * eléctrica»).
 *
 * Los casos van en orden de proceso —de la tolva de recepción al despacho— porque
 * `options()` respeta el orden de declaración y el desplegable se lee como se
 * recorre la planta, no como un alfabeto.
 */
enum PlantSection: string
{
    case RecepcionFruta = 'recepcion_fruta';
    case Esterilizacion = 'esterilizacion';
    case Desfrutado = 'desfrutado';
    case Desfibrado = 'desfibrado';
    case Raquis = 'raquis';
    case Extraccion = 'extraccion';
    case Clarificacion = 'clarificacion';
    case Palmisteria = 'palmisteria';
    case GeneracionDeVapor = 'generacion_de_vapor';
    case GeneracionElectrica = 'generacion_electrica';
    case PlantaGeneral = 'planta_general';

    public function label(): string
    {
        return match ($this) {
            self::RecepcionFruta => 'Recepción de fruta',
            self::Esterilizacion => 'Esterilización',
            self::Desfrutado => 'Desfrutado',
            self::Desfibrado => 'Desfibrado',
            self::Raquis => 'Raquis',
            self::Extraccion => 'Extracción',
            self::Clarificacion => 'Clarificación',
            self::Palmisteria => 'Palmistería',
            self::GeneracionDeVapor => 'Generación de vapor',
            self::GeneracionElectrica => 'Generación eléctrica',
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
