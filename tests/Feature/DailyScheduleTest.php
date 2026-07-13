<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\DailyScheduleService;
use App\Domain\Reports\Services\DailySchedulePdfService;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTechnician;
use Illuminate\Support\Carbon;

/**
 * F3 — el programa impreso del día.
 *
 * El criterio no es «¿genera un PDF?», es «¿el planificador deja de imprimir su
 * Excel?». Si algo que él sí anota en su hoja no sale aquí, sigue con la hoja.
 */
beforeEach(function (): void {
    $this->service = app(DailyScheduleService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->today = Carbon::parse('2026-07-14 06:00:00');
});

/** @param  array<string, mixed>  $overrides */
function scheduledWorkOrder(array $overrides = []): WorkOrder
{
    return WorkOrder::factory()->create([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => test()->equipment->id,
        'status' => WorkOrderStatus::Planned,
        'planned_start_at' => test()->today,
        ...$overrides,
    ]);
}

function scheduleForToday(): array
{
    return test()->service->forDay(test()->tenant->id, test()->today);
}

// ── Lo que el planificador tiene que ver ─────────────────────────────────────

it('puts today planned work on the sheet', function (): void {
    scheduledWorkOrder();

    expect(scheduleForToday()['work_orders'])->toHaveCount(1);
});

it('keeps work that is already running on the sheet', function (): void {
    // Sin fecha planificada, pero el técnico está encima de ella: le come el día.
    scheduledWorkOrder([
        'status' => WorkOrderStatus::InProgress,
        'planned_start_at' => null,
    ]);

    expect(scheduleForToday()['work_orders'])->toHaveCount(1);
});

it('drags yesterday unfinished work onto today and says it is late', function (): void {
    scheduledWorkOrder(['planned_start_at' => $this->today->copy()->subDays(3)]);

    $schedule = scheduleForToday();

    // Si lo atrasado no sale en el papel, el planificador se hace su propia lista
    // aparte — y ahí muere el CMMS.
    expect($schedule['work_orders'])->toHaveCount(1)
        ->and($schedule['overdue_count'])->toBe(1);
});

it('leaves tomorrow work for tomorrow', function (): void {
    scheduledWorkOrder(['planned_start_at' => $this->today->copy()->addDay()]);

    expect(scheduleForToday()['work_orders'])->toBeEmpty();
});

it('leaves drafts out — a draft is not a programme', function (): void {
    scheduledWorkOrder(['status' => WorkOrderStatus::Draft]);

    expect(scheduleForToday()['work_orders'])->toBeEmpty();
});

it('leaves finished work out', function (): void {
    scheduledWorkOrder(['status' => WorkOrderStatus::Completed]);
    scheduledWorkOrder(['status' => WorkOrderStatus::Closed]);
    scheduledWorkOrder(['status' => WorkOrderStatus::Cancelled]);

    expect(scheduleForToday()['work_orders'])->toBeEmpty();
});

// ── Una hoja por técnico ─────────────────────────────────────────────────────

it('gives each technician his own sheet and puts the unassigned first', function (): void {
    $technician = User::factory()->create(['name' => 'Zacarías Pérez']);

    $assigned = scheduledWorkOrder();
    WorkOrderTechnician::create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $assigned->id,
        'user_id' => $technician->id,
        'role' => 'technician',
    ]);

    scheduledWorkOrder(); // sin técnico

    $groups = scheduleForToday()['groups'];

    // Lo que nadie tiene asignado es la decisión que el planificador toma primero.
    expect($groups)->toHaveCount(2)
        ->and($groups[0]['technician'])->toBeNull()
        ->and($groups[1]['technician'])->toBe('Zacarías Pérez');
});

it('counts the work orders that need the machine stopped', function (): void {
    scheduledWorkOrder(['equipment_stopped' => true]);
    scheduledWorkOrder(['equipment_stopped' => false]);

    expect(scheduleForToday()['stopped_count'])->toBe(1);
});

// ── No inventar ──────────────────────────────────────────────────────────────

it('does not claim zero hours when nobody planned a duration', function (): void {
    scheduledWorkOrder(['planned_labor_hours' => null, 'planned_end_at' => null]);

    // «0 h» para una jornada de trabajo es una mentira; «—» es la verdad.
    expect(scheduleForToday()['planned_hours'])->toBeNull();
});

it('adds up the planned hours it does know', function (): void {
    scheduledWorkOrder(['planned_labor_hours' => 2.5, 'planned_end_at' => null]);
    scheduledWorkOrder(['planned_labor_hours' => 1.5, 'planned_end_at' => null]);

    expect(scheduleForToday()['planned_hours'])->toBe(4.0);
});

// ── Multi-tenant ─────────────────────────────────────────────────────────────

it('never programmes another company work', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'equipment_id' => Equipment::factory()->create([
            'tenant_id' => $other->id,
            'plant_id' => $otherPlant->id,
        ])->id,
        'status' => WorkOrderStatus::Planned,
        'planned_start_at' => $this->today,
    ]);

    expect(scheduleForToday()['work_orders'])->toBeEmpty();
});

it('can be narrowed to one plant', function (): void {
    $otherPlant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    scheduledWorkOrder();
    scheduledWorkOrder([
        'plant_id' => $otherPlant->id,
        'equipment_id' => Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $otherPlant->id,
        ])->id,
    ]);

    $schedule = $this->service->forDay($this->tenant->id, $this->today, $this->plant->id);

    expect($schedule['work_orders'])->toHaveCount(1)
        ->and($schedule['plant']->id)->toBe($this->plant->id);
});

// ── El papel ─────────────────────────────────────────────────────────────────

it('prints the day even when there is nothing programmed', function (): void {
    $pdf = app(DailySchedulePdfService::class)->generate($this->tenant->id, $this->today);

    // Un programa vacío es una respuesta válida: hoy no hay trabajo.
    expect($pdf)->toStartWith('%PDF');
});

it('streams the programme to the planner from the SPA', function (): void {
    scheduledWorkOrder();

    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('planner', ['reports.read']);
    $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

    $this->get(
        '/api/v1/reports/daily-schedule?date='.$this->today->toDateString(),
        ['Authorization' => 'Bearer '.$token->plainTextToken],
    )
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="PROGRAMA-2026-07-14.pdf"');
});

it('refuses to print another company programme', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('planner', ['reports.read']);
    $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

    // La planta existe, pero no es suya. `exists:plants,id` no lo habría notado.
    $this->get(
        "/api/v1/reports/daily-schedule?plant_id={$otherPlant->id}",
        ['Authorization' => 'Bearer '.$token->plainTextToken],
    )->assertNotFound();
});

it('prints the programme as a PDF', function (): void {
    scheduledWorkOrder(['title' => 'Cambio de rodamiento prensa 2']);

    $service = app(DailySchedulePdfService::class);

    expect($service->generate($this->tenant->id, $this->today))->toStartWith('%PDF')
        ->and($service->filename($this->today))->toBe('PROGRAMA-2026-07-14.pdf');
});
