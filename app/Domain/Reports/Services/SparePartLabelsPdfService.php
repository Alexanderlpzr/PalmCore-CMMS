<?php

namespace App\Domain\Reports\Services;

use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Models\SparePart;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Las etiquetas para el estante, como las de Sortly: el QR lleva a la ficha del repuesto
 * en fronda.app, y al lado van el código, el nombre y la ubicación. Tres por fila en A4.
 */
class SparePartLabelsPdfService
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    /**
     * @param  Collection<int, SparePart>  $parts
     */
    public function generate(Collection $parts, Tenant $tenant): string
    {
        $parts = (new EloquentCollection($parts->all()))->loadMissing('warehouseStock');

        $labels = $parts->sortBy('code')->map(fn (SparePart $part): array => [
            'code' => $part->code,
            'name' => $part->name,
            'unit' => $part->unit?->value,
            'location' => $part->warehouseStock->pluck('bin_location')->filter()->unique()->implode(' · '),
            'qr' => $this->branding->qrBase64(SparePartResource::getUrl('view', ['record' => $part], tenant: $tenant)),
        ])->values();

        return Pdf::loadView('reports.spare-part-labels', ['labels' => $labels, 'tenant' => $tenant])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }
}
