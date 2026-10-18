<?php

namespace App\Domain\Reports\Excel;

use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La planilla para contar en papel: ubicación por ubicación, el repuesto y una casilla en
 * blanco para lo que hay. No trae lo que dice el sistema —el conteo es ciego—.
 */
class InventoryCountSheetExcel
{
    public function download(InventoryCount $count): StreamedResponse
    {
        $count->loadMissing(['warehouse', 'lines.sparePart']);

        $rows = $count->lines
            ->sortBy(fn (InventoryCountLine $line): string => ($line->bin_location ?? 'ZZZ').'|'.$line->sparePart?->name)
            ->map(fn (InventoryCountLine $line): array => [
                $line->bin_location ?? '',
                $line->sparePart?->code,
                $line->sparePart?->name,
                $line->sparePart?->unit?->value,
                $line->counted_quantity !== null ? (float) $line->counted_quantity : '',
                '',
            ]);

        return FrondaWorkbook::create()
            ->sheet('Conteo')
            ->widths([1 => 18, 2 => 14, 3 => 44, 4 => 10, 5 => 14, 6 => 30])
            ->banner('Planilla de conteo '.$count->count_number, $count->warehouse?->name.' · '.$count->scope->label(), $count->tenant?->name, 6)
            ->table(['Ubicación', 'Código', 'Repuesto', 'Unidad', 'Contado', 'Observación'], $rows->values(), [5 => FrondaWorkbook::HOURS])
            ->footer()
            ->download('CONTEO-'.$count->count_number.'.xlsx');
    }
}
