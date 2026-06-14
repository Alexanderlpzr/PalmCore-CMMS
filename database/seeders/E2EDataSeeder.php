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
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Alert;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
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

        WorkOrder::withoutGlobalScopes()->firstOrCreate(
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
                'created_by' => $admin->id,
            ]
        );

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
    }
}
