<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderPartStatus: string
{
    case Requested = 'requested';
    case Reserved = 'reserved';
    case Issued = 'issued';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitado',
            self::Reserved => 'Reservado',
            self::Issued => 'Emitido',
            self::Returned => 'Devuelto',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'gray',
            self::Reserved => 'info',
            self::Issued => 'success',
            self::Returned => 'warning',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
