<?php

namespace App\Domain\Assets\Enums;

use App\Domain\Maintenance\Enums\WorkOrderType;

/**
 * Tipo I — the macro classification the plant already uses on its paper log.
 *
 * Tipo II (the specific cause: «atasco en prensa 2», «falla banda transportadora»)
 * stays free text on `stoppage_cause`: the real list is open and grows with the
 * operation. Forcing it into an enum would only push people to pick "Otro".
 */
enum StoppageCategory: string
{
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';
    case Instrumentation = 'instrumentation';
    case Process = 'process';
    case Operational = 'operational';
    case RawMaterial = 'raw_material';
    case Utilities = 'utilities';
    case External = 'external';
    case Planned = 'planned';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mechanical => 'Mecánico',
            self::Electrical => 'Eléctrico',
            self::Instrumentation => 'Instrumentación',
            self::Process => 'Proceso',
            self::Operational => 'Operacional',
            self::RawMaterial => 'Falta de fruta',
            self::Utilities => 'Servicios industriales',
            self::External => 'Externo',
            self::Planned => 'Programado',
            self::Other => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mechanical, self::Electrical, self::Instrumentation => 'danger',
            self::Process, self::Operational => 'warning',
            self::RawMaterial, self::Utilities, self::External => 'gray',
            self::Planned => 'info',
            self::Other => 'gray',
        };
    }

    /**
     * Maintenance owns these. The rest (falta de fruta, corte de energía,
     * atascos de proceso) are stoppages the plant suffers but maintenance is not
     * accountable for — mixing them is how a maintenance department ends up
     * blamed for the weather.
     */
    public function isMaintenanceResponsibility(): bool
    {
        return in_array($this, [
            self::Mechanical,
            self::Electrical,
            self::Instrumentation,
            self::Planned,
        ], strict: true);
    }

    public function isPlanned(): bool
    {
        return $this === self::Planned;
    }

    /**
     * The best Tipo I a work order can assert on its own. A planned intervention
     * is planned by definition; for the rest the OT genuinely does not know
     * whether the cause was mechanical or electrical, so it says so instead of
     * inventing a category — the técnico refines it when he diagnoses the failure.
     */
    public static function fromWorkOrderType(WorkOrderType $type): self
    {
        return match ($type) {
            WorkOrderType::Preventive => self::Planned,
            default => self::Other,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
