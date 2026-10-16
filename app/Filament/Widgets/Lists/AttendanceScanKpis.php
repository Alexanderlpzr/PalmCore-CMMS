<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Filament\Resources\AttendanceScans\AttendanceScanResource;
use App\Filament\Widgets\ListKpis;
use App\Models\AttendanceScan;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;

/** Encima de las marcas de portería: las de hoy, quién está adentro y las salidas que faltan. */
class AttendanceScanKpis extends ListKpis
{
    protected function kpis(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $today = CarbonImmutable::now($tenant->plantTimezone());
        $attention = app(HumanResourcesDashboard::class)->attention($tenant);
        $marksToday = AttendanceScan::query()->whereBetween('scanned_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])->count();
        $manual = AttendanceScan::query()->where('source', 'manual')->where('scanned_at', '>=', $today->startOfMonth()->utc())->count();

        return [
            [
                'label' => 'Marcas de hoy',
                'value' => number_format($marksToday, 0, ',', '.'),
                'detail' => $attention['markedToday'].' '.($attention['markedToday'] === 1 ? 'persona' : 'personas'),
                'icon' => 'heroicon-o-qr-code',
                'link' => ['filters' => ['fecha' => ['desde' => $today->toDateString(), 'hasta' => $today->toDateString()]]],
            ],
            [
                'label' => 'Adentro ahora',
                'value' => number_format($attention['inside'], 0, ',', '.'),
                'detail' => 'Entraron y no han salido',
                'icon' => 'heroicon-o-user-group',
            ],
            [
                'label' => 'Entradas sin salida',
                'value' => number_format($attention['openEntries'], 0, ',', '.'),
                'detail' => 'Turnos de los últimos 30 días sin salida',
                'icon' => 'heroicon-o-arrow-right-end-on-rectangle',
                'tone' => $attention['openEntries'] > 0 ? 'danger' : 'ok',
                'link' => ['filters' => ['fecha' => [
                    'desde' => $today->subDays(HumanResourcesDashboard::OPEN_ENTRIES_LOOKBACK_DAYS)->toDateString(),
                    'hasta' => $today->toDateString(),
                    'sin_salida' => true,
                ]]],
            ],
            [
                'label' => 'Hechas a mano',
                'value' => number_format($manual, 0, ',', '.'),
                'detail' => 'Este mes: agregadas o corregidas por RRHH',
                'icon' => 'heroicon-o-pencil-square',
                'link' => ['filters' => [
                    'fecha' => ['desde' => $today->startOfMonth()->toDateString(), 'hasta' => $today->toDateString()],
                    'source' => ['value' => 'manual'],
                ]],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return AttendanceScanResource::getUrl('index', $parameters);
    }
}
