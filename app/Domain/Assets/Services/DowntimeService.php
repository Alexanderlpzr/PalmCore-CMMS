<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\User;
use Carbon\CarbonInterface;
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

            $this->assertNoOverlappingOpenEvent($attributes);

            return EquipmentDowntimeEvent::create([
                ...$attributes,
                'ended_at' => null,
                'duration_minutes' => null,
            ]);
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
            $this->assertNoOverlappingOpenEvent($attributes);

            return EquipmentDowntimeEvent::create([
                ...$attributes,
                'ended_at' => $endedAt,
                'duration_minutes' => (int) round($attributes['started_at']->diffInMinutes($endedAt)),
            ]);
        });
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
     * @return array<string, float> category value => hours lost
     */
    public function lostHoursByCategory(
        string $plantId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        return EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $plantId)
            ->productionAffecting()
            ->whereNotNull('stoppage_category')
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw(
                'stoppage_category,
                 COALESCE(SUM(COALESCE(duration_minutes,
                     EXTRACT(EPOCH FROM (ended_at - started_at)) / 60)), 0) AS minutes'
            )
            ->groupBy('stoppage_category')
            ->pluck('minutes', 'stoppage_category')
            ->map(fn ($minutes): float => round((float) $minutes / 60, 2))
            ->all();
    }

    // ── Internals ─────────────────────────────────────────────────────────────

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
        $category = $data['stoppage_category'] instanceof StoppageCategory
            ? $data['stoppage_category']
            : StoppageCategory::from($data['stoppage_category']);

        $equipment = isset($data['equipment_id'])
            ? Equipment::withoutGlobalScopes()->find($data['equipment_id'])
            : null;

        $plantId = $data['plant_id'] ?? $equipment?->plant_id;

        if ($plantId === null && $equipment === null) {
            throw new BusinessRuleException(
                'Un paro debe indicar el equipo afectado o, si es un paro de planta, la planta.'
            );
        }

        return [
            'tenant_id' => $data['tenant_id'],
            'plant_id' => $plantId,
            'equipment_id' => $equipment?->id,
            'started_at' => isset($data['started_at']) ? Carbon::parse($data['started_at']) : now(),
            'cause_type' => $category->isPlanned()
                ? EquipmentDowntimeCauseType::Preventive->value
                : $this->causeTypeFor($category),
            'stoppage_category' => $category->value,
            'stoppage_cause' => $data['stoppage_cause'] ?? null,
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
     * The same asset cannot be down twice at once. Without this, a forgotten open
     * paro silently doubles every availability figure that reads it.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws BusinessRuleException
     */
    private function assertNoOverlappingOpenEvent(array $attributes): void
    {
        $query = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('tenant_id', $attributes['tenant_id'])
            ->ongoing();

        if ($attributes['equipment_id'] !== null) {
            $query->where('equipment_id', $attributes['equipment_id']);
        } else {
            // Plant-wide paro: only another plant-wide paro conflicts with it.
            $query->whereNull('equipment_id')->where('plant_id', $attributes['plant_id']);
        }

        if ($query->exists()) {
            throw new BusinessRuleException(
                $attributes['equipment_id'] !== null
                    ? 'Este equipo ya tiene un paro abierto. Ciérrelo antes de registrar otro.'
                    : 'La planta ya tiene un paro abierto. Ciérrelo antes de registrar otro.'
            );
        }
    }
}
