<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * El «Tipo I» del cliente convive con la causa física, no la reemplaza.
 *
 * Su Tipo I dice **quién paró la línea**; nuestra categoría dice **qué se rompió**.
 * En su planilla de junio hay 88 fallas mecánicas y eléctricas marcadas Tipo I
 * «Operativa», y su MTBF —que filtra por Tipo I— las excluye todas. Guardar los dos
 * datos es lo que permite reproducir su informe y, al mismo tiempo, poder enseñarle
 * el hueco con los paros en la mano.
 */
beforeEach(function (): void {
    $this->downtime = app(DowntimeService::class);
    $this->kpis = app(PlantKpiService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create();
    $this->from = Carbon::parse('2026-06-01');
    $this->to = Carbon::parse('2026-06-30 23:59:59');
});

/** Un paro consecutivo al anterior, para que no se pisen. */
function attributedStop(StoppageCategory $category, ?ReportedStoppageType $reportedType, float $hours = 2.0): void
{
    static $cursor = null;

    $cursor ??= Carbon::parse('2026-06-02 06:00:00');
    $startedAt = $cursor->copy();
    $cursor = $startedAt->copy()->addMinutes((int) round($hours * 60));

    test()->downtime->register(array_filter([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'stoppage_category' => $category,
        'reported_type' => $reportedType?->value,
        'started_at' => $startedAt,
        'ended_at' => $cursor,
    ], fn ($value): bool => $value !== null), test()->actor);
}

// ── Se guardan los dos ───────────────────────────────────────────────────────

it('keeps the plant own Tipo I exactly as they wrote it, even when it contradicts the cause', function (): void {
    // El caso real: falla mecánica que la planilla marcó «Operativa».
    attributedStop(StoppageCategory::Mechanical, ReportedStoppageType::Operational);

    $event = EquipmentDowntimeEvent::withoutGlobalScopes()->first();

    // No se corrige el dato del cliente: se guarda, y la contradicción queda visible.
    expect($event->reported_type)->toBe(ReportedStoppageType::Operational)
        ->and($event->stoppage_category)->toBe(StoppageCategory::Mechanical);
});

it('infers the Tipo I when nobody declared one', function (): void {
    // Un supervisor de Fronda que nunca oyó hablar del Tipo I no tiene que inventarlo.
    attributedStop(StoppageCategory::Electrical, null);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->first()->reported_type)
        ->toBe(ReportedStoppageType::Maintenance);
});

// ── El hueco ─────────────────────────────────────────────────────────────────

it('shows the failures the plant does not charge to itself', function (): void {
    ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-06-02',
        'programmed_hours' => 22,
    ]);

    // Como en su planilla: una falla reconocida y dos escondidas en «Operativa».
    attributedStop(StoppageCategory::Mechanical, ReportedStoppageType::Maintenance);
    attributedStop(StoppageCategory::Mechanical, ReportedStoppageType::Operational);
    attributedStop(StoppageCategory::Electrical, ReportedStoppageType::Operational);
    // Falta de fruta: esta sí es de operaciones, y no es una falla.
    attributedStop(StoppageCategory::RawMaterial, ReportedStoppageType::Operational);

    $gap = $this->kpis->failureAttributionGap($this->plant, $this->from, $this->to);

    expect($gap['reported_failure_count'])->toBe(1)
        ->and($gap['actual_failure_count'])->toBe(3)
        ->and($gap['unattributed_failure_count'])->toBe(2)
        // Su MTBF sale tres veces mejor que el real. Ese es el punto.
        ->and($gap['reported_mtbf_hours'])->toBeGreaterThan($gap['actual_mtbf_hours']);
});

it('reports no MTBF at all when there were no failures to divide by', function (): void {
    attributedStop(StoppageCategory::RawMaterial, ReportedStoppageType::Operational);

    $gap = $this->kpis->failureAttributionGap($this->plant, $this->from, $this->to);

    // Sin fallas no hay MTBF. Inventar uno sería peor que no tenerlo.
    expect($gap['actual_failure_count'])->toBe(0)
        ->and($gap['actual_mtbf_hours'])->toBeNull()
        ->and($gap['reported_mtbf_hours'])->toBeNull();
});

it('does not report a negative gap when the plant is stricter than we are', function (): void {
    // Si la planta se cobra más fallas de las que la causa física respalda, el hueco
    // es cero, no un número negativo que nadie sabría leer.
    attributedStop(StoppageCategory::Operational, ReportedStoppageType::Maintenance);

    expect($this->kpis->failureAttributionGap($this->plant, $this->from, $this->to)['unattributed_failure_count'])
        ->toBe(0);
});

it('keeps the attribution gap inside the tenant', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $this->downtime->register([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'reported_type' => ReportedStoppageType::Operational->value,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ], $this->actor);

    expect($this->kpis->failureAttributionGap($this->plant, $this->from, $this->to)['actual_failure_count'])
        ->toBe(0);
});
