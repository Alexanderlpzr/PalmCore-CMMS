<?php

namespace App\Domain\Reports\Services;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Reports\Contracts\PdfReport;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class PendingWorkOrdersPdfService implements PdfReport
{
    private const PENDING_STATUSES = [
        WorkOrderStatus::Draft,
        WorkOrderStatus::Planned,
        WorkOrderStatus::InProgress,
        WorkOrderStatus::OnHold,
    ];

    public function __construct(private readonly ReportBrandingService $branding) {}

    public function generate(string $tenantId, ?string $recordId = null): string
    {
        $workOrders = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', array_map(fn (WorkOrderStatus $status): string => $status->value, self::PENDING_STATUSES))
            ->whereNull('deleted_at')
            ->with(['equipment.plant', 'equipment.area', 'technicians.user'])
            ->orderByRaw("CASE priority
                WHEN 'p1_critical' THEN 1
                WHEN 'p2_high' THEN 2
                WHEN 'p3_medium' THEN 3
                WHEN 'p4_low' THEN 4
                WHEN 'p5_planned' THEN 5
                ELSE 6 END")
            ->orderBy('planned_start_at')
            ->get();

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = $this->branding->generateDocumentNumber('OT-PEND');

        return Pdf::loadView('reports.pending-work-orders', [
            'workOrders' => $workOrders,
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
        return 'OT-PENDIENTES-'.now()->format('Ymd-His').'.pdf';
    }
}
