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

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
