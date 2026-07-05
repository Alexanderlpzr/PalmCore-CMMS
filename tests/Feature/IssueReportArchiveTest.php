<?php

use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
});

function issueReportUser(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

it('lets administrador-general archive an acknowledged report', function () {
    $user = issueReportUser($this->tenant, 'administrador-general');
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Acknowledged,
    ]);

    expect($user->can('delete', $report))->toBeTrue();

    $report->delete();

    expect(EquipmentIssueReport::find($report->id))->toBeNull()
        ->and(EquipmentIssueReport::withTrashed()->find($report->id))->not->toBeNull();
});

it('denies archiving to every role other than administrador-general', function (string $role) {
    $user = issueReportUser($this->tenant, $role);
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Acknowledged,
    ]);

    expect($user->can('delete', $report))->toBeFalse();
})->with(['tecnico', 'supervisor', 'plant-manager', 'ingeniero-mantenimiento', 'operario', 'gerencia']);

it('lets administrador-general restore an archived report', function () {
    $user = issueReportUser($this->tenant, 'administrador-general');
    $report = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::ConvertedToMR,
    ]);
    $report->delete();

    expect($user->can('restore', $report))->toBeTrue();

    $report->restore();

    expect(EquipmentIssueReport::find($report->id))->not->toBeNull();
});

it('excludes archived reports from the default (non-trashed) query', function () {
    EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::ConvertedToMR,
    ])->delete();

    EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => IssueReportStatus::Open,
    ]);

    expect(EquipmentIssueReport::count())->toBe(1)
        ->and(EquipmentIssueReport::withTrashed()->count())->toBe(2);
});
