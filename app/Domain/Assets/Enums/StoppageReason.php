<?php

namespace App\Domain\Assets\Enums;

/**
 * Tipo II — la causa concreta del paro, con la lista fija que maneja El Pajuil en su
 * planilla. Cada Tipo II pertenece a un solo Tipo I ({@see ReportedStoppageType}) y
 * lleva además su categoría física ({@see StoppageCategory}), que es la que alimenta
 * el MTBF honesto (mecánico/eléctrico = falla de mantenimiento; atascamiento o falta
 * de fruta = no lo es, aunque el Tipo I del cliente diga otra cosa).
 *
 * Al elegir el Tipo II en el registro se derivan solos el Tipo I y la categoría.
 */
enum StoppageReason: string
{
    case MantenimientoProgramado = 'mantenimiento_programado';
    case ArranqueDePlanta = 'arranque_de_planta';
    case ApagadoDePlanta = 'apagado_de_planta';
    case FallaMecanica = 'falla_mecanica';
    case FallaElectrica = 'falla_electrica';
    case FallaOperativa = 'falla_operativa';
    case Atascamiento = 'atascamiento';
    case FaltaFrutaEsterilizada = 'falta_fruta_esterilizada';
    case FaltaFrutaFresca = 'falta_fruta_fresca';
    case CorteEnergiaRed = 'corte_energia_red';
    case Capacitaciones = 'capacitaciones';

    public function label(): string
    {
        return match ($this) {
            self::MantenimientoProgramado => 'Mantenimiento programado',
            self::ArranqueDePlanta => 'Arranque de planta',
            self::ApagadoDePlanta => 'Apagado de planta',
            self::FallaMecanica => 'Falla mecánica',
            self::FallaElectrica => 'Falla eléctrica',
            self::FallaOperativa => 'Falla operativa',
            self::Atascamiento => 'Atascamiento',
            self::FaltaFrutaEsterilizada => 'Falta de fruta esterilizada',
            self::FaltaFrutaFresca => 'Falta de fruta fresca (RFF)',
            self::CorteEnergiaRed => 'Corte de energía de red',
            self::Capacitaciones => 'Capacitaciones',
        };
    }

    /**
     * El Tipo I al que pertenece este Tipo II — el que propone el formulario.
     *
     * Es una propuesta, no una regla: la planilla de El Pajuil clasifica el mismo
     * Tipo II bajo Tipos I distintos según quién asumió el paro (una «falla
     * mecánica» aparece 52 veces como Operativa y 27 como Mantenimiento). Por eso
     * `DowntimeService::normalize()` respeta el Tipo I que venga explícito y solo
     * cae aquí cuando nadie lo declaró.
     */
    public function reportedType(): ReportedStoppageType
    {
        return match ($this) {
            self::MantenimientoProgramado, self::ArranqueDePlanta,
            self::ApagadoDePlanta => ReportedStoppageType::Scheduled,
            self::FallaMecanica, self::FallaElectrica => ReportedStoppageType::Maintenance,
            self::FallaOperativa, self::Atascamiento,
            self::FaltaFrutaEsterilizada => ReportedStoppageType::Operational,
            self::FaltaFrutaFresca, self::CorteEnergiaRed,
            self::Capacitaciones => ReportedStoppageType::External,
        };
    }

    /**
     * La categoría física real — la que decide si es o no falla de mantenimiento.
     *
     * Solo «Mantenimiento programado» es `Planned`. Apagar la planta para vaciar
     * el preclarificador o parar por una capacitación se reportan como programados
     * o externos, pero no son intervenciones de mantenimiento: si entraran como
     * `Planned` sumarían a las horas de aseo (HASEO) y subirían la eficiencia
     * descontando tiempo que la planta sí tenía disponible para prensar.
     */
    public function category(): StoppageCategory
    {
        return match ($this) {
            self::MantenimientoProgramado => StoppageCategory::Planned,
            self::ArranqueDePlanta, self::ApagadoDePlanta,
            self::FallaOperativa, self::Capacitaciones => StoppageCategory::Operational,
            self::FallaMecanica => StoppageCategory::Mechanical,
            self::FallaElectrica => StoppageCategory::Electrical,
            self::Atascamiento => StoppageCategory::Process,
            self::FaltaFrutaEsterilizada => StoppageCategory::Process,
            self::FaltaFrutaFresca => StoppageCategory::RawMaterial,
            self::CorteEnergiaRed => StoppageCategory::Utilities,
        };
    }

    /**
     * Los Tipo II de un Tipo I dado — para el desplegable dependiente del formulario.
     *
     * @return array<string, string>
     */
    public static function optionsFor(ReportedStoppageType $reportedType): array
    {
        return array_reduce(
            array_filter(self::cases(), fn (self $case): bool => $case->reportedType() === $reportedType),
            fn (array $options, self $case): array => [...$options, $case->value => $case->label()],
            [],
        );
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
