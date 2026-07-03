<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Contracts\PdfReport;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class MaintenancePlanPdfService implements PdfReport
{
    public function __construct(private readonly ReportBrandingService $branding) {}

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
        $documentNumber = $plan->plan_number;

        $qrTarget = $this->branding->recordUrl(
            'filament.admin.resources.maintenance.maintenance-plan.maintenance-plans.view',
            $tenant,
            $plan->id,
        ) ?? $this->branding->documentIdentityPayload($documentNumber, $tenant);

        return Pdf::loadView('reports.maintenance-plan', [
            'plan' => $plan,
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
        $number = MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $recordId)
            ->value('plan_number') ?? $recordId;

        return 'PM-'.str_replace('/', '-', (string) $number).'-'.now()->format('Ymd').'.pdf';
    }
}
