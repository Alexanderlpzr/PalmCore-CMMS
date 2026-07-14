<?php

namespace App\Domain\Assets\Enums;

/**
 * A5 — quién dice que la planta estuvo abajo.
 *
 * Un paro le resta horas a la producción del mes, y hasta ahora lo declaraba
 * mantenimiento sin contraparte. `Disputed` existe para que el desacuerdo tenga
 * dónde quedarse escrito en vez de resolverse borrando el paro: un paro discutido
 * sigue siendo un hecho, con la objeción de producción colgada al lado.
 */
enum StoppageConfirmationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Sin firmar',
            self::Confirmed => 'Confirmado por producción',
            self::Disputed => 'En disputa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Confirmed => 'success',
            self::Disputed => 'danger',
        };
    }

    /** Producción ya se pronunció: firmó o dejó constancia de que no está de acuerdo. */
    public function isSigned(): bool
    {
        return $this !== self::Pending;
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
