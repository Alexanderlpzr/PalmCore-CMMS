<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderPriority: string
{
    case P1Critical = 'p1_critical';
    case P2High     = 'p2_high';
    case P3Medium   = 'p3_medium';
    case P4Low      = 'p4_low';
    case P5Planned  = 'p5_planned';

    public function label(): string
    {
        return match ($this) {
            self::P1Critical => 'P1 — Crítico',
            self::P2High     => 'P2 — Alto',
            self::P3Medium   => 'P3 — Medio',
            self::P4Low      => 'P4 — Bajo',
            self::P5Planned  => 'P5 — Planificado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::P1Critical => 'danger',
            self::P2High     => 'warning',
            self::P3Medium   => 'info',
            self::P4Low      => 'gray',
            self::P5Planned  => 'success',
        };
    }

    public function slaHours(): ?int
    {
        return match ($this) {
            self::P1Critical => 4,
            self::P2High     => 24,
            self::P3Medium   => 72,
            self::P4Low      => 168,
            self::P5Planned  => null,
        };
    }

    public static function fromMaintenanceRequestPriority(MaintenanceRequestPriority $priority): self
    {
        return self::from($priority->value);
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
