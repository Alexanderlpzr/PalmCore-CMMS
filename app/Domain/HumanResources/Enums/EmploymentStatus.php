<?php

namespace App\Domain\HumanResources\Enums;

/**
 * En qué situación está el empleado frente a la empresa.
 *
 * El libro de la extractora tiene una columna «PERSONAL» con un solo valor, ACTIVO, en
 * los 48. Eso no significa que el estado sobre: significa que al retirado se le borra la
 * fila, y con ella su historia. Aquí el retirado se queda, porque su nómina de agosto
 * sigue existiendo en noviembre y alguien va a tener que explicarla.
 */
enum EmploymentStatus: string
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';
    case Retirado = 'retirado';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
            self::Retirado => 'Retirado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Suspendido => 'warning',
            self::Retirado => 'gray',
        };
    }

    /** Solo el activo puede marcar en portería. */
    public function canClockIn(): bool
    {
        return $this === self::Activo;
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
