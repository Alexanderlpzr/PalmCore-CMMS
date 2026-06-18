<?php

namespace Database\Seeders;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Services\QrCodeService;
use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use App\Domain\Inventory\Enums\SparePartUnit;
use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\Alert;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WebhookDeliveryLog;
use App\Models\WebhookSubscription;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class E2EDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'el-pajuil')->firstOrFail();

        $admin = User::where('email', 'admin@elpajuil.demo')->firstOrFail();

        $plant = Plant::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'PLT-01')
            ->firstOrFail();

        $area = Area::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('code', 'PRE-01')
            ->firstOrFail();

        // ── Equipment category ────────────────────────────────────────────────

        $category = EquipmentCategory::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-CAT-01'],
            [
                'name' => '[E2E] Prensas',
                'is_active' => true,
            ]
        );

        // ── Equipment ─────────────────────────────────────────────────────────

        $equipment = Equipment::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-PRE-001'],
            [
                'category_id' => $category->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'name' => '[E2E] Prensa Extractora Principal',
                'status' => EquipmentStatus::Active->value,
                'criticality' => EquipmentCriticality::High->value,
                'priority' => EquipmentPriority::P1->value,
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        // Ensure QR code is generated synchronously (bypasses the queue)
        if (! $equipment->qrCode) {
            app(QrCodeService::class)->createForEquipment($equipment);
        }

        // ── Warehouse ─────────────────────────────────────────────────────────

        $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-WH-01'],
            [
                'name' => '[E2E] Almacén de Pruebas',
                'location' => 'Zona E2E',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        // ── Spare part with stock ─────────────────────────────────────────────

        $sparePart = SparePart::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-SP-001'],
            [
                'name' => '[E2E] Filtro Hidráulico',
                'category_type' => SparePartCategoryType::Consumable->value,
                'criticality' => SparePartCriticality::Medium->value,
                'abc_classification' => SparePartAbcClassification::B->value,
                'unit' => SparePartUnit::Piece->value,
                'unit_cost' => 150000,
                'minimum_stock' => 5,
                'reorder_point' => 10,
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        WarehouseSparePart::withoutGlobalScopes()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'spare_part_id' => $sparePart->id],
            [
                'tenant_id' => $tenant->id,
                'current_stock' => 50,
                'reserved_stock' => 0,
                'average_unit_cost' => 150000,
            ]
        );

        // ── WorkOrder in InProgress (for inventory flow) ──────────────────────

        // Remove WOs created dynamically by previous E2E test runs so the global
        // sequence counter always starts clean. The static fixture (E2E-WO-0001)
        // uses a non-OT prefix and is safe to recreate with firstOrCreate below.
        WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('work_order_number', 'like', 'OT-%')
            ->delete();

        // updateOrCreate (not firstOrCreate) so that a previous E2E run that
        // advanced the WO through completed/verified/closed is always reset to
        // in_progress before the next run. Group 5 requires this start state.
        WorkOrder::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'work_order_number' => 'E2E-WO-0001'],
            [
                'equipment_id' => $equipment->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'work_order_type' => WorkOrderType::Corrective->value,
                'status' => WorkOrderStatus::InProgress->value,
                'priority' => WorkOrderPriority::P2High->value,
                'title' => '[E2E] OT para pruebas de inventario',
                'description' => 'Orden de trabajo creada automáticamente para E2E.',
                'planned_start_at' => now(),
                'planned_end_at' => now()->addHours(4),
                'actual_start_at' => now(),
                'started_at' => now(),
                'completed_at' => null,
                'actual_end_at' => null,
                'verified_at' => null,
                'closed_at' => null,
                'completed_by' => null,
                'verified_by' => null,
                'created_by' => $admin->id,
            ]
        );

        // ── Bulk action test fixtures (Grupo 6) ──────────────────────────────

        // E2E-WO-0004/0005: two planned WOs for the "full success" set_priority test (test 3).
        // Title prefix "[E2E success]" is distinct from "[E2E bulk]" so the search isolates them.
        WorkOrder::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'work_order_number' => 'E2E-WO-0004'],
            [
                'equipment_id' => $equipment->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'work_order_type' => WorkOrderType::Corrective->value,
                'status' => WorkOrderStatus::Planned->value,
                'priority' => WorkOrderPriority::P3Medium->value,
                'title' => '[E2E success] OT planificada-A',
                'description' => 'OT planificada para test de éxito total en acciones masivas.',
                'planned_start_at' => now()->addDay(),
                'planned_end_at' => now()->addDays(2),
                'actual_start_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'actual_end_at' => null,
                'verified_at' => null,
                'closed_at' => null,
                'completed_by' => null,
                'verified_by' => null,
                'created_by' => $admin->id,
            ]
        );

        WorkOrder::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'work_order_number' => 'E2E-WO-0005'],
            [
                'equipment_id' => $equipment->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'work_order_type' => WorkOrderType::Corrective->value,
                'status' => WorkOrderStatus::Planned->value,
                'priority' => WorkOrderPriority::P3Medium->value,
                'title' => '[E2E success] OT planificada-B',
                'description' => 'OT planificada para test de éxito total en acciones masivas.',
                'planned_start_at' => now()->addDay(),
                'planned_end_at' => now()->addDays(2),
                'actual_start_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'actual_end_at' => null,
                'verified_at' => null,
                'closed_at' => null,
                'completed_by' => null,
                'verified_by' => null,
                'created_by' => $admin->id,
            ]
        );

        // E2E-WO-0002: planned — cancellable, used for bulk cancel success path
        WorkOrder::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'work_order_number' => 'E2E-WO-0002'],
            [
                'equipment_id' => $equipment->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'work_order_type' => WorkOrderType::Corrective->value,
                'status' => WorkOrderStatus::Planned->value,
                'priority' => WorkOrderPriority::P3Medium->value,
                'title' => '[E2E bulk] OT planificada',
                'description' => 'Orden planificada para pruebas de acciones masivas.',
                'planned_start_at' => now()->addDay(),
                'planned_end_at' => now()->addDays(2),
                'actual_start_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'actual_end_at' => null,
                'verified_at' => null,
                'closed_at' => null,
                'completed_by' => null,
                'verified_by' => null,
                'created_by' => $admin->id,
            ]
        );

        // E2E-WO-0003: closed — not cancellable, triggers partial failure in bulk cancel
        WorkOrder::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'work_order_number' => 'E2E-WO-0003'],
            [
                'equipment_id' => $equipment->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'work_order_type' => WorkOrderType::Corrective->value,
                'status' => WorkOrderStatus::Closed->value,
                'priority' => WorkOrderPriority::P3Medium->value,
                'title' => '[E2E bulk] OT cerrada',
                'description' => 'Orden cerrada para pruebas de acciones masivas.',
                'planned_start_at' => now()->subDays(2),
                'planned_end_at' => now()->subDay(),
                'actual_start_at' => now()->subDays(2),
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subHours(4),
                'actual_end_at' => now()->subHours(4),
                'verified_at' => now()->subHours(2),
                'closed_at' => now()->subHour(),
                'completed_by' => $admin->id,
                'verified_by' => $admin->id,
                'created_by' => $admin->id,
            ]
        );

        // Cancel any non-E2E pending MRs so "Pendientes" only shows our 3 fixtures.
        // This prevents stale demo data or other-group artifacts from polluting nth() positions.
        MaintenanceRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [MaintenanceRequestStatus::Submitted->value, MaintenanceRequestStatus::UnderReview->value])
            ->where('request_number', 'not like', 'E2E-MR-%')
            ->update(['status' => MaintenanceRequestStatus::Cancelled->value]);

        // E2E-MR-001: under_review (oldest, nth(3) in DESC sort) — approvable in test 4.
        // E2E-MR-002: submitted (middle, nth(2)) — editable for set_priority in test 3; not directly approvable in test 4.
        // E2E-MR-003: submitted (newest, nth(1)) — editable for set_priority in test 3.
        // created_at is set explicitly (withoutTimestamps) to guarantee DESC sort order across runs.
        $mr1 = MaintenanceRequest::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'request_number' => 'E2E-MR-001'],
            [
                'equipment_id' => $equipment->id,
                'request_type' => MaintenanceRequestType::Corrective->value,
                'priority' => MaintenanceRequestPriority::P2High->value,
                'status' => MaintenanceRequestStatus::UnderReview->value,
                'title' => '[E2E bulk] Solicitud en revisión',
                'description' => 'Solicitud en revisión para pruebas de acciones masivas.',
                'submitted_at' => now()->subHours(2),
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'assigned_reviewer' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'created_by' => $admin->id,
            ]
        );
        $mr1->timestamps = false;
        $mr1->created_at = now()->subHours(2);
        $mr1->save();

        $mr2 = MaintenanceRequest::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'request_number' => 'E2E-MR-002'],
            [
                'equipment_id' => $equipment->id,
                'request_type' => MaintenanceRequestType::Corrective->value,
                'priority' => MaintenanceRequestPriority::P3Medium->value,
                'status' => MaintenanceRequestStatus::Submitted->value,
                'title' => '[E2E bulk] Solicitud enviada',
                'description' => 'Solicitud enviada para pruebas de acciones masivas.',
                'submitted_at' => now()->subHour(),
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'assigned_reviewer' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'created_by' => $admin->id,
            ]
        );
        $mr2->timestamps = false;
        $mr2->created_at = now()->subHour();
        $mr2->save();

        // E2E-MR-003: submitted — newest (created_at = now) so it appears first in DESC sort.
        // Together with E2E-MR-002 at nth(2) they give 2 editable MRs for test 3 success.
        $mr3 = MaintenanceRequest::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'request_number' => 'E2E-MR-003'],
            [
                'equipment_id' => $equipment->id,
                'request_type' => MaintenanceRequestType::Corrective->value,
                'priority' => MaintenanceRequestPriority::P4Low->value,
                'status' => MaintenanceRequestStatus::Submitted->value,
                'title' => '[E2E bulk] Solicitud enviada-2',
                'description' => 'Segunda solicitud enviada para pruebas de acciones masivas.',
                'submitted_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'assigned_reviewer' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'created_by' => $admin->id,
            ]
        );
        $mr3->timestamps = false;
        $mr3->created_at = now();
        $mr3->save();

        // E2E-PREV-001: maintenance plan for Grupo 7 (favoritos de preventivos).
        // Uses trigger_source=Manual (no time_frequency or meter_interval required).
        MaintenancePlan::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'plan_number' => 'E2E-PREV-001'],
            [
                'equipment_id' => $equipment->id,
                'name' => '[E2E] Plan Preventivo de Prueba',
                'description' => 'Plan preventivo creado para pruebas E2E de favoritos.',
                'trigger_source' => MaintenanceTriggerSource::Manual->value,
                'is_active' => true,
                'pause_when_equipment_inactive' => false,
                'responsible_user_id' => null,
            ]
        );

        // E2E-PRE-002: second equipment for bulk equipment selection tests
        $equipment2 = Equipment::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-PRE-002'],
            [
                'category_id' => $category->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'name' => '[E2E] Prensa Auxiliar',
                'status' => EquipmentStatus::Active->value,
                'criticality' => EquipmentCriticality::Medium->value,
                'priority' => EquipmentPriority::P2->value,
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        if (! $equipment2->qrCode) {
            app(QrCodeService::class)->createForEquipment($equipment2);
        }

        // ── Open alert (for alert flow) ───────────────────────────────────────

        Alert::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'title' => '[E2E] Vibración anormal en prensa E2E-PRE-001',
            ],
            [
                'severity' => AlertSeverity::Warning->value,
                'category' => AlertCategory::Maintenance->value,
                'status' => AlertStatus::Open->value,
                'message' => 'Vibración superior a 12 mm/s detectada. Revisar rodamientos.',
                'entity_type' => 'equipment',
                'entity_id' => $equipment->id,
                'metadata' => ['source' => 'e2e_test'],
            ]
        );

        // ── Alert fixtures — Grupo 10 (always reset to open) ─────────────────

        // Each fixture uses a distinct category to satisfy the alerts_open_idempotency
        // partial unique index on (tenant_id, entity_type, entity_id, category).
        // The existing [E2E] Vibración alert already holds the 'maintenance' slot.
        Alert::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => '[E2E] Alerta para resolver'],
            [
                'severity' => AlertSeverity::Warning->value,
                'category' => AlertCategory::Reliability->value,
                'status' => AlertStatus::Open->value,
                'closed_at' => null,
                'message' => 'Alerta warning creada para el test de resolución E2E-Grupo10.',
                'entity_type' => 'equipment',
                'entity_id' => $equipment->id,
                'metadata' => ['source' => 'e2e_test'],
            ]
        );

        Alert::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => '[E2E] Alerta para descartar'],
            [
                'severity' => AlertSeverity::Warning->value,
                'category' => AlertCategory::Inventory->value,
                'status' => AlertStatus::Open->value,
                'closed_at' => null,
                'message' => 'Alerta warning creada para el test de descarte E2E-Grupo10.',
                'entity_type' => 'equipment',
                'entity_id' => $equipment->id,
                'metadata' => ['source' => 'e2e_test'],
            ]
        );

        Alert::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => '[E2E] Alerta crítica de prueba'],
            [
                'severity' => AlertSeverity::Critical->value,
                'category' => AlertCategory::Automation->value,
                'status' => AlertStatus::Open->value,
                'closed_at' => null,
                'message' => 'Alerta crítica creada para el test de no-descarte E2E-Grupo10.',
                'entity_type' => 'equipment',
                'entity_id' => $equipment->id,
                'metadata' => ['source' => 'e2e_test'],
            ]
        );

        Alert::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'title' => '[E2E] Alerta para persistencia'],
            [
                'severity' => AlertSeverity::Warning->value,
                'category' => AlertCategory::WorkOrder->value,
                'status' => AlertStatus::Open->value,
                'closed_at' => null,
                'message' => 'Alerta warning creada para el test de persistencia E2E-Grupo10.',
                'entity_type' => 'equipment',
                'entity_id' => $equipment->id,
                'metadata' => ['source' => 'e2e_test'],
            ]
        );

        // ── Tenant B + user (for tenant isolation flow) ───────────────────────

        $tenantB = Tenant::firstOrCreate(
            ['slug' => 'e2e-tenant-b'],
            [
                'name' => '[E2E] Tenant B — Aislamiento',
                'country_code' => 'COL',
                'timezone' => 'America/Bogota',
                'locale' => 'es_CO',
                'is_active' => true,
            ]
        );

        $this->call(TenantRolesSeeder::class, false, ['tenant' => $tenantB]);

        $userB = User::firstOrCreate(
            ['email' => 'admin@e2etenantb.test'],
            [
                'name' => '[E2E] Admin Tenant B',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        if (! $userB->tenants()->where('tenants.id', $tenantB->id)->exists()) {
            $userB->tenants()->attach($tenantB->id, [
                'is_primary_tenant' => true,
                'is_owner' => true,
                'joined_at' => now(),
            ]);
        }

        setPermissionsTeamId($tenantB->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! $userB->hasRole('administrador-general')) {
            $userB->assignRole('administrador-general');
        }

        // ── WO in Verified status — Grupo 12 webhook close test ──────────────

        // Reset to Verified each run so the "Cerrar OT" button is always available.
        WorkOrder::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'work_order_number' => 'E2E-WO-WEBHOOK'],
            [
                'equipment_id' => $equipment->id,
                'plant_id' => $plant->id,
                'area_id' => $area->id,
                'work_order_type' => WorkOrderType::Corrective->value,
                'status' => WorkOrderStatus::Verified->value,
                'priority' => WorkOrderPriority::P2High->value,
                'title' => '[E2E] OT para test de cierre por webhook',
                'description' => 'OT en estado verificado lista para cerrar en el test de webhooks.',
                'planned_start_at' => now()->subDays(2),
                'planned_end_at' => now()->subDay(),
                'actual_start_at' => now()->subDays(2),
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subHours(4),
                'actual_end_at' => now()->subHours(4),
                'verified_at' => now()->subHour(),
                'closed_at' => null,
                'completed_by' => $admin->id,
                'verified_by' => $admin->id,
                'created_by' => $admin->id,
            ]
        );

        // ── Webhook alert fixture — Grupo 12 alert.resolved test ─────────────

        // Force-delete all previous 12F alert versions (including soft-deleted ones).
        // Using soft-delete (->delete()) leaves tombstones that updateOrCreate() ignores,
        // causing the alert to be recreated with a new UUID every run — making the
        // tinkerUuid() lookup in the spec find a soft-deleted (404) record.
        Alert::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('title', '[E2E] Alerta para webhook test')
            ->forceDelete();

        // Force-delete null-entity system alerts left by previous 12E runs.
        // Uses forceDelete() to avoid the same soft-delete accumulation issue.
        Alert::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('category', AlertCategory::System->value)
            ->whereNull('entity_type')
            ->whereNull('entity_id')
            ->forceDelete();

        // entity_type + entity_id distinguish this seeder alert (used in 12F) from the
        // API-created alert in 12E (null entity). AlertService::create() uses existsOpenAlert()
        // which matches on (tenant, entity_type, entity_id, category).
        Alert::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'title' => '[E2E] Alerta para webhook test',
            'severity' => AlertSeverity::Warning->value,
            'category' => AlertCategory::System->value,
            'status' => AlertStatus::Open->value,
            'closed_at' => null,
            'message' => 'Alerta creada por el seeder para validar alert.resolved en webhooks.',
            'entity_type' => 'e2e_webhook',
            'entity_id' => '00000000-0000-0000-0000-000000e2e12f',
            'metadata' => ['source' => 'e2e_webhook_test'],
        ]);

        // ── Webhook subscriptions — Grupo 12 ─────────────────────────────────

        $webhookEventsA = [
            WebhookEvent::WorkOrderCreated->value,
            WebhookEvent::WorkOrderClosed->value,
            WebhookEvent::MaintenanceRequestCreated->value,
            WebhookEvent::AlertCreated->value,
            WebhookEvent::AlertResolved->value,
        ];

        $subA = WebhookSubscription::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'url' => 'https://webhook.e2e.test/receive'],
            [
                'events' => $webhookEventsA,
                'secret' => 'e2e-webhook-secret-tenant-a',
                'is_active' => true,
                'failure_count' => 0,
                'last_triggered_at' => null,
                'last_error' => null,
                'created_by' => $admin->id,
            ]
        );

        // Clear delivery logs from previous runs so tests verify the current run's logs.
        WebhookDeliveryLog::withoutGlobalScopes()
            ->where('webhook_subscription_id', $subA->id)
            ->delete();

        // Tenant B subscription — must stay at 0 logs to validate tenant isolation in 12H.
        $subB = WebhookSubscription::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantB->id, 'url' => 'https://webhook.e2e.test/receive-b'],
            [
                'events' => $webhookEventsA,
                'secret' => 'e2e-webhook-secret-tenant-b',
                'is_active' => true,
                'failure_count' => 0,
                'last_triggered_at' => null,
                'last_error' => null,
                'created_by' => $userB->id,
            ]
        );

        WebhookDeliveryLog::withoutGlobalScopes()
            ->where('webhook_subscription_id', $subB->id)
            ->delete();
    }
}
