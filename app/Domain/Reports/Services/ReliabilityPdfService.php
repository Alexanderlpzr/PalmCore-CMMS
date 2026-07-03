<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\EquipmentKpi;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class ReliabilityPdfService implements PdfReport
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $kpis = EquipmentKpi::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['equipment.plant', 'equipment.area'])
            ->orderByDesc('downtime_hours')
            ->get();

        $summary = EquipmentKpi::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw(
                'COUNT(DISTINCT equipment_id) AS total_equipment,
                 SUM(failure_count) AS total_failures,
                 SUM(downtime_hours) AS total_downtime,
                 AVG(availability_percentage) AS avg_availability,
                 AVG(mtbf_hours) AS avg_mtbf,
                 AVG(mttr_hours) AS avg_mttr'
            )
            ->first();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = $this->branding->generateDocumentNumber('REL');

        return Pdf::loadView('reports.reliability', [
            'kpis' => $kpis,
            'summary' => $summary,
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($this->branding->documentIdentityPayload($documentNumber, $tenant)),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(string $tenantId, ?string $recordId = null): string
    {
        return 'CONFIABILIDAD-'.now()->format('Ymd').'.pdf';
    }
}
