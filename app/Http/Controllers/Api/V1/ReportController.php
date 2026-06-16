<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Contracts\PdfReport;
use App\Domain\Reports\Services\EquipmentPdfService;
use App\Domain\Reports\Services\InventoryPdfService;
use App\Domain\Reports\Services\MaintenancePlanPdfService;
use App\Domain\Reports\Services\ReliabilityPdfService;
use App\Domain\Reports\Services\WorkOrderPdfService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * On-demand PDF reports for the token-authenticated SPA.
 *
 * Thin streaming wrappers over the existing (already tested) DomPDF services in
 * App\Domain\Reports\Services — no report logic is duplicated here. Each method
 * is tenant-scoped via CurrentTenant and gated by the `reports.read` ability;
 * cross-tenant record ids surface as 404 from the underlying service.
 */
class ReportController extends Controller
{
    public function reliability(Request $request, ReliabilityPdfService $service): Response
    {
        return $this->stream($request, $service);
    }

    public function inventory(Request $request, InventoryPdfService $service): Response
    {
        return $this->stream($request, $service);
    }

    public function workOrder(Request $request, WorkOrderPdfService $service, string $id): Response
    {
        return $this->stream($request, $service, $id);
    }

    public function equipment(Request $request, EquipmentPdfService $service, string $id): Response
    {
        return $this->stream($request, $service, $id);
    }

    public function maintenancePlan(Request $request, MaintenancePlanPdfService $service, string $id): Response
    {
        return $this->stream($request, $service, $id);
    }

    private function stream(Request $request, PdfReport $service, ?string $recordId = null): Response
    {
        abort_if(! $request->user()->tokenCan('reports.read') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();
        $bytes = $service->generate($tenantId, $recordId);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$service->filename($tenantId, $recordId).'"',
        ]);
    }
}
