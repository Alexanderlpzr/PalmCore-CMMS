<?php

namespace App\Domain\HumanResources\Enums;

/**
 * En qué punto está la nómina de un período.
 *
 * Cerrar no es un trámite: es el momento en que las cifras dejan de recalcularse. Una
 * nómina abierta se puede volver a liquidar cuantas veces haga falta —llegan marcas, se
 * corrige una novedad, se carga una bonificación—; una cerrada ya se pagó y se aportó, y
 * volver a calcularla cambiaría un número que alguien tiene en su desprendible.
 */
enum PayrollRunStatus: string
{
    case Borrador = 'borrador';
    case Cerrada = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Cerrada => 'Cerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'warning',
            self::Cerrada => 'success',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Borrador;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
