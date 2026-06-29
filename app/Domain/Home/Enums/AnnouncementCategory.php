<?php

namespace App\Domain\Home\Enums;

/**
 * Categoría/tipo del recurso unificado "Contenido" (UX-2).
 *
 * El Centro de Inicio usa un único recurso de Contenido cuya diferenciación
 * se hace por esta categoría — NO por recursos Filament separados. Para
 * habilitar un nuevo tipo de contenido basta con agregar aquí el case y su
 * entrada en label()/color(); el resto de la arquitectura (recurso, formulario,
 * tabla, API y feed) ya consume la categoría de forma genérica.
 *
 * Tipos previstos a futuro (aún no implementados): Eventos, Campañas, Videos,
 * Banners, Encuestas.
 */
enum AnnouncementCategory: string
{
    case News = 'news';
    case Communication = 'communication';
    case Training = 'training';
    case MaintenanceScheduled = 'maintenance_scheduled';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Noticia',
            self::Communication => 'Comunicado',
            self::Training => 'Capacitación',
            self::MaintenanceScheduled => 'Mantenimiento programado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::News => 'blue',
            self::Communication => 'emerald',
            self::Training => 'blue',
            self::MaintenanceScheduled => 'amber',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
