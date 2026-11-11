<?php

namespace App\Domain\Safety\Enums;

use App\Models\User;

/**
 * De dónde viene un hallazgo: de Seguridad y Salud en el Trabajo o de Gestión Ambiental.
 * Las dos cuentas tienen los mismos permisos, pero cada hallazgo dice de quién es, para
 * llevar la trazabilidad (pedido 2026-11-10). Va en la lista, en el Tablero, en los avisos
 * y en el cargo del reporte de novedad que le llega a mantenimiento.
 */
enum FindingOrigin: string
{
    case Sst = 'sst';
    case Ambiental = 'ambiental';

    public function label(): string
    {
        return match ($this) {
            self::Sst => 'SST',
            self::Ambiental => 'Ambiental',
        };
    }

    /** «Hallazgo SST H-001», «Hallazgo ambiental H-027». */
    public function adjective(): string
    {
        return match ($this) {
            self::Sst => 'SST',
            self::Ambiental => 'ambiental',
        };
    }

    /** El cargo con que firma el reporte de novedad que le llega a mantenimiento. */
    public function reporterPosition(): string
    {
        return match ($this) {
            self::Sst => 'Seguridad y Salud en el Trabajo',
            self::Ambiental => 'Gestión Ambiental',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sst => 'warning',
            self::Ambiental => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sst => 'heroicon-o-shield-exclamation',
            self::Ambiental => 'heroicon-o-globe-americas',
        };
    }

    /**
     * El que le corresponde a quien lo reporta: la cuenta ambiental reporta ambientales y
     * la de SST, de SST. Quien no es ninguna de las dos (o es las dos) lo elige.
     */
    public static function forUser(?User $user): ?self
    {
        if ($user === null) {
            return null;
        }

        $isSst = $user->hasRole('sst');
        $isAmbiental = $user->hasRole('ambiental');

        return match (true) {
            $isAmbiental && ! $isSst => self::Ambiental,
            $isSst && ! $isAmbiental => self::Sst,
            default => null,
        };
    }

    /** @return list<string> */
    public static function reporterPositions(): array
    {
        return array_map(fn (self $origin): string => $origin->reporterPosition(), self::cases());
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
