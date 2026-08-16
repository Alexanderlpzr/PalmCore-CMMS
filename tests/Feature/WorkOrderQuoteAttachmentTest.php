<?php

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\CreateWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\EditWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\AttachmentsRelationManager;
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

/**
 * El soporte de la cotización: qué se cotizó, contra la OT que lo ejecutó.
 *
 * Es opcional a propósito. La mayoría de las OT nace de una falla y no tiene
 * cotización que adjuntar; exigirla obligaría a inventarla, y un campo obligatorio
 * que la gente rellena con cualquier cosa vale menos que uno vacío.
 */
beforeEach(function (): void {
    Storage::fake(private_files_disk());
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

    $this->baseForm = [
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Reconstrucción del tornillo de la prensa',
        'description' => 'Trabajo cotizado con contratista',
    ];
});

it('adjunta el PDF de cotización al crear la OT', function (): void {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            ...$this->baseForm,
            'quote_document' => UploadedFile::fake()->create('cotizacion.pdf', 120, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $workOrder = WorkOrder::where('title', $this->baseForm['title'])->firstOrFail();
    $quote = $workOrder->quoteAttachment;

    expect($quote)->not->toBeNull()
        ->and($quote->attachment_type)->toBe(WorkOrderAttachmentType::Quote)
        ->and($quote->uploaded_by)->toBe($this->admin->id);

    Storage::disk(private_files_disk())->assertExists($quote->file_path);
});

it('crea la OT sin cotización cuando el trabajo no se cotizó', function (): void {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm($this->baseForm)
        ->call('create')
        ->assertHasNoFormErrors();

    $workOrder = WorkOrder::where('title', $this->baseForm['title'])->firstOrFail();

    expect($workOrder->quoteAttachment)->toBeNull()
        ->and($workOrder->attachments()->count())->toBe(0);
});

it('guarda el tamaño y el tipo reales de la cotización', function (): void {
    // `file_size` y `mime_type` son NOT NULL: escribirlas en null revienta el insert
    // en Postgres. Es la regresión que dejaba la pestaña de adjuntos inservible.
    //
    // Con contenido real y no `create()`: un archivo falso «de 120 KB» se guarda vacío,
    // así que el tamaño leído del disco sería 0 y la prueba no probaría nada.
    $pdf = UploadedFile::fake()->createWithContent(
        'cotizacion.pdf',
        "%PDF-1.4\nCotización de reconstrucción de tornillo\n%%EOF",
    );

    Livewire::test(CreateWorkOrder::class)
        ->fillForm([...$this->baseForm, 'quote_document' => $pdf])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = WorkOrder::where('title', $this->baseForm['title'])->firstOrFail()->quoteAttachment;

    expect($quote->file_size)->toBeGreaterThan(0)
        ->and($quote->mime_type)->not->toBeNull()
        ->and($quote->mime_type)->not->toBe('');
});

it('descarga la cotización desde su propia ruta', function (): void {
    // El disco privado es local en producción y no expone URL: sin esta ruta el PDF
    // queda guardado y fuera de alcance.
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            ...$this->baseForm,
            'quote_document' => UploadedFile::fake()->createWithContent('cotizacion.pdf', '%PDF-1.4'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = WorkOrder::where('title', $this->baseForm['title'])->firstOrFail()->quoteAttachment;

    $this->get(route('work-order-attachments.download', $quote))
        ->assertOk()
        ->assertDownload('cotizacion.pdf');
});

it('no deja descargar la cotización de otro tenant', function (): void {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            ...$this->baseForm,
            'quote_document' => UploadedFile::fake()->createWithContent('cotizacion.pdf', '%PDF-1.4'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = WorkOrder::where('title', $this->baseForm['title'])->firstOrFail()->quoteAttachment;

    $otherTenant = Tenant::factory()->create();
    $intruder = User::factory()->create(['is_active' => true]);
    $intruder->tenants()->attach($otherTenant->id, ['joined_at' => now()]);

    $this->actingAs($intruder)
        ->get(route('work-order-attachments.download', $quote))
        ->assertForbidden();
});

it('adjunta un archivo desde la pestaña de adjuntos sin reventar contra Postgres', function (): void {
    // El relation manager forzaba file_size y mime_type a null contra dos columnas
    // NOT NULL: cualquier adjunto fallaba. Este test es el que lo impide volver.
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    // La pestaña se edita desde la página de edición: en la de vista, Filament deja
    // los relation managers en solo lectura por defecto.
    Livewire::test(AttachmentsRelationManager::class, [
        'ownerRecord' => $workOrder,
        'pageClass' => EditWorkOrder::class,
    ])
        ->callAction(TestAction::make('create')->table(), data: [
            'attachment_type' => WorkOrderAttachmentType::Quote->value,
            'file_path' => UploadedFile::fake()->createWithContent('soporte.pdf', "%PDF-1.4\nsoporte\n%%EOF"),
            'caption' => 'Cotización de Disam',
        ])
        ->assertHasNoActionErrors();

    $attachment = $workOrder->attachments()->firstOrFail();

    expect($attachment->file_size)->toBeGreaterThan(0)
        ->and($attachment->mime_type)->not->toBeNull()
        ->and($attachment->attachment_type)->toBe(WorkOrderAttachmentType::Quote);
});
