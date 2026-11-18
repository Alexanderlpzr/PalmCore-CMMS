<?php

namespace App\Domain\Commercial\Enums;

/** El destino de una venta: lo que el Excel llamaba «Indicador» o «Destino». */
enum Market: string
{
    case Nacional = 'nacional';
    case Exportacion = 'exportacion';

    public function label(): string
    {
        return match ($this) {
            self::Nacional => 'Nacional',
            self::Exportacion => 'Exportación',
        };
    }

    /**
     * El semáforo de la parte nacional de las ventas, como lo pidió la Directora
     * Administrativa (2026-11-18): lo nacional debe ser más de la mitad, y entre más mejor.
     * Con la mitad o menos, rojo; desde el 70 %, verde; en medio, ámbar.
     */
    public const NATIONAL_RED_AT_MOST = 0.5;

    public const NATIONAL_GREEN_FROM = 0.7;

    /** 'danger', 'warning' o 'success' según la parte nacional (0–1); null sin ventas. */
    public static function nationalShareTone(?float $share): ?string
    {
        return match (true) {
            $share === null => null,
            $share <= self::NATIONAL_RED_AT_MOST => 'danger',
            $share < self::NATIONAL_GREEN_FROM => 'warning',
            default => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
