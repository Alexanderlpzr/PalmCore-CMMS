<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderTaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En ejecución',
            self::Done => 'Ejecutada',
            self::Skipped => 'Omitida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Skipped => 'warning',
        };
    }

    /** A task no longer awaiting work — either done or deliberately skipped. */
    public function isResolved(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
