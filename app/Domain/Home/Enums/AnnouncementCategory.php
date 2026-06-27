<?php

namespace App\Domain\Home\Enums;

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
            self::Training => 'violet',
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
