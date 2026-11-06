<?php

namespace App\Domain\Notifications;

/**
 * De qué trata un aviso de la campana: le da su ícono, su color y el filtro del centro
 * de notificaciones.
 */
enum NotificationKind: string
{
    case Sst = 'sst';
    case WorkOrder = 'work_order';
    case IssueReport = 'issue_report';
    case Alert = 'alert';
    case Maintenance = 'maintenance';
    case Inventory = 'inventory';
    case Reliability = 'reliability';
    case Report = 'report';
    case Platform = 'platform';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Sst => 'Hallazgos SST',
            self::WorkOrder => 'Órdenes de trabajo',
            self::IssueReport => 'Reportes de novedad',
            self::Alert => 'Alertas',
            self::Maintenance => 'Preventivos',
            self::Inventory => 'Almacén',
            self::Reliability => 'Confiabilidad',
            self::Report => 'Descargas',
            self::Platform => 'Plataforma',
            self::General => 'General',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sst => 'heroicon-o-shield-exclamation',
            self::WorkOrder => 'heroicon-o-wrench-screwdriver',
            self::IssueReport => 'heroicon-o-megaphone',
            self::Alert => 'heroicon-o-bell-alert',
            self::Maintenance => 'heroicon-o-calendar-days',
            self::Inventory => 'heroicon-o-archive-box',
            self::Reliability => 'heroicon-o-chart-bar',
            self::Report => 'heroicon-o-arrow-down-tray',
            self::Platform => 'heroicon-o-server-stack',
            self::General => 'heroicon-o-bell',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $kind): array => [$kind->value => $kind->label()])->all();
    }
}
