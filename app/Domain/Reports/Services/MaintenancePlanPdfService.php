<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class MaintenancePlanPdfService implements PdfReport
{
    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $plan = MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->with([
                'equipment.plant',
                'equipment.area',
                'responsibleUser',
                'tasks',
                'schedule',
            ])
            ->firstOrFail();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        return Pdf::loadView('reports.maintenance-plan', [
            'plan' => $plan,
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
        $number = MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->value('plan_number') ?? $recordId;

        return 'PM-'.str_replace('/', '-', (string) $number).'-'.now()->format('Ymd').'.pdf';
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
