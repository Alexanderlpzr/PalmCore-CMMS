<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\CreateWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake(persistent_disk());

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('guarda la foto del antes al crear la OT', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'OT con foto del antes',
            'description' => 'Se fotografía cómo se encontró el equipo',
            'before_photo_path' => UploadedFile::fake()->image('antes.jpg', 400, 300),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $workOrder = WorkOrder::where('title', 'OT con foto del antes')->firstOrFail();

    expect($workOrder->before_photo_path)->not->toBeNull()
        ->and($workOrder->after_photo_path)->toBeNull();

    Storage::disk(persistent_disk())->assertExists($workOrder->before_photo_path);
});

it('guarda la foto del después al cerrar la OT y conserva la del antes', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'before_photo_path' => 'work-order-photos/antes.jpg',
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Se cambió el rodamiento',
            'after_photo_path' => UploadedFile::fake()->image('despues.jpg', 400, 300),
        ]);

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::Closed)
        ->and($workOrder->before_photo_path)->toBe('work-order-photos/antes.jpg')
        ->and($workOrder->after_photo_path)->not->toBeNull();

    Storage::disk(persistent_disk())->assertExists($workOrder->after_photo_path);
});

it('el detalle muestra el registro fotográfico cuando hay al menos una foto', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photo_path' => 'work-order-photos/antes.jpg',
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->assertSee('Registro fotográfico')
        ->assertSee('Antes');
});

it('el detalle oculta el registro fotográfico cuando no hay fotos', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photo_path' => null,
        'after_photo_path' => null,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->assertDontSee('Registro fotográfico');
});
