<?php

namespace App\Domain\Maintenance\Enums;

/**
 * El concepto de un gasto de mantenimiento — en qué se invirtió el presupuesto.
 * Lista fija para que el desglose («en qué se fue la plata») sea tabulable.
 */
enum ExpenseCategory: string
{
    case Repuestos = 'repuestos';
    case ManoDeObra = 'mano_de_obra';
    case ServiciosTerceros = 'servicios_terceros';
    case Lubricantes = 'lubricantes';
    case Herramientas = 'herramientas';
    case Otros = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::Repuestos => 'Repuestos',
            self::ManoDeObra => 'Mano de obra / contratistas',
            self::ServiciosTerceros => 'Servicios de terceros',
            self::Lubricantes => 'Lubricantes y consumibles',
            self::Herramientas => 'Herramientas',
            self::Otros => 'Otros',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Repuestos => 'info',
            self::ManoDeObra => 'warning',
            self::ServiciosTerceros => 'danger',
            self::Lubricantes => 'success',
            self::Herramientas => 'gray',
            self::Otros => 'gray',
        };
    }

    /** Color de la porción del pie, en rgba, siguiendo el orden del enum. */
    public function chartColor(): string
    {
        return match ($this) {
            self::Repuestos => 'rgba(59, 130, 246, 0.8)',
            self::ManoDeObra => 'rgba(249, 115, 22, 0.8)',
            self::ServiciosTerceros => 'rgba(239, 68, 68, 0.8)',
            self::Lubricantes => 'rgba(16, 185, 129, 0.8)',
            self::Herramientas => 'rgba(168, 85, 247, 0.8)',
            self::Otros => 'rgba(100, 116, 139, 0.8)',
        };
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
