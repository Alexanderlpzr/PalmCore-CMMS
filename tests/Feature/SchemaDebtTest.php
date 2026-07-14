<?php

use App\Models\Area;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Support\Facades\DB;

/**
 * La deuda de esquema que la auditoría dejó anotada: M3, M4 y M6.
 *
 * Ninguna de las tres rompía nada hoy. Las tres eran la clase de detalle que se
 * cobra tarde: una moneda que nadie eligió, una restricción que fallaba al insertar
 * un área, y un repuesto con dos identidades que se contradecían.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
});

// ── M3 — la moneda de la operación ───────────────────────────────────────────

it('defaults equipment to the currency the plant actually operates in', function (): void {
    // Nadie eligió el dólar: era el default de la columna, y bastaba con crear un
    // equipo sin tocar el campo para etiquetar su costo en una moneda que la planta
    // no usa. El dólar ahora se elige a propósito (una bomba importada), no por
    // descuido del formulario.
    $default = DB::selectOne(<<<'SQL'
        SELECT column_default
        FROM information_schema.columns
        WHERE table_name = 'equipment' AND column_name = 'currency_code'
    SQL);

    expect($default->column_default)->toContain('COP');
});

// ── M4 — el orden no es una restricción ──────────────────────────────────────

it('lets two areas share a position in the process flow', function (): void {
    // Que dos áreas empaten en sort_order no es un dato corrupto: es una preferencia
    // sin definir. Antes, esto reventaba con un error de unicidad.
    Area::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'EXT-01',
        'sort_order' => 10,
    ]);

    $second = Area::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'AAA-01',
        'sort_order' => 10,
    ]);

    expect($second->exists)->toBeTrue();

    // Y el orden sigue siendo determinístico: desempata por código.
    expect(Area::where('plant_id', $this->plant->id)->ordered()->pluck('code')->all())
        ->toBe(['AAA-01', 'EXT-01']);
});

// ── M6 — un repuesto, una identidad ──────────────────────────────────────────

it('freezes the part code from the inventory master when the row is linked', function (): void {
    $sparePart = SparePart::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'RD-0417',
        'name' => 'Rodamiento 6205',
    ]);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    $part = WorkOrderPart::create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $workOrder->id,
        'spare_part_id' => $sparePart->id,
        // El técnico escribió otra cosa a mano. El maestro manda.
        'part_code' => 'rodamiento grande',
        'description' => 'Rodamiento del reductor',
        'quantity' => 2,
    ]);

    expect($part->refresh()->part_code)->toBe('RD-0417');

    // Y si mañana renombran el repuesto, la OT sigue diciendo lo que el almacén decía
    // el día que salió: es un snapshot, no un espejo.
    $sparePart->update(['code' => 'RD-9999']);

    expect($part->refresh()->part_code)->toBe('RD-0417');
});

it('keeps free text for the part that never lived in the master', function (): void {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    // El repuesto que se compró en la ferretería del pueblo un domingo. Existe.
    $part = WorkOrderPart::create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $workOrder->id,
        'part_code' => 'tornillo 1/2 galvanizado',
        'description' => 'Tornillo 1/2 galvanizado',
        'quantity' => 4,
    ]);

    expect($part->refresh()->part_code)->toBe('tornillo 1/2 galvanizado')
        ->and($part->spare_part_id)->toBeNull();
});

it('lets a part live on its description alone', function (): void {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    // Sin código y sin enlace, pero con nombre. Obligar a inventar un código para un
    // repuesto que no está en el maestro sería obligar a inventar un dato.
    $part = WorkOrderPart::create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $workOrder->id,
        'description' => 'Tuerca M10',
        'quantity' => 1,
    ]);

    expect($part->refresh()->part_code)->toBeNull()
        ->and($part->description)->toBe('Tuerca M10');
});
