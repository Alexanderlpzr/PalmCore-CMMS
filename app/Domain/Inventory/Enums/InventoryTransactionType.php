<?php

namespace App\Domain\Inventory\Enums;

enum InventoryTransactionType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Consumption = 'consumption';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Ingreso',
            self::Exit => 'Salida',
            self::Adjustment => 'Ajuste',
            self::TransferOut => 'Transferencia (Salida)',
            self::TransferIn => 'Transferencia (Entrada)',
            self::Consumption => 'Consumo OT',
            self::Return => 'Devolución OT',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entry => 'success',
            self::Exit => 'danger',
            self::Adjustment => 'warning',
            self::TransferOut => 'orange',
            self::TransferIn => 'info',
            self::Consumption => 'danger',
            self::Return => 'success',
        };
    }

    /** Positive = stock increases, negative = stock decreases. */
    public function stockDirection(): int
    {
        return match ($this) {
            self::Entry, self::TransferIn, self::Return => 1,
            self::Exit, self::TransferOut, self::Consumption => -1,
            self::Adjustment => 0, // sign comes from quantity itself
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::TransferOut || $this === self::TransferIn;
    }

    public function isWorkOrderRelated(): bool
    {
        return $this === self::Consumption || $this === self::Return;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
