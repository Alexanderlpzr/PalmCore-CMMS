<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Contracts\PdfReport;
use App\Domain\Reports\Services\DailySchedulePdfService;
use App\Domain\Reports\Services\EquipmentPdfService;
use App\Domain\Reports\Services\InventoryPdfService;
use App\Domain\Reports\Services\LostHoursPdfService;
use App\Domain\Reports\Services\MaintenancePlanPdfService;
use App\Domain\Reports\Services\ReliabilityPdfService;
use App\Domain\Reports\Services\WorkOrderPdfService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

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

    /**
     * El programa del día: el papel que el planificador reparte en la mañana.
     *
     * Keyed by date, not by record — hence its own method instead of the generic
     * {@see stream()} wrapper.
     */
    public function dailySchedule(Request $request, DailySchedulePdfService $service): Response
    {
        abort_if(! $request->user()->tokenCan('reports.read') && ! $request->user()->tokenCan('*'), 403);

        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'plant_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $day = isset($validated['date']) ? Carbon::parse($validated['date']) : now();

        // El id llega del request: se resuelve dentro del tenant, nunca globalmente.
        $plantId = isset($validated['plant_id'])
            ? Plant::withoutGlobalScopes()
                ->where('tenant_id', CurrentTenant::id())
                ->whereKey($validated['plant_id'])
                ->value('id')
            : null;

        abort_if(isset($validated['plant_id']) && $plantId === null, 404);

        $bytes = $service->generate(CurrentTenant::id(), $day, $plantId);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$service->filename($day).'"',
        ]);
    }

    /**
     * A6 — horas perdidas por equipo y por Tipo I, con Pareto. Por planta y ventana.
     */
    public function lostHours(Request $request, LostHoursPdfService $service, string $plant): Response
    {
        abort_if(! $request->user()->tokenCan('reports.read') && ! $request->user()->tokenCan('*'), 403);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        // El id llega del request: se resuelve dentro del tenant.
        $plant = Plant::withoutGlobalScopes()
            ->where('tenant_id', CurrentTenant::id())
            ->findOrFail($plant);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : now()->endOfMonth();

        return response($service->generate($plant, $from, $to), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$service->filename($plant, $from).'"',
        ]);
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
