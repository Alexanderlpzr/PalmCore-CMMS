<?php

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Maintenance\IssueReport\Pages\ListIssueReports;
use App\Filament\Resources\Maintenance\IssueReport\Pages\ViewIssueReport;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'A02STR.02.01',
        'name' => 'Redler #2 de Fruta a Esterilizadores',
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('la pestaña Pendientes solo muestra los reportes sin atender', function () {
    $pendiente = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Open->value,
    ]);
    $reconocido = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Acknowledged->value,
    ]);

    Livewire::test(ListIssueReports::class)
        ->assertCanSeeTableRecords([$pendiente])
        ->assertCanNotSeeTableRecords([$reconocido]);
});

it('la pestaña Atendidos reúne reconocidos, con OT y resueltos', function () {
    $pendiente = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Open->value,
    ]);
    $reconocido = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Acknowledged->value,
    ]);
    $conOt = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::ConvertedToWO->value,
    ]);
    $resuelto = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Resolved->value,
    ]);

    Livewire::test(ListIssueReports::class)
        ->set('activeTab', 'atendidos')
        ->assertCanSeeTableRecords([$reconocido, $conOt, $resuelto])
        ->assertCanNotSeeTableRecords([$pendiente]);
});

it('un reporte nuevo levanta una alerta de mantenimiento', function () {
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Open->value,
        'severity' => IssueSeverity::Medium->value,
        'reporter_name' => 'Sebastian',
        'description' => 'Desgaste en platina de sacrificio',
    ]);

    $alert = Alert::query()
        ->where('entity_type', EquipmentIssueReport::class)
        ->where('entity_id', $report->id)
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert->category)->toBe(AlertCategory::Maintenance)
        ->and($alert->severity)->toBe(AlertSeverity::Warning)
        ->and($alert->status)->toBe(AlertStatus::Open)
        ->and($alert->title)->toContain('Redler #2 de Fruta a Esterilizadores')
        ->and($alert->message)->toContain('Sebastian')
        ->and($alert->message)->toContain('Desgaste en platina de sacrificio');
});

it('una novedad crítica levanta la alerta como crítica', function () {
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Open->value,
        'severity' => IssueSeverity::Critical->value,
    ]);

    expect(Alert::where('entity_id', $report->id)->first()->severity)
        ->toBe(AlertSeverity::Critical);
});

it('al reconocer el reporte la alerta se cierra sola', function () {
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Open->value,
    ]);

    expect(Alert::where('entity_id', $report->id)->where('status', AlertStatus::Open->value)->exists())
        ->toBeTrue();

    $report->acknowledge($this->admin);

    expect(Alert::where('entity_id', $report->id)->where('status', AlertStatus::Open->value)->exists())
        ->toBeFalse();
});

it('el modal de Crear OT desde el reporte guarda responsable y clase de mantenimiento', function () {
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Acknowledged->value,
        'description' => 'Desgaste en platina de sacrificio',
    ]);

    Livewire::test(ViewIssueReport::class, ['record' => $report->getKey()])
        ->callAction('create_wo', data: [
            'work_order_type' => WorkOrderType::Corrective->value,
            'priority' => WorkOrderPriority::P3Medium->value,
            'maintenance_area' => MaintenanceArea::Mecanico->value,
            'executed_by' => 'El mecánico y su auxiliar',
            'title' => 'Cambio de platina',
            'description' => 'Desgaste en platina de sacrificio',
        ])
        ->assertHasNoActionErrors();

    $workOrder = WorkOrder::where('issue_report_id', $report->id)->firstOrFail();

    expect($workOrder->executed_by)->toBe('El mecánico y su auxiliar')
        ->and($workOrder->maintenance_area)->toBe(MaintenanceArea::Mecanico)
        ->and($report->fresh()->status)->toBe(IssueReportStatus::ConvertedToWO);
});

it('el detalle del reporte ya no muestra «Usuario registrado»', function () {
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    Livewire::test(ViewIssueReport::class, ['record' => $report->getKey()])
        ->assertSee('Reportante')
        ->assertDontSee('Usuario registrado');
});
