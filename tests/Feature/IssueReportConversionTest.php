<?php

use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;

// ── Issue Report → MaintenanceRequest conversion ──────────────────────────────

it('creates maintenance request from issue report', function () {
    $service = app(MaintenanceRequestService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $mr = $service->createFromIssueReport($report, [
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Reporte convertido',
        'description' => 'Descripción del reporte.',
    ], $user);

    expect($mr)->toBeInstanceOf(MaintenanceRequest::class)
        ->and($mr->issue_report_id)->toBe($report->id)
        ->and($mr->equipment_id)->toBe($equipment->id)
        ->and($mr->tenant_id)->toBe($tenant->id);
});

it('lands the new request directly in under_review, skipping draft/submitted', function () {
    $service = app(MaintenanceRequestService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $mr = $service->createFromIssueReport($report, [
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Reporte convertido',
        'description' => 'Descripción del reporte.',
    ], $user);

    expect($mr->status)->toBe(MaintenanceRequestStatus::UnderReview);
});

it('marks issue report as converted_to_mr after conversion', function () {
    $service = app(MaintenanceRequestService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $service->createFromIssueReport($report, [
        'request_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Test',
        'description' => 'desc',
    ], $user);

    $report->refresh();

    expect($report->status)->toBe(IssueReportStatus::ConvertedToMR);
});

it('converted mr inherits equipment from issue report', function () {
    $service = app(MaintenanceRequestService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $mr = $service->createFromIssueReport($report, [
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'desc',
    ], $user);

    expect($mr->equipment_id)->toBe($equipment->id);
});

// ── Acknowledge ───────────────────────────────────────────────────────────────

it('acknowledge sets status to acknowledged and records timestamp', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'open',
    ]);

    $report->acknowledge($user);

    expect($report->status)->toBe(IssueReportStatus::Acknowledged)
        ->and($report->acknowledged_at)->not->toBeNull()
        ->and($report->acknowledged_by)->toBe($user->id);
});
