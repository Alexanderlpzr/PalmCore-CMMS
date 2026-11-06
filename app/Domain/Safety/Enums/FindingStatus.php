<?php

namespace App\Domain\Safety\Enums;

/**
 * El estado de un hallazgo de SST y de la parte que le toca a cada área responsable.
 *
 * Un hallazgo queda «Ejecutado» cuando todas sus áreas terminan, y «Verificado» cuando
 * SST revisa el arreglo (con la foto del después): ahí se cierra. Las áreas solo llegan
 * hasta «Ejecutado».
 */
enum FindingStatus: string
{
    case Pendiente = 'pendiente';
    case EnEjecucion = 'en_ejecucion';
    case Ejecutado = 'ejecutado';
    case Verificado = 'verificado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnEjecucion => 'En ejecución',
            self::Ejecutado => 'Ejecutado',
            self::Verificado => 'Verificado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'danger',
            self::EnEjecucion => 'warning',
            self::Ejecutado => 'info',
            self::Verificado => 'success',
        };
    }

    /** Todavía hay que hacer algo: lo que cuenta para los vencidos. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pendiente, self::EnEjecucion], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
