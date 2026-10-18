<?php

namespace App\Domain\Reports\Excel;

use App\Models\InventoryTransaction;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El kardex en Excel: los mismos movimientos que muestra la pantalla, con sus filtros,
 * y los totales de lo que entró y lo que salió.
 */
class KardexExcel
{
    /**
     * @param  Collection<int, InventoryTransaction>  $movements
     */
    public function download(Collection $movements): StreamedResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = Filament::getTenant();
        $timezone = $tenant?->plantTimezone() ?? 'America/Bogota';
        $movements = $movements->loadMissing(['warehouse:id,name', 'performedBy:id,name']);

        $headers = ['Fecha', 'Número', 'Movimiento', 'Código', 'Repuesto', 'Almacén', 'Cantidad', 'Costo unitario', 'Valor', 'Saldo', 'Referencia', 'Recibió', 'Registró'];

        $rows = $movements->map(fn (InventoryTransaction $movement): array => [
            $movement->performed_at?->copy()->setTimezone($timezone)->format('d/m/Y h:i a'),
            $movement->transaction_number,
            $movement->exit_reason?->label() ?? $movement->type->label(),
            $movement->spare_part_code_snapshot,
            $movement->spare_part_name_snapshot,
            $movement->warehouse?->name,
            round((float) $movement->quantity, 2),
            round((float) $movement->unit_cost, 0),
            round((float) $movement->quantity * (float) $movement->unit_cost, 0),
            round((float) $movement->new_stock, 2),
            $movement->reference_number,
            $movement->recipient_name,
            $movement->performedBy?->name,
        ]);

        $in = $movements->filter(fn (InventoryTransaction $m): bool => (float) $m->quantity > 0);
        $out = $movements->filter(fn (InventoryTransaction $m): bool => (float) $m->quantity < 0);
        $value = fn (Collection $items): float => round((float) $items->sum(fn (InventoryTransaction $m): float => abs((float) $m->quantity) * (float) $m->unit_cost), 0);
        $formats = [7 => FrondaWorkbook::HOURS, 8 => FrondaWorkbook::MONEY, 9 => FrondaWorkbook::MONEY, 10 => FrondaWorkbook::HOURS];

        return FrondaWorkbook::create()
            ->sheet('Kardex')
            ->widths([1 => 18, 2 => 18, 3 => 22, 4 => 12, 5 => 36, 6 => 18, 7 => 11, 8 => 14, 9 => 14, 10 => 10, 11 => 18, 12 => 22, 13 => 20])
            ->banner('Kardex de movimientos', $movements->count().' movimientos', $tenant?->name, count($headers))
            ->table($headers, $rows, $formats)
            ->totals(['Entró', '', '', '', '', '', '', '', $value($in), '', '', '', ''], $formats)
            ->totals(['Salió', '', '', '', '', '', '', '', $value($out), '', '', '', ''], $formats)
            ->footer()
            ->download('KARDEX-'.now()->format('Ymd').'.xlsx');
    }
}
