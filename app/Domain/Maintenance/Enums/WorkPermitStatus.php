<?php

namespace App\Domain\Maintenance\Enums;

/**
 * El ciclo de vida de un permiso: emitido → aceptado → cerrado.
 *
 * `Accepted` es el único estado que habilita el trabajo. Un permiso emitido por
 * HSE pero que el ejecutante no firmó no protege a nadie: nadie le explicó los
 * riesgos al que va a entrar al digestor.
 */
enum WorkPermitStatus: string
{
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitido — falta firma del ejecutante',
            self::Accepted => 'Aceptado — vigente',
            self::Closed => 'Cerrado',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'warning',
            self::Accepted => 'success',
            self::Closed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /** Solo un permiso aceptado autoriza el trabajo. */
    public function authorizesWork(): bool
    {
        return $this === self::Accepted;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => [...$options, $case->value => $case->label()],
            [],
        );
    }
}
