<?php

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    $this->service = app(WorkOrderService::class);
    $this->tenant = Tenant::factory()->create();
    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create();
});

function openReport(): EquipmentIssueReport
{
    return EquipmentIssueReport::factory()->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipment->id,
        'status' => IssueReportStatus::Open->value,
        'severity' => IssueSeverity::Medium->value,
        'description' => 'Ruido en el reductor de la prensa',
    ]);
}

it('crear OT desde un reporte la deja ligada y marca el reporte «OT creada»', function (): void {
    $report = openReport();

    $workOrder = $this->service->createFromIssueReport($report, [
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => WorkOrderPriority::P3Medium->value,
        'title' => 'Reparar reductor',
    ], $this->user);

    expect($workOrder->issue_report_id)->toBe($report->id)
        ->and($workOrder->equipment_id)->toBe($this->equipment->id)
        // La descripción cae del reporte si no se escribió otra.
        ->and($workOrder->description)->toBe('Ruido en el reductor de la prensa')
        ->and($report->fresh()->status)->toBe(IssueReportStatus::ConvertedToWO)
        ->and($report->fresh()->workOrder->id)->toBe($workOrder->id);
});

it('completar la OT nacida del reporte lo marca «Resuelto»', function (): void {
    $report = openReport();

    $workOrder = $this->service->createFromIssueReport($report, [
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => WorkOrderPriority::P3Medium->value,
        'title' => 'Reparar reductor',
    ], $this->user);

    $this->service->assignTechnician($workOrder, $this->user, TechnicianRole::Technician);
    $this->service->transition($workOrder, WorkOrderStatus::Planned, $this->user);
    $this->service->transition($workOrder, WorkOrderStatus::InProgress, $this->user);
    $this->service->transition($workOrder, WorkOrderStatus::Completed, $this->user, [
        'work_performed' => 'Cambio de rodamiento del reductor',
    ]);

    expect($report->fresh()->status)->toBe(IssueReportStatus::Resolved);
});

it('una OT suelta (sin reporte) no toca ningún reporte al completarse', function (): void {
    $workOrder = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => WorkOrderPriority::P3Medium->value,
        'title' => 'Mantenimiento suelto',
        'description' => 'desc',
    ], $this->user);

    $this->service->assignTechnician($workOrder, $this->user, TechnicianRole::Technician);
    $this->service->transition($workOrder, WorkOrderStatus::Planned, $this->user);
    $this->service->transition($workOrder, WorkOrderStatus::InProgress, $this->user);
    $this->service->transition($workOrder, WorkOrderStatus::Completed, $this->user, ['work_performed' => 'ok']);

    expect($workOrder->fresh()->issue_report_id)->toBeNull();
});
