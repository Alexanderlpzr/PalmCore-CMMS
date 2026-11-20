<?php

namespace App\Domain\Safety\Enums;

/**
 * Quién corrige un hallazgo de SST, y a qué rol de la empresa le llega.
 *
 * Mantenimiento lo recibe como un reporte de novedad, que se vuelve OT por el flujo de
 * siempre; al cerrarse la OT su parte queda hecha. Las demás áreas la marcan a mano.
 * Dirección es la gerencia: el administrador general.
 */
enum ResponsibleArea: string
{
    case Mantenimiento = 'mantenimiento';
    case Produccion = 'produccion';
    case Sst = 'sst';
    case Direccion = 'direccion';

    public function label(): string
    {
        return match ($this) {
            self::Mantenimiento => 'Mantenimiento',
            self::Produccion => 'Producción',
            self::Sst => 'SST',
            self::Direccion => 'Dirección',
        };
    }

    /**
     * Los roles de la empresa que reciben y trabajan la parte de esta área. SST la llevan
     * dos: «sst» y «ambiental», con los mismos permisos (pedido 2026-11-10).
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return match ($this) {
            self::Mantenimiento => ['mantenimiento'],
            self::Produccion => ['produccion'],
            self::Sst => ['sst', 'ambiental'],
            self::Direccion => ['administrador-general'],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }

    /**
     * Las que se marcan al reportar un hallazgo. Dirección no (pedido 2026-11-19): sigue
     * existiendo para los que ya la tienen —los importados del cuadro—, para filtrarlos y
     * para que la gerencia mueva su parte, pero no se le asignan hallazgos nuevos.
     *
     * @return array<string, string>
     */
    public static function reportableOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $case): bool => $case === self::Direccion)
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Las del cuadro de Excel: «Mantenimiento / Proceso», «Proceso / Sgsst»,
     * «Mantenimiento / Direccion», «Manteniiento»…
     *
     * @return list<self>
     */
    public static function fromText(?string $text): array
    {
        $areas = [];

        foreach (preg_split('#[/,;y]+#u', mb_strtolower((string) $text)) ?: [] as $part) {
            $part = trim($part);
            $area = match (true) {
                $part === '' => null,
                str_starts_with($part, 'mant') => self::Mantenimiento,
                str_starts_with($part, 'proc'), str_starts_with($part, 'prod') => self::Produccion,
                str_contains($part, 'sst') => self::Sst,
                str_starts_with($part, 'dir') => self::Direccion,
                default => null,
            };

            if ($area && ! in_array($area, $areas, true)) {
                $areas[] = $area;
            }
        }

        return $areas === [] ? [self::Mantenimiento] : $areas;
    }
}
