<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Verified = 'verified';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Abierta',
            self::Planned => 'Planificada',
            self::InProgress => 'En Ejecución',
            self::OnHold => 'En Espera',
            self::Completed => 'Completada',
            self::Verified => 'Verificada',
            self::Closed => 'Cerrada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'info',
            self::Planned => 'info',
            self::InProgress => 'warning',
            self::OnHold => 'orange',
            self::Completed => 'warning',
            self::Verified => 'success',
            self::Closed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function allowedTransitions(): array
    {
        // El flujo vigente es Abierta → Cerrada (+ Cancelada): una OT abierta se
        // cierra directamente. Las transiciones intermedias (Planned/InProgress/
        // Completed/Verified) se conservan para las OT heredadas que aún vengan a
        // medio ciclo, pero desde cualquier estado abierto se puede cerrar de una.
        return match ($this) {
            self::Draft => [self::Planned, self::Closed, self::Cancelled],
            self::Planned => [self::InProgress, self::OnHold, self::Closed, self::Cancelled],
            self::InProgress => [self::OnHold, self::Completed, self::Closed, self::Cancelled],
            self::OnHold => [self::InProgress, self::Closed, self::Cancelled],
            self::Completed => [self::Verified, self::InProgress, self::Closed],
            self::Verified => [self::Closed],
            self::Closed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Planned], strict: true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], strict: true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::InProgress, self::OnHold], strict: true);
    }

    public function isPendingVerification(): bool
    {
        return $this === self::Completed;
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
