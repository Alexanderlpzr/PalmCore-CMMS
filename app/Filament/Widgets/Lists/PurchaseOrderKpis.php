<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use App\Filament\Widgets\ListKpis;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;

/** Encima de las órdenes de compra: lo que está por llegar, lo atrasado y lo comprometido. */
class PurchaseOrderKpis extends ListKpis
{
    protected function kpis(): array
    {
        $today = CarbonImmutable::today();
        $open = PurchaseOrder::query()->open()->count();
        $overdue = PurchaseOrder::query()->open()->whereNotNull('expected_at')->whereDate('expected_at', '<', $today)->count();
        $committed = (float) PurchaseOrder::query()->open()->sum('total');
        $received = PurchaseOrder::query()->where('status', PurchaseOrderStatus::Received->value)->where('received_at', '>=', $today->startOfMonth())->count();

        return [
            [
                'label' => 'Abiertas',
                'value' => number_format($open, 0, ',', '.'),
                'detail' => 'En borrador, enviadas o recibidas en parte',
                'icon' => 'heroicon-o-truck',
                'link' => ['filters' => ['abiertas' => ['isActive' => true]]],
            ],
            [
                'label' => 'Entrega vencida',
                'value' => number_format($overdue, 0, ',', '.'),
                'detail' => 'La fecha esperada ya pasó',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $overdue > 0 ? 'danger' : 'ok',
                'percent' => $open > 0 ? $overdue * 100 / $open : null,
                'color' => '#dc2626',
                'link' => ['filters' => ['vencidas' => ['isActive' => true]]],
            ],
            [
                'label' => 'Valor comprometido',
                'value' => '$ '.number_format($committed, 0, ',', '.'),
                'detail' => 'De las abiertas',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Recibidas este mes',
                'value' => number_format($received, 0, ',', '.'),
                'detail' => 'Desde el 1 de '.$today->locale('es')->translatedFormat('F'),
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'ok',
                'link' => ['filters' => ['status' => ['value' => PurchaseOrderStatus::Received->value]]],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return PurchaseOrderResource::getUrl('index', $parameters);
    }
}
