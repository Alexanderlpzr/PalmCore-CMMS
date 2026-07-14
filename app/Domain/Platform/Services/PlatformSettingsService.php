<?php

namespace App\Domain\Platform\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Los interruptores del sistema.
 *
 * Viven en la base y no en la caché: un interruptor que decide si se hacen copias de
 * seguridad no puede olvidarse porque alguien reinició un contenedor. El olvido sería
 * silencioso y solo se notaría el día que hace falta un respaldo que nadie hizo.
 */
class PlatformSettingsService
{
    public const AUTOMATIC_BACKUPS = 'backups.automatic_enabled';

    /**
     * ¿Corre el respaldo nocturno?
     *
     * Por defecto sí. Un sistema que arranca sin respaldos porque nadie fue a
     * activarlos es un sistema sin respaldos, y ese es exactamente el estado en el que
     * esta plataforma estuvo meses sin que nadie lo supiera.
     */
    public function automaticBackupsEnabled(): bool
    {
        return (bool) ($this->get(self::AUTOMATIC_BACKUPS, ['enabled' => true])['enabled'] ?? true);
    }

    public function setAutomaticBackups(bool $enabled, ?User $actor = null): void
    {
        $this->set(self::AUTOMATIC_BACKUPS, ['enabled' => $enabled], $actor);
    }

    /** Quién apagó (o encendió) el interruptor, y cuándo. */
    public function automaticBackupsChangedBy(): ?array
    {
        $setting = PlatformSetting::with('updatedBy')->find(self::AUTOMATIC_BACKUPS);

        if ($setting === null) {
            return null;
        }

        return [
            'user' => $setting->updatedBy?->name,
            'at' => Carbon::parse($setting->updated_at),
        ];
    }

    // ── Genérico ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function get(string $key, array $default = []): array
    {
        return PlatformSetting::find($key)?->value ?? $default;
    }

    /** @param array<string, mixed> $value */
    public function set(string $key, array $value, ?User $actor = null): void
    {
        PlatformSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor?->id],
        );
    }
}
