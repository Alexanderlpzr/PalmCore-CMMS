<?php

namespace App\Domain\Reports\Services;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Domain\Reports\Contracts\PdfReport;
use App\Models\Plant;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A6 — el reporte de horas perdidas, exportable.
 *
 * Reemplaza dos hojas del Excel de indicadores: «Análisis PNP por equipo» y el
 * resumen por Tipo I. Keyed by plant *and* window, so — like the daily programme —
 * it does not fit the single-`recordId` {@see PdfReport}
 * contract and does not pretend to.
 */
class LostHoursPdfService
{
    public function __construct(
        private readonly DowntimeService $downtime,
        private readonly PlantKpiService $kpis,
        private readonly ReportBrandingService $branding,
    ) {}

    public function generate(
        Plant $plant,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
    ): string {
        $from = $from !== null ? Carbon::parse($from) : now()->startOfMonth();
        $to = $to !== null ? Carbon::parse($to) : now()->endOfMonth();

        $byEquipment = $this->downtime->lostHoursByEquipment($plant->id, $from, $to);
        $byCategory = $this->downtime->lostHoursByCategory($plant->id, $from, $to);

        $tenant = Tenant::withoutGlobalScopes()->find($plant->tenant_id);
        $documentNumber = $this->branding->generateDocumentNumber('PNP');

        return Pdf::loadView('reports.lost-hours', [
            'plant' => $plant,
            'from' => $from,
            'to' => $to,
            'byEquipment' => $byEquipment['equipment'],
            'plantWideHours' => $byEquipment['plant_wide_hours'],
            'totalHours' => $byEquipment['total_hours'],
            'byCategory' => collect($byCategory)->map(fn (float $hours, string $category): array => [
                'label' => StoppageCategory::from($category)->label(),
                'is_maintenance' => StoppageCategory::from($category)->isMaintenanceResponsibility(),
                'hours' => $hours,
            ])->values()->all(),
            'kpis' => $this->kpis->calculate($plant, $from, $to),
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($this->branding->documentIdentityPayload($documentNumber, $tenant)),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(Plant $plant, CarbonInterface|string|null $from = null): string
    {
        $from = $from !== null ? Carbon::parse($from) : now();

        return 'HORAS-PERDIDAS-'.$from->format('Y-m').'.pdf';
    }
}
