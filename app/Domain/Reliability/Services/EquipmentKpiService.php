<?php

namespace App\Domain\Reliability\Services;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Reliability\DTOs\EquipmentKpiData;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use App\Models\EquipmentMeterReading;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

class EquipmentKpiService
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Calculate KPIs for the equipment and persist the result.
     * Idempotent: safe to call multiple times; always produces the same row.
     */
    public function recalculate(Equipment $equipment): EquipmentKpi
    {
        $data = $this->calculateForEquipment($equipment);

        return $this->persist($equipment, $data);
    }

    /**
     * Mark an equipment's KPI cache as stale without recalculating.
     * Used by event listeners to flag that a recalculation is needed.
     */
    public function markStale(Equipment|string $equipment): void
    {
        $equipmentId = $equipment instanceof Equipment ? $equipment->id : $equipment;

        EquipmentKpi::withoutGlobalScopes()
            ->where('equipment_id', $equipmentId)
            ->whereNull('deleted_at')
            ->update(['is_stale' => true]);
    }

    /**
     * Pure calculation — queries DB, returns a DTO, touches nothing.
     * Public so tests can verify the math independently of persistence.
     */
    public function calculateForEquipment(Equipment $equipment): EquipmentKpiData
    {
        $periodMonths = $this->resolvePeriodMonths($equipment->tenant_id);
        $periodEnd = CarbonImmutable::today();
        $periodStart = $periodEnd->subMonths($periodMonths)->startOfDay();

        $totalPeriodHours = (float) $periodStart->diffInHours($periodEnd);

        // Guard: period too short to be meaningful (e.g. equipment created today)
        if ($totalPeriodHours < 1.0) {
            return new EquipmentKpiData(
                periodMonths: $periodMonths,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                mtbfHours: null,
                mttrHours: null,
                availabilityPercentage: null,
                unplannedAvailabilityPercentage: null,
                failureCount: 0,
                downtimeHours: 0.0,
                lastFailureAt: null,
            );
        }

        // ── Corrective (unplanned) events ─────────────────────────────────────
        $unplanned = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->selectRaw(
                'COUNT(*) AS failure_count,
                 COALESCE(SUM(COALESCE(duration_minutes,
                     EXTRACT(EPOCH FROM (ended_at - started_at)) / 60
                 )), 0) AS total_minutes,
                 MAX(started_at) AS last_failure_at'
            )
            ->where('equipment_id', $equipment->id)
            ->where('was_planned', false)
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', $periodStart)
            ->first();

        $failureCount = (int) ($unplanned->failure_count ?? 0);
        $unplannedDowntimeHours = round((float) ($unplanned->total_minutes ?? 0) / 60, 4);
        $lastFailureAt = $unplanned->last_failure_at !== null
            ? CarbonImmutable::parse($unplanned->last_failure_at)
            : null;

        // ── All events (planned + unplanned) for total availability ───────────
        $allDowntimeMinutes = (float) EquipmentDowntimeEvent::withoutGlobalScopes()
            ->selectRaw(
                'COALESCE(SUM(COALESCE(duration_minutes,
                    EXTRACT(EPOCH FROM (ended_at - started_at)) / 60
                )), 0) AS total_minutes'
            )
            ->where('equipment_id', $equipment->id)
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', $periodStart)
            ->value('total_minutes') ?? 0;

        $totalDowntimeHours = round($allDowntimeMinutes / 60, 4);

        // ── Derived KPIs ──────────────────────────────────────────────────────
        $unplannedOperatingHours = max(0.0, $totalPeriodHours - $unplannedDowntimeHours);
        $totalOperatingHours = max(0.0, $totalPeriodHours - $totalDowntimeHours);

        $mttrHours = $failureCount > 0
            ? round($unplannedDowntimeHours / $failureCount, 2)
            : null;

        // MTBF prefers real operating hours from the hour-meter (accurate for
        // equipment that does not run 24/7); falls back to calendar operating
        // hours when no usable meter reading spans the window.
        $meterOperatingHours = $this->meterOperatingHours($equipment, $periodStart, $periodEnd);
        $usesMeter = $meterOperatingHours !== null && $meterOperatingHours > 0.0;
        $mtbfOperatingHours = $usesMeter ? $meterOperatingHours : $unplannedOperatingHours;

        $mtbfHours = $failureCount > 0
            ? round($mtbfOperatingHours / $failureCount, 2)
            : null;

        $availabilityPercentage = round($totalOperatingHours / $totalPeriodHours * 100, 2);
        $unplannedAvailabilityPercentage = round($unplannedOperatingHours / $totalPeriodHours * 100, 2);

        return new EquipmentKpiData(
            periodMonths: $periodMonths,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            mtbfHours: $mtbfHours,
            mttrHours: $mttrHours,
            availabilityPercentage: $availabilityPercentage,
            unplannedAvailabilityPercentage: $unplannedAvailabilityPercentage,
            failureCount: $failureCount,
            downtimeHours: $unplannedDowntimeHours,
            lastFailureAt: $lastFailureAt,
            operatingHours: $usesMeter ? round($meterOperatingHours, 2) : null,
            mtbfBasis: $usesMeter ? 'meter' : 'calendar',
        );
    }

    /**
     * Operating hours accrued on the equipment's hour-meter during the window,
     * from the span between the first and last hour reading. Returns null when
     * the equipment is not measured in hours or has fewer than two readings in
     * the window (no reliable span to measure).
     */
    private function meterOperatingHours(Equipment $equipment, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?float
    {
        if ($equipment->meter_unit !== MeterReadingUnit::Hours->value) {
            return null;
        }

        $bounds = EquipmentMeterReading::withoutGlobalScopes()
            ->where('equipment_id', $equipment->id)
            ->where('reading_unit', MeterReadingUnit::Hours->value)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd])
            ->selectRaw('MIN(reading_value) AS min_value, MAX(reading_value) AS max_value, COUNT(*) AS reading_count')
            ->first();

        if ((int) ($bounds->reading_count ?? 0) < 2) {
            return null;
        }

        $span = (float) $bounds->max_value - (float) $bounds->min_value;

        return $span > 0.0 ? $span : null;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Upsert — creates the row on first run, updates on subsequent runs.
     * Uses withoutGlobalScopes() because this runs in job context (no CurrentTenant).
     * Uses withTrashed() so soft-deleted rows are restored rather than duplicated.
     */
    private function persist(Equipment $equipment, EquipmentKpiData $data): EquipmentKpi
    {
        /** @var EquipmentKpi $kpi */
        $kpi = EquipmentKpi::withoutGlobalScopes()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'tenant_id' => $equipment->tenant_id,
                    'equipment_id' => $equipment->id,
                ],
                [
                    'period_months' => $data->periodMonths,
                    'period_start' => $data->periodStart->toDateString(),
                    'period_end' => $data->periodEnd->toDateString(),
                    'mtbf_hours' => $data->mtbfHours,
                    'mttr_hours' => $data->mttrHours,
                    'availability_percentage' => $data->availabilityPercentage,
                    'unplanned_availability_percentage' => $data->unplannedAvailabilityPercentage,
                    'failure_count' => $data->failureCount,
                    'downtime_hours' => $data->downtimeHours,
                    'operating_hours' => $data->operatingHours,
                    'mtbf_basis' => $data->mtbfBasis,
                    'last_failure_at' => $data->lastFailureAt,
                    'last_calculated_at' => now(),
                    'is_stale' => false,
                    'deleted_at' => null, // restore if soft-deleted
                ]
            );

        return $kpi->refresh();
    }

    /**
     * Reads kpi_period_months from the tenant's JSONB settings column.
     * Falls back to 12 if not configured.
     */
    private function resolvePeriodMonths(string $tenantId): int
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        return (int) ($tenant?->settings['kpi_period_months'] ?? 12);
    }
}
