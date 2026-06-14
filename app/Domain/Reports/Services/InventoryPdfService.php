<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\SparePart;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InventoryPdfService implements PdfReport
{
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

        return Pdf::loadView('reports.inventory', [
            'parts' => $parts,
            'tenant' => $tenant,
            'logoBase64' => $this->logoBase64($tenant),
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

    private function logoBase64(?Tenant $tenant): ?string
    {
        if (! $tenant?->logo_path) {
            return null;
        }

        try {
            $content = Storage::disk('public')->get($tenant->logo_path);
            $mime = Storage::disk('public')->mimeType($tenant->logo_path);

            return "data:{$mime};base64,".base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }
}
