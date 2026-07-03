<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\Equipment;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class EquipmentPdfService implements PdfReport
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $equipment = Equipment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->with([
                'plant',
                'area',
                'category',
                'manufacturer',
                'supplier',
                'kpi',
                'documents',
            ])
            ->firstOrFail();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = 'FICHA-'.$equipment->code;

        $qrTarget = $this->branding->recordUrl(
            'filament.admin.resources.equipment.view',
            $tenant,
            $equipment->id,
        ) ?? $this->branding->documentIdentityPayload($documentNumber, $tenant);

        return Pdf::loadView('reports.equipment', [
            'equipment' => $equipment,
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($qrTarget),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(string $tenantId, ?string $recordId = null): string
    {
        $code = Equipment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->value('code') ?? $recordId;

        return 'EQ-'.str_replace('/', '-', (string) $code).'-'.now()->format('Ymd').'.pdf';
    }
}
