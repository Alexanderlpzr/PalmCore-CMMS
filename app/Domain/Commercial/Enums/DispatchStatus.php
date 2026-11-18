<?php

namespace App\Domain\Commercial\Enums;

/**
 * En qué va un cargue: sale de la planta, el cliente lo recibe y liquida, el contador lo
 * factura y el cliente lo paga. Cada paso lo marca un dato del despacho —el peso de
 * llegada, el número de factura, la fecha de pago—, no una casilla aparte.
 */
enum DispatchStatus: string
{
    case Cargado = 'cargado';
    case Descargado = 'descargado';
    case Facturado = 'facturado';
    case Pagado = 'pagado';

    public function label(): string
    {
        return match ($this) {
            self::Cargado => 'En tránsito',
            self::Descargado => 'Por facturar',
            self::Facturado => 'Por cobrar',
            self::Pagado => 'Pagado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cargado => 'warning',
            self::Descargado => 'info',
            self::Facturado => 'danger',
            self::Pagado => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cargado => 'heroicon-o-truck',
            self::Descargado => 'heroicon-o-document-text',
            self::Facturado => 'heroicon-o-banknotes',
            self::Pagado => 'heroicon-o-check-circle',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
