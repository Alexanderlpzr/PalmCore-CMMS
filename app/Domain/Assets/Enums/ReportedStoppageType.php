<?php

namespace App\Domain\Assets\Enums;

/**
 * El «Tipo I» tal como El Pajuil lo escribe en su planilla, sin traducir.
 *
 * Ojo con la trampa: este Tipo I no clasifica *qué se rompió*, sino **quién paró
 * la línea**. Por eso convive con {@see StoppageCategory} en vez de reemplazarla —
 * son dos preguntas distintas y las dos se responden:
 *
 *   - Tipo I (esto):            «Operativa» — producción detuvo la planta.
 *   - Tipo I nuestro (categoría): «Mecánico» — lo que se rompió fue un rodamiento.
 *
 * La planilla real de junio 2026 tiene 88 paros con Tipo II «falla mecánica» o
 * «falla eléctrica» marcados como Tipo I «Operativa». Si el MTBF se calcula por
 * Tipo I —que es lo que hace su Excel— esas 88 fallas no existen y el indicador
 * sale ~3 veces mejor de lo que la planta realmente está.
 *
 * Guardar los dos permite reproducir su informe **y** publicar el número honesto,
 * con la diferencia auditable hasta el paro que la origina. Borrar este campo para
 * «simplificar» sería borrar la evidencia.
 */
enum ReportedStoppageType: string
{
    case Scheduled = 'programada';
    case Maintenance = 'mantenimiento';
    case Operational = 'operativa';
    case External = 'externa';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::Maintenance => 'Mantenimiento',
            self::Operational => 'Operativa',
            self::External => 'Externa',
        };
    }

    /** ¿La planta le atribuye este paro a mantenimiento? (Su criterio, no el nuestro.) */
    public function isAttributedToMaintenance(): bool
    {
        return in_array($this, [self::Maintenance, self::Scheduled], strict: true);
    }

    /**
     * El Tipo I que la planta habría escrito, deducido de la causa física.
     *
     * Es solo un valor por defecto para el paro que se registra en Fronda sin que
     * nadie declare el Tipo I. Cuando el dato viene de la planilla, se guarda el que
     * ellos escribieron — aunque contradiga la causa física. Esa contradicción es
     * justamente lo que hay que poder ver.
     */
    public static function inferredFrom(StoppageCategory $category): self
    {
        return match ($category) {
            StoppageCategory::Planned => self::Scheduled,
            StoppageCategory::Mechanical,
            StoppageCategory::Electrical,
            StoppageCategory::Instrumentation => self::Maintenance,
            StoppageCategory::External,
            StoppageCategory::Utilities => self::External,
            default => self::Operational,
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
