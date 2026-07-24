<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Domain\Assets\Enums\StoppageReason;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paros as first-class facts.
 *
 * A stoppage is something that happened to the plant. Most of them (falta de
 * fruta, corte de energía, atasco en prensa) never justify an OT, and the ones
 * that do are still *the paro*, not the OT. This service is the only way a paro
 * gets recorded outside the work-order flow, and the place where the plant's
 * Tipo I / Tipo II taxonomy is enforced.
 */
class DowntimeService
{
    public function __construct(private readonly LostHoursCalculator $lostHours) {}

    /**
     * Open a paro that is happening right now (or started at a known moment) and
     * has not ended yet. The line is down while this record has no `ended_at`.
     *
     * @param  array{
     *     tenant_id: string,
     *     plant_id?: ?string,
     *     equipment_id?: ?string,
     *     stoppage_category: StoppageCategory|string,
     *     stoppage_cause?: ?string,
     *     started_at?: CarbonInterface|string|null,
     *     affects_production?: bool,
     *     notes?: ?string,
     *     reported_by?: ?string,
     * }  $data
     *
     * @throws BusinessRuleException
     */
    public function start(array $data, User $registeredBy): EquipmentDowntimeEvent
    {
        return DB::transaction(function () use ($data, $registeredBy): EquipmentDowntimeEvent {
            $attributes = $this->normalize($data, $registeredBy);

            $this->assertNoOverlap($attributes);

            $event = EquipmentDowntimeEvent::create([
                ...$attributes,
                'ended_at' => null,
                'duration_minutes' => null,
            ]);

            $this->touchLastFailure($event);

            return $event;
        });
    }

    /**
     * Close an open paro. The line is back up.
     *
     * @throws BusinessRuleException
     */
    public function end(
        EquipmentDowntimeEvent $event,
        CarbonInterface|string|null $endedAt = null,
        ?string $notes = null,
    ): EquipmentDowntimeEvent {
        if (! $event->isOngoing()) {
            throw new BusinessRuleException(
                'Este paro ya fue cerrado.',
                detail: "downtime_event:{$event->id}",
            );
        }

        $endedAt = $endedAt ? Carbon::parse($endedAt) : now();

        if ($endedAt->lt($event->started_at)) {
            throw new BusinessRuleException(
                'Un paro no puede terminar antes de haber empezado.',
                detail: "downtime_event:{$event->id}",
            );
        }

        // Closing at a time that reaches over a paro registered later would make the
        // two share hours. Rare, but it is exactly how the log gets corrupted: the
        // supervisor closes yesterday's forgotten paro with today's timestamp.
        $this->assertNoOverlap(
            [
                'tenant_id' => $event->tenant_id,
                'plant_id' => $event->plant_id,
                'equipment_id' => $event->equipment_id,
                'started_at' => $event->started_at,
            ],
            endedAt: $endedAt,
            ignoreId: $event->id,
        );

        $event->update([
            'ended_at' => $endedAt,
            'duration_minutes' => (int) round($event->started_at->diffInMinutes($endedAt)),
            'notes' => $notes ?? $event->notes,
        ]);

        return $event->refresh();
    }

    /**
     * Record a paro that already began and ended — the normal case when the
     * supervisor types up the shift log at the end of the turno.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException
     */
    public function register(array $data, User $registeredBy): EquipmentDowntimeEvent
    {
        $attributes = $this->normalize($data, $registeredBy);

        $endedAt = isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : null;

        if ($endedAt === null) {
            return $this->start($data, $registeredBy);
        }

        if ($endedAt->lt($attributes['started_at'])) {
            throw new BusinessRuleException('Un paro no puede terminar antes de haber empezado.');
        }

        return DB::transaction(function () use ($attributes, $endedAt): EquipmentDowntimeEvent {
            $this->assertNoOverlap($attributes, $endedAt);

            $event = EquipmentDowntimeEvent::create([
                ...$attributes,
                'ended_at' => $endedAt,
                'duration_minutes' => (int) round($attributes['started_at']->diffInMinutes($endedAt)),
            ]);

            $this->touchLastFailure($event);

            return $event;
        });
    }

    /**
     * A4 — afinar el Tipo I cuando por fin se sabe qué se rompió.
     *
     * Una OT correctiva abre su paro en «otro»: al arrancar, nadie sabe todavía si
     * el problema era mecánico o eléctrico, y adivinarlo sería inventar el dato. El
     * técnico lo afina al diagnosticar, y el Pareto deja de tener una montaña de
     * «otro» que no le sirve a nadie.
     *
     * Dos cosas que este método no hace:
     *
     *  - No toca `reported_type`. El Tipo I del cliente es suyo; nuestro diagnóstico
     *    va al lado, no encima. La diferencia entre los dos es el hallazgo.
     *  - No convierte una falla en paro programado ni al revés. Si «programado» fuera
     *    un diagnóstico posible, cualquier falla incómoda podría salirse del MTBF con
     *    un clic. Eso viene del origen del paro, no de destapar la máquina.
     *
     * @throws BusinessRuleException
     */
    public function reclassify(
        EquipmentDowntimeEvent $event,
        StoppageCategory $category,
        ?string $cause = null,
    ): EquipmentDowntimeEvent {
        if ($category->isPlanned()) {
            throw new BusinessRuleException(
                'Un diagnóstico no puede convertir un paro en «Programado»: eso lo define el origen del paro, no el hallazgo.',
                detail: "downtime_event:{$event->id}",
            );
        }

        if ($event->was_planned) {
            throw new BusinessRuleException(
                'Este paro es programado. Su Tipo I viene de la intervención que lo originó y no se reclasifica.',
                detail: "downtime_event:{$event->id}",
            );
        }

        $event->update([
            'stoppage_category' => $category->value,
            'cause_type' => $this->causeTypeFor($category),
            'stoppage_cause' => $cause ?? $event->stoppage_cause,
        ]);

        $event->refresh();
        $this->touchLastFailure($event);

        return $event;
    }

    /**
     * A5 — el jefe de turno firma las horas.
     *
     * Producción confirma que la planta estuvo abajo lo que mantenimiento dice que
     * estuvo abajo. Sin esto, el mismo que queda mal en la foto es el único que
     * escribe el número.
     *
     * @throws BusinessRuleException
     */
    public function confirm(
        EquipmentDowntimeEvent $event,
        User $confirmedBy,
        ?string $notes = null,
    ): EquipmentDowntimeEvent {
        $this->assertSignable($event);

        $event->update([
            'confirmation_status' => StoppageConfirmationStatus::Confirmed->value,
            'confirmed_by' => $confirmedBy->id,
            'confirmed_at' => now(),
            'confirmation_notes' => $notes,
        ]);

        return $event->refresh();
    }

    /**
     * Producción no está de acuerdo con las horas. El paro no se borra ni se corrige
     * a espaldas de nadie: queda marcado, con el motivo escrito, y sigue contando en
     * los indicadores hasta que las dos áreas se sienten a mirarlo. Un paro en disputa
     * que desaparece del reporte es exactamente la mentira que este campo evita.
     *
     * @throws BusinessRuleException
     */
    public function dispute(
        EquipmentDowntimeEvent $event,
        User $disputedBy,
        string $reason,
    ): EquipmentDowntimeEvent {
        $this->assertSignable($event);

        if (trim($reason) === '') {
            throw new BusinessRuleException('Para disputar un paro hay que decir por qué.');
        }

        $event->update([
            'confirmation_status' => StoppageConfirmationStatus::Disputed->value,
            'confirmed_by' => $disputedBy->id,
            'confirmed_at' => now(),
            'confirmation_notes' => $reason,
        ]);

        return $event->refresh();
    }

    /**
     * Cuánto del mes va al informe sin que producción lo haya firmado. No es un KPI:
     * es la medida de cuánto del número hay que creerle a una sola de las dos partes.
     *
     * @return array{events: int, hours: float}
     */
    public function pendingConfirmation(
        string $plantId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $pending = fn (): Builder => EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $plantId)
            ->awaitingConfirmation();

        return [
            'events' => $pending()
                ->where('started_at', '<', $to)
                ->where('ended_at', '>', $from)
                ->count(),
            'hours' => $this->lostHours->unionHours($pending(), $from, $to),
        ];
    }

    /** The paro currently keeping this equipment down, if any. */
    public function ongoingFor(Equipment $equipment): ?EquipmentDowntimeEvent
    {
        return EquipmentDowntimeEvent::where('equipment_id', $equipment->id)
            ->ongoing()
            ->latest('started_at')
            ->first();
    }

    /** @return Collection<int, EquipmentDowntimeEvent> */
    public function ongoingForPlant(Plant $plant): Collection
    {
        return EquipmentDowntimeEvent::where('plant_id', $plant->id)
            ->ongoing()
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Production hours lost in a window, split by Tipo I. This is the number the
     * plant argues about every Monday: how much of the month we lost, and to whom
     * it belongs.
     *
     * Each Tipo I is the union of its own paros, clipped to the window, so a
     * category cannot charge the plant twice for the same hour and a paro that
     * crosses midnight of the 1st is split between the two months. Categories are
     * not additive against the plant total: two overlapping paros of *different*
     * Tipo I each own the hour they shared.
     *
     * @return array<string, float> category value => hours lost
     */
    public function lostHoursByCategory(
        string $plantId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $base = fn (): Builder => EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $plantId)
            ->productionAffecting()
            ->whereNotNull('stoppage_category');

        $categories = $base()
            ->where('started_at', '<', $to)
            ->where('ended_at', '>', $from)
            ->distinct()
            ->pluck('stoppage_category');

        $hours = [];

        foreach ($categories as $category) {
            $value = $category instanceof StoppageCategory ? $category->value : (string) $category;

            $hours[$value] = $this->lostHours->unionHours(
                $base()->where('stoppage_category', $value),
                $from,
                $to,
            );
        }

        arsort($hours);

        return $hours;
    }

    /**
     * A6 — horas perdidas por equipo, peor primero. La hoja «Análisis PNP por
     * equipo» del cliente, que hoy vive en Excel.
     *
     * Un Pareto de verdad: cada equipo aporta la unión de sus propios paros
     * (recortados a la ventana) y el acumulado dice dónde está el 80 %. Los paros
     * de planta —falta de fruta, corte de energía— no son de ningún equipo y se
     * reportan aparte en vez de repartirse entre máquinas que no fallaron.
     *
     * @return array{
     *     equipment: array<int, array{equipment_id: ?string, code: ?string, name: string, hours: float, events: int, cumulative_percentage: float}>,
     *     plant_wide_hours: float,
     *     total_hours: float,
     * }
     */
    public function lostHoursByEquipment(
        string $plantId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $base = fn (): Builder => EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $plantId)
            ->productionAffecting();

        $touching = fn (Builder $query): Builder => $query
            ->where('started_at', '<', $to)
            ->where('ended_at', '>', $from);

        $counts = $touching($base())
            ->whereNotNull('equipment_id')
            ->selectRaw('equipment_id, COUNT(*) AS events')
            ->groupBy('equipment_id')
            ->pluck('events', 'equipment_id');

        $equipment = Equipment::withoutGlobalScopes()
            ->whereIn('id', $counts->keys())
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $rows = [];

        foreach ($counts as $equipmentId => $events) {
            $rows[] = [
                'equipment_id' => $equipmentId,
                'code' => $equipment[$equipmentId]->code ?? null,
                'name' => $equipment[$equipmentId]->name ?? 'Equipo desconocido',
                'hours' => $this->lostHours->unionHours(
                    $base()->where('equipment_id', $equipmentId),
                    $from,
                    $to,
                ),
                'events' => (int) $events,
                'cumulative_percentage' => 0.0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['hours'] <=> $a['hours']);

        $equipmentHours = array_sum(array_column($rows, 'hours'));
        $running = 0.0;

        foreach ($rows as $index => $row) {
            $running += $row['hours'];
            // Sin denominador no hay porcentaje: un Pareto de cero horas no es 100 %.
            $rows[$index]['cumulative_percentage'] = $equipmentHours > 0
                ? round($running / $equipmentHours * 100, 2)
                : 0.0;
        }

        $plantWide = $this->lostHours->unionHours(
            $base()->whereNull('equipment_id'),
            $from,
            $to,
        );

        return [
            'equipment' => $rows,
            'plant_wide_hours' => $plantWide,
            // La planta tiene un solo reloj: el total no es la suma de los equipos
            // (dos máquinas paradas a la vez cuestan una hora, no dos).
            'total_hours' => $this->lostHours->unionHours($base(), $from, $to),
        ];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Un paro de falla de mantenimiento (mecánica/eléctrica/instrumentación) es la
     * «última falla» del equipo — el dato que alimentan el MTBF y las alertas de
     * confiabilidad. Antes lo marcaba la OT; ahora que los paros son manuales, lo
     * marca su registro. Solo avanza: un paro cargado con fecha vieja no pisa una
     * falla más reciente.
     */
    private function touchLastFailure(EquipmentDowntimeEvent $event): void
    {
        if ($event->equipment_id === null) {
            return;
        }

        $category = $event->stoppage_category;

        if ($category === null || ! $category->isMaintenanceResponsibility() || $category->isPlanned()) {
            return;
        }

        Equipment::withoutGlobalScopes()
            ->whereKey($event->equipment_id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('last_failure_at')
                ->orWhere('last_failure_at', '<', $event->started_at))
            ->update(['last_failure_at' => $event->started_at]);
    }

    /**
     * Solo se firman horas que existen y que le costaron producción a la planta: un
     * paro abierto todavía no tiene duración, y una falla que no detuvo la línea no
     * le quitó tiempo a producción. Firmar dos veces tampoco: la firma es un hecho
     * fechado, no un campo editable.
     *
     * @throws BusinessRuleException
     */
    private function assertSignable(EquipmentDowntimeEvent $event): void
    {
        if ($event->isOngoing()) {
            throw new BusinessRuleException(
                'Este paro sigue abierto. Producción firma las horas cuando la planta vuelve a arrancar.',
                detail: "downtime_event:{$event->id}",
            );
        }

        if (! $event->affects_production) {
            throw new BusinessRuleException(
                'Este evento no le restó horas a la producción, así que no hay nada que producción deba firmar.',
                detail: "downtime_event:{$event->id}",
            );
        }

        if ($event->isSignedByProduction()) {
            throw new BusinessRuleException(
                'Este paro ya fue firmado por producción el '
                    .$event->confirmed_at->format('d/m/Y H:i').'.',
                detail: "downtime_event:{$event->id}",
            );
        }
    }

    /**
     * Fill in what the caller can be expected to leave out: the plant behind the
     * equipment, and the planned/affects-production flags implied by the Tipo I.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws BusinessRuleException
     */
    private function normalize(array $data, User $registeredBy): array
    {
        // El Tipo II (causa concreta), si viene, define la categoría física y el
        // Tipo I. Si no, se cae al flujo anterior por categoría explícita.
        $reason = isset($data['stoppage_reason']) && $data['stoppage_reason'] !== null
            ? ($data['stoppage_reason'] instanceof StoppageReason
                ? $data['stoppage_reason']
                : StoppageReason::from($data['stoppage_reason']))
            : null;

        $category = $reason?->category()
            ?? ($data['stoppage_category'] instanceof StoppageCategory
                ? $data['stoppage_category']
                : StoppageCategory::from($data['stoppage_category']));

        $section = isset($data['section']) && $data['section'] !== null
            ? ($data['section'] instanceof PlantSection
                ? $data['section']
                : PlantSection::from($data['section']))
            : null;

        $tenantId = $data['tenant_id'];

        // Resolved *within the tenant*, never globally: the id arrives from the
        // request, and `exists:equipment,id` does not know about tenants. Without
        // this filter a caller could hang a paro on another company's equipment.
        $equipment = isset($data['equipment_id'])
            ? Equipment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($data['equipment_id'])
            : null;

        if (isset($data['equipment_id']) && $equipment === null) {
            throw new BusinessRuleException('El equipo indicado no existe en esta organización.');
        }

        $plantId = $data['plant_id'] ?? $equipment?->plant_id;

        if ($plantId === null && $equipment === null) {
            throw new BusinessRuleException(
                'Un paro debe indicar el equipo afectado o, si es un paro de planta, la planta.'
            );
        }

        if ($plantId !== null && ! Plant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($plantId)
            ->exists()
        ) {
            throw new BusinessRuleException('La planta indicada no existe en esta organización.');
        }

        return [
            'tenant_id' => $tenantId,
            'plant_id' => $plantId,
            'equipment_id' => $equipment?->id,
            'section' => $section?->value,
            'started_at' => isset($data['started_at']) ? Carbon::parse($data['started_at']) : now(),
            'cause_type' => $category->isPlanned()
                ? EquipmentDowntimeCauseType::Preventive->value
                : $this->causeTypeFor($category),
            'stoppage_category' => $category->value,
            'stoppage_reason' => $reason?->value,
            'stoppage_cause' => $data['stoppage_cause'] ?? null,
            // El Tipo I del cliente. Si eligió un Tipo II, sale de él; si lo declara
            // aparte, se respeta; si no, se deduce de la causa física. Cuando el dato
            // viene de su planilla se guarda tal como ellos lo escribieron, aunque
            // contradiga la causa — esa contradicción es el dato.
            'reported_type' => isset($data['reported_type']) && $data['reported_type'] !== null
                ? ($data['reported_type'] instanceof ReportedStoppageType
                    ? $data['reported_type']->value
                    : ReportedStoppageType::from($data['reported_type'])->value)
                : ($reason?->reportedType()->value ?? ReportedStoppageType::inferredFrom($category)->value),
            'was_planned' => $category->isPlanned(),
            'affects_production' => $data['affects_production'] ?? true,
            'source' => 'manual',
            'notes' => $data['notes'] ?? null,
            'reported_by' => $data['reported_by'] ?? null,
            'registered_by' => $registeredBy->id,
        ];
    }

    /**
     * Keep the legacy `cause_type` coherent so the existing MTBF/MTTR and
     * availability queries — which only know about cause_type — keep working
     * while the Tipo I taxonomy carries the real meaning.
     */
    private function causeTypeFor(StoppageCategory $category): string
    {
        return match ($category) {
            StoppageCategory::Mechanical,
            StoppageCategory::Electrical,
            StoppageCategory::Instrumentation => EquipmentDowntimeCauseType::Corrective->value,
            StoppageCategory::RawMaterial,
            StoppageCategory::Utilities,
            StoppageCategory::External => EquipmentDowntimeCauseType::External->value,
            default => EquipmentDowntimeCauseType::Other->value,
        };
    }

    /**
     * The same asset cannot be down twice at once — and that includes the past.
     *
     * Checking only *open* paros (which is all this used to do) let two closed
     * paros of the same equipment sit on top of each other, and every hour they
     * shared got billed to the plant twice. An open paro is treated as running to
     * infinity: nothing may be recorded after it until somebody closes it.
     *
     * The database enforces the same rule with an exclusion constraint. This check
     * exists to say it in Spanish before Postgres says it in SQL.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws BusinessRuleException
     */
    private function assertNoOverlap(
        array $attributes,
        ?CarbonInterface $endedAt = null,
        ?string $ignoreId = null,
    ): void {
        $query = $this->siblingsOf($attributes)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            // An existing paro clashes unless it ended before this one began…
            ->where(fn (Builder $q) => $q
                ->whereNull('ended_at')
                ->orWhere('ended_at', '>', $attributes['started_at']));

        // …or began after this one ended. An open paro (no end) swallows everything after it.
        if ($endedAt !== null) {
            $query->where('started_at', '<', $endedAt);
        }

        $conflict = $query->orderBy('started_at')->first();

        if ($conflict === null) {
            return;
        }

        $subject = $attributes['equipment_id'] !== null ? 'Este equipo' : 'La planta';

        throw new BusinessRuleException(
            $conflict->isOngoing()
                ? "{$subject} ya tiene un paro abierto desde el "
                    .$conflict->started_at->format('d/m/Y H:i').'. Ciérrelo antes de registrar otro.'
                : "{$subject} ya tiene un paro registrado que se cruza con este ("
                    .$conflict->started_at->format('d/m/Y H:i').' — '
                    .$conflict->ended_at->format('d/m/Y H:i').'). Las horas perdidas se contarían dos veces.',
            detail: "downtime_event:{$conflict->id}",
        );
    }

    /**
     * The paros that compete for the same clock: those of the same equipment, or —
     * for a paro de planta — the other plant-wide paros. An equipment paro and a
     * plant-wide paro may legitimately coexist (a power cut while a pump is being
     * repaired); the union in {@see LostHoursCalculator} keeps them from double
     * counting.
     *
     * @param  array<string, mixed>  $attributes
     * @return Builder<EquipmentDowntimeEvent>
     */
    private function siblingsOf(array $attributes): Builder
    {
        $query = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('tenant_id', $attributes['tenant_id']);

        return $attributes['equipment_id'] !== null
            ? $query->where('equipment_id', $attributes['equipment_id'])
            : $query->whereNull('equipment_id')->where('plant_id', $attributes['plant_id']);
    }
}
