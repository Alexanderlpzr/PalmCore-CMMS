<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\SparePart;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class InventoryPdfService implements PdfReport
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $parts = SparePart::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with([
                'manufacturer',
                'supplier',
                'warehouseStock.warehouse',
            ])
            ->orderBy('code')
            ->get();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = $this->branding->generateDocumentNumber('INV');

        return Pdf::loadView('reports.inventory', [
            'parts' => $parts,
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
        return 'INV-'.now()->format('Ymd-His').'.pdf';
    }
}
