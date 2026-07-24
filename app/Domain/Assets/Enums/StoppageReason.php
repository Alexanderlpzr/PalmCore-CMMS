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
    case FallaMecanica = 'falla_mecanica';
    case FallaElectrica = 'falla_electrica';
    case Atascamiento = 'atascamiento';
    case FaltaFrutaEsterilizada = 'falta_fruta_esterilizada';
    case FaltaFrutaFresca = 'falta_fruta_fresca';
    case CorteEnergiaRed = 'corte_energia_red';

    public function label(): string
    {
        return match ($this) {
            self::MantenimientoProgramado => 'Mantenimiento programado',
            self::ArranqueDePlanta => 'Arranque de planta',
            self::FallaMecanica => 'Falla mecánica',
            self::FallaElectrica => 'Falla eléctrica',
            self::Atascamiento => 'Atascamiento',
            self::FaltaFrutaEsterilizada => 'Falta de fruta esterilizada',
            self::FaltaFrutaFresca => 'Falta de fruta fresca (RFF)',
            self::CorteEnergiaRed => 'Corte de energía de red',
        };
    }

    /** El Tipo I al que pertenece este Tipo II. */
    public function reportedType(): ReportedStoppageType
    {
        return match ($this) {
            self::MantenimientoProgramado, self::ArranqueDePlanta => ReportedStoppageType::Scheduled,
            self::FallaMecanica, self::FallaElectrica => ReportedStoppageType::Maintenance,
            self::Atascamiento, self::FaltaFrutaEsterilizada => ReportedStoppageType::Operational,
            self::FaltaFrutaFresca, self::CorteEnergiaRed => ReportedStoppageType::External,
        };
    }

    /** La categoría física real — la que decide si es o no falla de mantenimiento. */
    public function category(): StoppageCategory
    {
        return match ($this) {
            self::MantenimientoProgramado => StoppageCategory::Planned,
            self::ArranqueDePlanta => StoppageCategory::Operational,
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
