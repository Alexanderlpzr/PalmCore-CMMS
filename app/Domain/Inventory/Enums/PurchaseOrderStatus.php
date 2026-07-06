<?php

namespace App\Domain\Inventory\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviada',
            self::PartiallyReceived => 'Recibida parcial',
            self::Received => 'Recibida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** A draft can still be edited (lines added/removed) before it is sent. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Stock can be received against a PO that has been sent to the supplier. */
    public function canReceive(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyReceived], strict: true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Received, self::Cancelled], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
