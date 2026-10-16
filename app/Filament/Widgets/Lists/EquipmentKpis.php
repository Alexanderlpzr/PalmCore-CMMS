<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Widgets\ListKpis;
use App\Models\Equipment;

/** Encima de los equipos: los activos, los críticos, los que están en mantenimiento y los sin plan. */
class EquipmentKpis extends ListKpis
{
    protected function kpis(): array
    {
        $active = Equipment::query()->where('status', EquipmentStatus::Active->value)->count();
        $critical = Equipment::query()->where('status', EquipmentStatus::Active->value)->where('criticality', EquipmentCriticality::Critical->value)->count();
        $maintenance = Equipment::query()->where('status', EquipmentStatus::UnderMaintenance->value)->count();
        $withoutPlan = Equipment::query()->where('status', EquipmentStatus::Active->value)->whereDoesntHave('maintenancePlans', fn ($q) => $q->where('is_active', true))->count();

        return [
            [
                'label' => 'Equipos activos',
                'value' => number_format($active, 0, ',', '.'),
                'detail' => 'En operación',
                'icon' => 'heroicon-o-cog-6-tooth',
                'link' => ['filters' => ['status' => ['value' => EquipmentStatus::Active->value]]],
            ],
            [
                'label' => 'Críticos',
                'value' => number_format($critical, 0, ',', '.'),
                'detail' => 'Si paran, para la planta',
                'icon' => 'heroicon-o-shield-exclamation',
                'percent' => $active > 0 ? $critical * 100 / $active : null,
                'color' => '#dc2626',
                'link' => ['filters' => ['criticality' => ['value' => EquipmentCriticality::Critical->value]]],
            ],
            [
                'label' => 'En mantenimiento',
                'value' => number_format($maintenance, 0, ',', '.'),
                'detail' => 'Fuera de operación por mantenimiento',
                'icon' => 'heroicon-o-wrench',
                'tone' => $maintenance > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['status' => ['value' => EquipmentStatus::UnderMaintenance->value]]],
            ],
            [
                'label' => 'Sin plan preventivo',
                'value' => number_format($withoutPlan, 0, ',', '.'),
                'detail' => 'Activos sin ningún plan activo',
                'icon' => 'heroicon-o-calendar',
                'tone' => $withoutPlan > 0 ? 'warning' : 'ok',
                'percent' => $active > 0 ? $withoutPlan * 100 / $active : null,
                'color' => '#f59e0b',
                'link' => ['filters' => ['sin_plan' => ['isActive' => true]]],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return EquipmentResource::getUrl('index', $parameters);
    }
}
