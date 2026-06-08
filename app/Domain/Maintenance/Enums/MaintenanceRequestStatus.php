<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceRequestStatus: string
{
    case Draft       = 'draft';
    case Submitted   = 'submitted';
    case UnderReview = 'under_review';
    case Approved    = 'approved';
    case Rejected    = 'rejected';
    case Cancelled   = 'cancelled';
    case Converted   = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft       => 'Borrador',
            self::Submitted   => 'Enviado',
            self::UnderReview => 'En Revisión',
            self::Approved    => 'Aprobado',
            self::Rejected    => 'Rechazado',
            self::Cancelled   => 'Cancelado',
            self::Converted   => 'Convertido a OT',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft       => 'gray',
            self::Submitted   => 'info',
            self::UnderReview => 'warning',
            self::Approved    => 'success',
            self::Rejected    => 'danger',
            self::Cancelled   => 'gray',
            self::Converted   => 'success',
        };
    }

    /** Returns the valid states a request can transition TO from this state. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft       => [self::Submitted, self::Cancelled],
            self::Submitted   => [self::UnderReview, self::Cancelled],
            self::UnderReview => [self::Approved, self::Rejected, self::Submitted],
            self::Approved    => [self::Converted, self::Cancelled],
            self::Rejected    => [self::Submitted],
            self::Cancelled   => [],
            self::Converted   => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /** States where the request document is still editable. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Submitted], strict: true);
    }

    /** Terminal states — no further action possible. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Converted], strict: true);
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
