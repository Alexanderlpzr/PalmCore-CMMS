<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\Equipment;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class EquipmentPdfService implements PdfReport
{
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

        return Pdf::loadView('reports.equipment', [
            'equipment' => $equipment,
            'tenant' => $tenant,
            'logoBase64' => $this->logoBase64($tenant),
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

    private function logoBase64(?Tenant $tenant): ?string
    {
        if (! $tenant?->logo_path) {
            return null;
        }

        try {
            $content = Storage::disk(persistent_disk())->get($tenant->logo_path);
            $mime = Storage::disk(persistent_disk())->mimeType($tenant->logo_path);

            return "data:{$mime};base64,".base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }
}
