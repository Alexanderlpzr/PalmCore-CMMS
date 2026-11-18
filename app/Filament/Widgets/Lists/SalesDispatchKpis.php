<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\DispatchStatus;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Filament\Widgets\ListKpis;
use App\Models\SalesDispatch;
use Carbon\CarbonImmutable;

/**
 * Encima de los despachos, el ciclo en cuatro números: lo que va en camino, lo que el
 * cliente ya recibió y falta facturar, la cartera por cobrar y lo despachado en el año.
 */
class SalesDispatchKpis extends ListKpis
{
    protected function kpis(): array
    {
        $byStatus = SalesDispatch::query()
            ->selectRaw('status, count(*) as loads, coalesce(sum(departure_weight_tons), 0) as departure, coalesce(sum(arrival_weight_tons), 0) as arrival, coalesce(sum(invoice_amount), 0) as invoiced')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (SalesDispatch $row): string => $row->status->value);

        $inTransit = $byStatus->get(DispatchStatus::Cargado->value);
        $toInvoice = $byStatus->get(DispatchStatus::Descargado->value);
        $receivable = $byStatus->get(DispatchStatus::Facturado->value);

        // Aceite y palmiste no se suman: son productos distintos.
        $year = CarbonImmutable::parse(SalesDispatch::today())->startOfYear();
        $thisYear = SalesDispatch::query()
            ->whereDate('loaded_on', '>=', $year->toDateString())
            ->selectRaw('product, count(*) as loads, coalesce(sum(arrival_weight_tons), 0) as arrival')
            ->groupBy('product')
            ->get()
            ->keyBy(fn (SalesDispatch $row): string => $row->product->value);
        $oil = $thisYear->get(CommercialProduct::Cpo->value);
        $kernel = $thisYear->get(CommercialProduct::Pko->value);

        $receivableAmount = (float) ($receivable->invoiced ?? 0);
        $overdueAmount = (float) SalesDispatch::query()->overdue()->sum('invoice_amount');

        return [
            [
                'label' => 'En tránsito',
                'value' => number_format((int) ($inTransit->loads ?? 0), 0, ',', '.'),
                'detail' => self::tons((float) ($inTransit->departure ?? 0)).' en camino al cliente',
                'icon' => 'heroicon-o-truck',
                'tone' => ($inTransit->loads ?? 0) > 0 ? 'warning' : 'ok',
                'link' => ['tab' => DispatchStatus::Cargado->value],
            ],
            [
                'label' => 'Por facturar',
                'value' => number_format((int) ($toInvoice->loads ?? 0), 0, ',', '.'),
                'detail' => self::tons((float) ($toInvoice->arrival ?? 0)).' recibidas sin factura',
                'icon' => 'heroicon-o-document-text',
                'tone' => ($toInvoice->loads ?? 0) > 0 ? 'warning' : 'ok',
                'link' => ['tab' => DispatchStatus::Descargado->value],
            ],
            [
                'label' => 'Por cobrar',
                'value' => '$'.number_format($receivableAmount / 1_000_000, 0, ',', '.').' M',
                'detail' => number_format((int) ($receivable->loads ?? 0), 0, ',', '.').' facturas'
                    .($overdueAmount > 0 ? ' · vencido $'.number_format($overdueAmount / 1_000_000, 0, ',', '.').' M' : ' · nada vencido'),
                'icon' => 'heroicon-o-banknotes',
                'tone' => $overdueAmount > 0 ? 'danger' : ($receivableAmount > 0 ? 'warning' : 'ok'),
                'percent' => $receivableAmount > 0 ? $overdueAmount * 100 / $receivableAmount : null,
                'color' => '#dc2626',
                'link' => ['tab' => DispatchStatus::Facturado->value, 'filters' => ['overdue' => ['isActive' => true]]],
            ],
            [
                'label' => 'Aceite despachado en '.$year->year,
                'value' => self::tons((float) ($oil->arrival ?? 0)),
                'detail' => number_format((int) ($oil->loads ?? 0), 0, ',', '.').' cargues, por peso de llegada'
                    .($kernel !== null ? ' · palmiste '.self::tons((float) $kernel->arrival) : ''),
                'icon' => 'heroicon-o-chart-bar',
                'link' => ['filters' => ['product' => ['value' => CommercialProduct::Cpo->value]]],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return SalesDispatchResource::getUrl('index', $parameters);
    }

    private static function tons(float $tons): string
    {
        return number_format($tons, 2, ',', '.').' tn';
    }
}
