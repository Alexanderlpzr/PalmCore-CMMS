<?php

use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Services\EquipmentPdfService;
use App\Domain\Reports\Services\InventoryPdfService;
use App\Domain\Reports\Services\MaintenancePlanPdfService;
use App\Domain\Reports\Services\PendingWorkOrdersPdfService;
use App\Domain\Reports\Services\ReliabilityPdfService;
use App\Domain\Reports\Services\ReportManager;
use App\Domain\Reports\Services\WorkOrderPdfService;
use App\Jobs\GenerateInventoryReportJob;
use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\MaintenancePlan;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────────────────────

function pdfFake(): void
{
    Pdf::shouldReceive('loadView')->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');
}

function reportTenantWithUser(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    return ['tenant' => $tenant, 'user' => $user];
}

// ── WorkOrderPdfService ───────────────────────────────────────────────────────

it('WorkOrderPdfService generates bytes for a valid work order', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $bytes = app(WorkOrderPdfService::class)->generate($tenant->id, $wo->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('WorkOrderPdfService enforces tenant isolation', function () {
    pdfFake();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenantA->id]);

    expect(fn () => app(WorkOrderPdfService::class)->generate($tenantB->id, $wo->id))
        ->toThrow(ModelNotFoundException::class);
});

it('WorkOrderPdfService filename includes work_order_number', function () {
    $tenant = Tenant::factory()->create();
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'work_order_number' => 'OT-2026-001',
    ]);

    $filename = app(WorkOrderPdfService::class)->filename($tenant->id, $wo->id);

    expect($filename)->toContain('OT-2026-001');
});

// ── EquipmentPdfService ───────────────────────────────────────────────────────

it('EquipmentPdfService generates bytes for a valid equipment', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $bytes = app(EquipmentPdfService::class)->generate($tenant->id, $equipment->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('EquipmentPdfService enforces tenant isolation', function () {
    pdfFake();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenantA->id]);

    expect(fn () => app(EquipmentPdfService::class)->generate($tenantB->id, $equipment->id))
        ->toThrow(ModelNotFoundException::class);
});

it('EquipmentPdfService handles equipment with no KPI without crashing', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $bytes = app(EquipmentPdfService::class)->generate($tenant->id, $equipment->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

// ── MaintenancePlanPdfService ─────────────────────────────────────────────────

it('MaintenancePlanPdfService generates bytes for a valid plan', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    $plan = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id]);

    $bytes = app(MaintenancePlanPdfService::class)->generate($tenant->id, $plan->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('MaintenancePlanPdfService enforces tenant isolation', function () {
    pdfFake();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $plan = MaintenancePlan::factory()->create(['tenant_id' => $tenantA->id]);

    expect(fn () => app(MaintenancePlanPdfService::class)->generate($tenantB->id, $plan->id))
        ->toThrow(ModelNotFoundException::class);
});

// ── InventoryPdfService ───────────────────────────────────────────────────────

it('InventoryPdfService generates bytes for a tenant with parts', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    SparePart::factory()->create(['tenant_id' => $tenant->id]);

    $bytes = app(InventoryPdfService::class)->generate($tenant->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('InventoryPdfService generates bytes for an empty inventory without crashing', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();

    $bytes = app(InventoryPdfService::class)->generate($tenant->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('InventoryPdfService only includes parts for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    SparePart::factory()->create(['tenant_id' => $tenantA->id]);
    SparePart::factory()->create(['tenant_id' => $tenantB->id]);

    $capturedParts = null;

    Pdf::shouldReceive('loadView')
        ->withArgs(function (string $view, array $data) use (&$capturedParts): bool {
            $capturedParts = $data['parts'] ?? null;

            return true;
        })
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');

    app(InventoryPdfService::class)->generate($tenantA->id);

    expect($capturedParts)->not->toBeNull()
        ->and($capturedParts)->toHaveCount(1)
        ->and($capturedParts->first()->tenant_id)->toBe($tenantA->id);
});

it('InventoryPdfService excludes soft-deleted spare parts', function () {
    $tenant = Tenant::factory()->create();
    $activePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);
    $deletedPart = SparePart::factory()->create(['tenant_id' => $tenant->id]);
    $deletedPart->delete();

    $capturedParts = null;

    Pdf::shouldReceive('loadView')
        ->withArgs(function (string $view, array $data) use (&$capturedParts): bool {
            $capturedParts = $data['parts'] ?? null;

            return true;
        })
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');

    app(InventoryPdfService::class)->generate($tenant->id);

    expect($capturedParts)->toHaveCount(1)
        ->and($capturedParts->first()->id)->toBe($activePart->id);
});

// ── ReliabilityPdfService ─────────────────────────────────────────────────────

it('ReliabilityPdfService generates bytes for a tenant with KPIs', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 90.00,
    ]);

    $bytes = app(ReliabilityPdfService::class)->generate($tenant->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('ReliabilityPdfService generates bytes for a tenant with no KPIs', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();

    $bytes = app(ReliabilityPdfService::class)->generate($tenant->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('ReliabilityPdfService only includes KPIs for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    EquipmentKpi::factory()->create(['tenant_id' => $tenantA->id, 'equipment_id' => $equipA->id]);
    EquipmentKpi::factory()->create(['tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id]);

    $capturedKpis = null;

    Pdf::shouldReceive('loadView')
        ->withArgs(function (string $view, array $data) use (&$capturedKpis): bool {
            $capturedKpis = $data['kpis'] ?? null;

            return true;
        })
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');

    app(ReliabilityPdfService::class)->generate($tenantA->id);

    expect($capturedKpis)->toHaveCount(1)
        ->and($capturedKpis->first()->tenant_id)->toBe($tenantA->id);
});

it('ReliabilityPdfService excludes soft-deleted KPI records', function () {
    $tenant = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $activeKpi = EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipA->id]);
    $deletedKpi = EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipB->id]);
    $deletedKpi->delete();

    $capturedKpis = null;

    Pdf::shouldReceive('loadView')
        ->withArgs(function (string $view, array $data) use (&$capturedKpis): bool {
            $capturedKpis = $data['kpis'] ?? null;

            return true;
        })
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');

    app(ReliabilityPdfService::class)->generate($tenant->id);

    expect($capturedKpis)->toHaveCount(1)
        ->and($capturedKpis->first()->equipment_id)->toBe($equipA->id);
});

// ── PendingWorkOrdersPdfService ───────────────────────────────────────────────

it('PendingWorkOrdersPdfService generates bytes for a tenant with pending OTs', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'planned']);

    $bytes = app(PendingWorkOrdersPdfService::class)->generate($tenant->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('PendingWorkOrdersPdfService generates bytes when there are no pending OTs', function () {
    pdfFake();

    $tenant = Tenant::factory()->create();

    $bytes = app(PendingWorkOrdersPdfService::class)->generate($tenant->id);

    expect($bytes)->toBeString()->not->toBeEmpty();
});

it('PendingWorkOrdersPdfService only includes non-terminal, non-completed statuses for the requested tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $draft = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'draft']);
    $planned = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'planned']);
    $inProgress = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'in_progress']);
    $onHold = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'on_hold']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'completed']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'verified']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'closed']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'status' => 'cancelled']);
    WorkOrder::factory()->create(['tenant_id' => $otherTenant->id, 'status' => 'planned']);

    $captured = null;

    Pdf::shouldReceive('loadView')
        ->withArgs(function (string $view, array $data) use (&$captured): bool {
            $captured = $data['workOrders'] ?? null;

            return true;
        })
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('setOption')->andReturnSelf();
    Pdf::shouldReceive('output')->andReturn('%PDF-1.4 test');

    app(PendingWorkOrdersPdfService::class)->generate($tenant->id);

    expect($captured)->not->toBeNull()
        ->and($captured->pluck('id')->sort()->values()->all())
        ->toBe(collect([$draft->id, $planned->id, $inProgress->id, $onHold->id])->sort()->values()->all());
});

// ── GenerateInventoryReportJob ────────────────────────────────────────────────

it('GenerateInventoryReportJob stores PDF under tenant path and notifies user', function () {
    ['tenant' => $tenant, 'user' => $user] = reportTenantWithUser();

    Storage::fake('reports');
    pdfFake();

    $request = new ReportRequest(
        type: ReportType::Inventory,
        tenantId: $tenant->id,
        requestedBy: $user->id,
    );

    (new GenerateInventoryReportJob($request))->handle(app(InventoryPdfService::class));

    $files = Storage::disk('reports')->allFiles();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith($tenant->id.'/');

    expect($user->notifications()->count())->toBeGreaterThan(0);
});

// ── ReportManager::cleanupOldReports ─────────────────────────────────────────

it('ReportManager::cleanupOldReports deletes files older than 7 days and keeps recent ones', function () {
    Storage::fake('reports');
    $disk = Storage::disk('reports');

    $disk->put('tenant-abc/new-report.pdf', '%PDF-1.4 new');

    // Write old file and backdate it
    $disk->put('tenant-abc/old-report.pdf', '%PDF-1.4 old');
    $oldPath = $disk->path('tenant-abc/old-report.pdf');
    touch($oldPath, now()->subDays(8)->timestamp);

    ReportManager::cleanupOldReports();

    expect($disk->exists('tenant-abc/new-report.pdf'))->toBeTrue()
        ->and($disk->exists('tenant-abc/old-report.pdf'))->toBeFalse();
});

// ── Scheduler registration ────────────────────────────────────────────────────

it('report cleanup scheduler is registered at 03:00', function () {
    $events = app(Schedule::class)->events();

    $cleanupEvents = collect($events)->filter(fn ($e) => $e->expression === '0 3 * * *');

    expect($cleanupEvents->count())->toBeGreaterThan(0);
});

// ── ReportRequest DTO ─────────────────────────────────────────────────────────

it('ReportRequest stores all fields correctly', function () {
    $request = new ReportRequest(
        type: ReportType::WorkOrder,
        tenantId: 'tenant-uuid',
        requestedBy: 'user-uuid',
        recordId: 'record-uuid',
    );

    expect($request->type)->toBe(ReportType::WorkOrder)
        ->and($request->tenantId)->toBe('tenant-uuid')
        ->and($request->requestedBy)->toBe('user-uuid')
        ->and($request->recordId)->toBe('record-uuid');
});

it('ReportRequest recordId defaults to null for aggregate reports', function () {
    $request = new ReportRequest(
        type: ReportType::Inventory,
        tenantId: 'tenant-uuid',
        requestedBy: 'user-uuid',
    );

    expect($request->recordId)->toBeNull();
});
