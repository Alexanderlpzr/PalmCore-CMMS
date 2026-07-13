<?php

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Domain\Reports\Services\LostHoursPdfService;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A6 — horas perdidas por equipo, con Pareto. La hoja «Análisis PNP por equipo».
 *
 * El reporte existe para responder una sola pregunta: ¿dónde está el 80 %? Si no
 * la responde, el jefe de mantenimiento sigue con su Excel.
 */
beforeEach(function (): void {
    $this->service = app(DowntimeService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create();
    $this->from = Carbon::parse('2026-06-01');
    $this->to = Carbon::parse('2026-06-30 23:59:59');
});

function machine(string $code): Equipment
{
    return Equipment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'code' => $code,
    ]);
}

/** Un paro de `$hours` horas para ese equipo, empezando el día `$day` a las 08:00. */
function stoppage(?Equipment $equipment, int $day, float $hours, StoppageCategory $category = StoppageCategory::Mechanical): void
{
    $startedAt = Carbon::parse('2026-06-01 08:00:00')->addDays($day - 1);

    test()->service->register([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => $equipment?->id,
        'stoppage_category' => $category,
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addMinutes((int) round($hours * 60)),
    ], test()->actor);
}

function pareto(): array
{
    return test()->service->lostHoursByEquipment(test()->plant->id, test()->from, test()->to);
}

// ── El Pareto ────────────────────────────────────────────────────────────────

it('ranks the equipment by the hours it cost, worst first', function (): void {
    $prensa = machine('A05EXT.05');
    $caldera = machine('A12CAL.01');

    stoppage($prensa, 1, 6.0);
    stoppage($prensa, 2, 4.0);
    stoppage($caldera, 3, 2.0);

    $rows = pareto()['equipment'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['code'])->toBe('A05EXT.05')
        ->and($rows[0]['hours'])->toBe(10.0)
        ->and($rows[0]['events'])->toBe(2)
        ->and($rows[1]['code'])->toBe('A12CAL.01')
        ->and($rows[1]['hours'])->toBe(2.0);
});

it('says where the 80 percent is', function (): void {
    $prensa = machine('A05EXT.05');
    $caldera = machine('A12CAL.01');

    stoppage($prensa, 1, 8.0);
    stoppage($caldera, 2, 2.0);

    $rows = pareto()['equipment'];

    expect($rows[0]['cumulative_percentage'])->toBe(80.0)
        ->and($rows[1]['cumulative_percentage'])->toBe(100.0);
});

it('does not blame the machines for a paro that was not theirs', function (): void {
    $prensa = machine('A05EXT.05');

    stoppage($prensa, 1, 4.0);
    // Falta de fruta: la planta se paró, pero ninguna máquina falló.
    stoppage(null, 2, 10.0, StoppageCategory::RawMaterial);

    $result = pareto();

    // Repartir esas 10 h entre los equipos sería inventar fallas que no ocurrieron.
    expect($result['equipment'])->toHaveCount(1)
        ->and($result['equipment'][0]['hours'])->toBe(4.0)
        ->and($result['plant_wide_hours'])->toBe(10.0)
        ->and($result['total_hours'])->toBe(14.0);
});

it('does not let the plant total double count two machines stopped at once', function (): void {
    $prensa = machine('A05EXT.05');
    $caldera = machine('A12CAL.01');

    // Prensa 08:00–12:00 y caldera 08:00–14:00 el mismo día.
    stoppage($prensa, 1, 4.0);
    stoppage($caldera, 1, 6.0);

    $result = pareto();

    // Cada equipo responde por sus horas, pero la planta perdió 6, no 10.
    expect(array_sum(array_column($result['equipment'], 'hours')))->toBe(10.0)
        ->and($result['total_hours'])->toBe(6.0);
});

it('is honest with an empty period instead of reporting 100 percent of nothing', function (): void {
    $result = pareto();

    expect($result['equipment'])->toBeEmpty()
        ->and($result['total_hours'])->toBe(0.0);
});

it('only counts the part of the window that belongs to it', function (): void {
    $prensa = machine('A05EXT.05');

    // Paro de mayo: no es de junio.
    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $prensa->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => '2026-05-20 08:00:00',
        'ended_at' => '2026-05-20 18:00:00',
    ], $this->actor);

    stoppage($prensa, 5, 3.0);

    expect(pareto()['equipment'][0]['hours'])->toBe(3.0);
});

// ── Multi-tenant ─────────────────────────────────────────────────────────────

it('never puts another company equipment in the Pareto', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);
    $otherEquipment = Equipment::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
    ]);

    $this->service->register([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'equipment_id' => $otherEquipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 20:00:00',
    ], $this->actor);

    expect(pareto()['equipment'])->toBeEmpty();
});

// ── El papel ─────────────────────────────────────────────────────────────────

it('exports the report as a PDF', function (): void {
    stoppage(machine('A05EXT.05'), 1, 6.0);

    $service = app(LostHoursPdfService::class);

    expect($service->generate($this->plant, $this->from, $this->to))->toStartWith('%PDF')
        ->and($service->filename($this->plant, $this->from))->toBe('HORAS-PERDIDAS-2026-06.pdf');
});

it('streams the report to the SPA, scoped to the tenant', function (): void {
    $stranger = Plant::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $token = $user->createToken('report', ['reports.read']);
    $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->get("/api/v1/reports/lost-hours/{$this->plant->id}", $headers)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // La planta de otra empresa no existe para este token.
    $this->get("/api/v1/reports/lost-hours/{$stranger->id}", $headers)->assertNotFound();
});
