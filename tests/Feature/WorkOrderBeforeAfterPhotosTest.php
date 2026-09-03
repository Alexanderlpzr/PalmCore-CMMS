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

/*
 * El registro fotográfico de la OT. Antes era una foto del antes y una del después;
 * ahora son dos galerías, porque un rodamiento que se cambia no se explica con una foto
 * y quien cerraba la OT terminaba eligiendo cuál de las cuatro que tomó era la que valía.
 */

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

it('guarda una sola foto del antes al crear la OT', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'OT con foto del antes',
            'description' => 'Se fotografía cómo se encontró el equipo',
            'before_photos' => [UploadedFile::fake()->image('antes.jpg', 400, 300)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $workOrder = WorkOrder::where('title', 'OT con foto del antes')->firstOrFail();

    expect($workOrder->before_photos)->toHaveCount(1)
        ->and($workOrder->after_photos)->toBeNull();

    Storage::disk(persistent_disk())->assertExists($workOrder->before_photos[0]);
});

it('guarda varias fotos del antes al crear la OT', function () {
    // Es lo que el módulo vino a permitir: tres ángulos del mismo problema.
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'OT con galería del antes',
            'description' => 'Tres ángulos de cómo se encontró el equipo',
            'before_photos' => [
                UploadedFile::fake()->image('antes-1.jpg', 400, 300),
                UploadedFile::fake()->image('antes-2.jpg', 400, 300),
                UploadedFile::fake()->image('antes-3.jpg', 400, 300),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $workOrder = WorkOrder::where('title', 'OT con galería del antes')->firstOrFail();

    expect($workOrder->before_photos)->toHaveCount(3);

    foreach ($workOrder->before_photos as $path) {
        Storage::disk(persistent_disk())->assertExists($path);
    }
});

it('guarda varias fotos del después al cerrar la OT y conserva las del antes', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'before_photos' => ['work-order-photos/antes.jpg'],
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Se cambió el rodamiento',
            'after_photos' => [
                UploadedFile::fake()->image('despues-1.jpg', 400, 300),
                UploadedFile::fake()->image('despues-2.jpg', 400, 300),
            ],
        ]);

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::Closed)
        ->and($workOrder->before_photos)->toBe(['work-order-photos/antes.jpg'])
        ->and($workOrder->after_photos)->toHaveCount(2);

    foreach ($workOrder->after_photos as $path) {
        Storage::disk(persistent_disk())->assertExists($path);
    }
});

it('cuenta las fotos de las dos galerías', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photos' => ['a.jpg', 'b.jpg'],
        'after_photos' => ['c.jpg'],
    ]);

    expect($workOrder->photoCount())->toBe(3)
        ->and($workOrder->hasPhotos())->toBeTrue();
});

it('el detalle muestra el registro fotográfico cuando hay al menos una foto', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photos' => ['work-order-photos/antes.jpg'],
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->assertSee('Registro fotográfico')
        ->assertSee('Antes');
});

it('el detalle anuncia cuántas fotos hay cuando son varias', function () {
    // Con una sola no se pone contador: un «(1)» permanente es ruido. Con varias sí,
    // porque avisa de que hay que desplazarse para verlas todas.
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photos' => ['a.jpg', 'b.jpg', 'c.jpg'],
        'after_photos' => ['d.jpg'],
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->assertSee('Antes (3)')
        ->assertSee('Después')
        ->assertDontSee('Después (1)');
});

it('el detalle oculta el registro fotográfico cuando no hay fotos', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photos' => null,
        'after_photos' => null,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->assertDontSee('Registro fotográfico');
});

it('una galería vacía cuenta igual que ninguna foto', function () {
    // `[]` llega cuando alguien sube fotos y después las quita todas.
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'before_photos' => [],
        'after_photos' => [],
    ]);

    expect($workOrder->hasPhotos())->toBeFalse();

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->assertDontSee('Registro fotográfico');
});
