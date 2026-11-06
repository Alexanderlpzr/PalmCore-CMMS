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
    case Proceso = 'proceso';
    case Sst = 'sst';
    case Direccion = 'direccion';

    public function label(): string
    {
        return match ($this) {
            self::Mantenimiento => 'Mantenimiento',
            self::Proceso => 'Proceso',
            self::Sst => 'SST',
            self::Direccion => 'Dirección',
        };
    }

    /** El rol de la empresa que recibe y trabaja la parte de esta área. */
    public function role(): string
    {
        return match ($this) {
            self::Mantenimiento => 'mantenimiento',
            self::Proceso => 'proceso',
            self::Sst => 'sst',
            self::Direccion => 'administrador-general',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
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
                str_starts_with($part, 'proc') => self::Proceso,
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
