<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Widgets\ListKpis;
use App\Models\Employee;
use App\Models\Tenant;
use Filament\Facades\Filament;

/** Encima de Personal: los activos y lo que les falta para liquidar y marcar. */
class EmployeeKpis extends ListKpis
{
    protected function kpis(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $attention = app(HumanResourcesDashboard::class)->attention($tenant);
        $active = Employee::query()->active()->count();

        return array_values(array_filter([
            [
                'label' => 'Activos',
                'value' => number_format($active, 0, ',', '.'),
                'detail' => 'Trabajando hoy en la empresa',
                'icon' => 'heroicon-o-user-group',
                'link' => ['filters' => ['status' => ['value' => EmploymentStatus::Activo->value]]],
            ],
            (auth()->user()?->can('viewAnySalary', Employee::class) ?? false) ? [
                'label' => 'Sin salario',
                'value' => number_format($attention['withoutSalary'], 0, ',', '.'),
                'detail' => 'No se pueden liquidar',
                'icon' => 'heroicon-o-banknotes',
                'tone' => $attention['withoutSalary'] > 0 ? 'danger' : 'ok',
                'link' => ['filters' => ['sin_salario' => ['isActive' => true]]],
            ] : null,
            [
                'label' => 'Sin carné',
                'value' => number_format($attention['withoutBadge'], 0, ',', '.'),
                'detail' => 'No pueden marcar en portería',
                'icon' => 'heroicon-o-qr-code',
                'tone' => $attention['withoutBadge'] > 0 ? 'warning' : 'ok',
                'percent' => $active > 0 ? $attention['withoutBadge'] * 100 / $active : null,
                'color' => '#f59e0b',
                'link' => ['filters' => ['sin_carne' => ['isActive' => true]]],
            ],
            [
                'label' => 'Contratos por vencer',
                'value' => number_format($attention['contractsEnding'], 0, ',', '.'),
                'detail' => 'Término fijo, próximos 30 días',
                'icon' => 'heroicon-o-document-text',
                'tone' => $attention['contractsEnding'] > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['contrato_por_vencer' => ['isActive' => true]]],
            ],
        ]));
    }

    protected function listUrl(array $parameters = []): string
    {
        return EmployeeResource::getUrl('index', $parameters);
    }
}
