<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\MaintenancePlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Horómetros.
 *
 * Two numbers live here and they are not the same thing:
 *
 *  - `reading_value` / `current_meter_reading`: what the dial says today. It can
 *    go backwards, because dials get replaced.
 *  - `accumulated_value` / `accumulated_meter_reading`: how many hours the machine
 *    has actually worked since it entered service. It never goes backwards, and it
 *    is the only number a meter-driven preventive plan may be scheduled against.
 */
class EquipmentMeterReadingService
{
    public function __construct(private readonly StaleMeterReadingService $staleReadings) {}

    /**
     * Record a reading. A value below the current dial is not an error — it is a
     * meter reset — and it is recorded as such instead of being rejected.
     */
    public function record(
        Equipment $equipment,
        float $readingValue,
        User $recordedBy,
        MeterReadingUnit $unit = MeterReadingUnit::Hours,
        ?CarbonInterface $recordedAt = null,
        ?string $notes = null,
    ): EquipmentMeterReading {
        if ($readingValue < 0) {
            throw new \InvalidArgumentException('Una lectura de horómetro no puede ser negativa.');
        }

        return DB::transaction(function () use ($equipment, $readingValue, $recordedBy, $unit, $recordedAt, $notes): EquipmentMeterReading {
            $previous = $this->currentReading($equipment);
            $isReset = $previous !== null && $readingValue < $previous;

            // On a reset the new dial started at zero, so everything it shows is
            // consumption since the swap. Otherwise it is the plain difference.
            $delta = match (true) {
                $previous === null => 0.0,
                $isReset => $readingValue,
                default => $readingValue - $previous,
            };

            $accumulated = round((float) $equipment->accumulated_meter_reading + $delta, 1);

            $reading = EquipmentMeterReading::create([
                'tenant_id' => $equipment->tenant_id,
                'equipment_id' => $equipment->id,
                'reading_value' => $readingValue,
                'previous_value' => $previous,
                'delta' => round($delta, 1),
                'accumulated_value' => $accumulated,
                'is_reset' => $isReset,
                'reading_unit' => $unit->value,
                'recorded_at' => $recordedAt ?? now(),
                'recorded_by' => $recordedBy->id,
                'notes' => $notes,
            ]);

            $equipment->update([
                'current_meter_reading' => $readingValue,
                'accumulated_meter_reading' => $accumulated,
                'meter_unit' => $unit->value,
            ]);

            // A7 — el equipo volvió a hablar: la alerta de horómetro mudo se cierra
            // sola. Nadie va a entrar al tablero a cerrarla a mano.
            $this->staleReadings->resolveFor($equipment);

            return $reading;
        });
    }

    /**
     * The daily round: one operator walks the plant and enters ~30 dials at once.
     * One bad reading must not lose the other 29, so each is reported on its own.
     *
     * @param  array<int, array{equipment_id: string, reading_value: float, recorded_at?: CarbonInterface|string|null, notes?: ?string}>  $readings
     * @return array{recorded: list<EquipmentMeterReading>, failed: list<array{equipment_id: string, error: string}>}
     */
    public function recordBulk(array $readings, User $recordedBy, string $tenantId): array
    {
        $recorded = [];
        $failed = [];

        foreach ($readings as $row) {
            try {
                // Scoped to the tenant on purpose: the ids come straight from the
                // request body, and `exists:equipment,id` does not filter by tenant.
                $equipment = Equipment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->findOrFail($row['equipment_id']);

                $recorded[] = $this->record(
                    equipment: $equipment,
                    readingValue: (float) $row['reading_value'],
                    recordedBy: $recordedBy,
                    unit: $equipment->meter_unit ?? MeterReadingUnit::Hours,
                    recordedAt: isset($row['recorded_at']) ? Carbon::parse($row['recorded_at']) : null,
                    notes: $row['notes'] ?? null,
                );
            } catch (\Throwable $e) {
                $failed[] = ['equipment_id' => $row['equipment_id'], 'error' => $e->getMessage()];
            }
        }

        return ['recorded' => $recorded, 'failed' => $failed];
    }

    /** What the dial reads today. */
    public function currentReading(Equipment $equipment): ?float
    {
        return $equipment->current_meter_reading
            ?? EquipmentMeterReading::withoutGlobalScopes()
                ->where('equipment_id', $equipment->id)
                ->orderByDesc('recorded_at')
                ->value('reading_value');
    }

    /** Hours the machine has really worked — survives every meter swap. */
    public function accumulatedReading(Equipment $equipment): float
    {
        return (float) $equipment->accumulated_meter_reading;
    }

    /**
     * Average consumption per day over the last window, measured from the readings
     * themselves — not from the calendar, because a machine that was not read was
     * usually not running either.
     *
     * Null when there is not enough history to say anything honest.
     */
    public function consumptionPerDay(Equipment $equipment, int $days = 30): ?float
    {
        $since = now()->subDays($days);

        $readings = EquipmentMeterReading::withoutGlobalScopes()
            ->where('equipment_id', $equipment->id)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'delta']);

        if ($readings->count() < 2) {
            return null;
        }

        $elapsedDays = $readings->first()->recorded_at
            ->diffInMinutes($readings->last()->recorded_at) / 1440;

        if ($elapsedDays <= 0) {
            return null;
        }

        // The first reading's delta belongs to the period *before* the window.
        $consumed = (float) $readings->skip(1)->sum('delta');

        if ($consumed <= 0) {
            return null;
        }

        return round($consumed / $elapsedDays, 2);
    }

    /**
     * «Días faltantes» — the column the plant already keeps by hand: at the pace
     * this machine is being used, how many days until the next preventive falls
     * due?
     *
     * Null when the plan is not meter-driven, has no due point, or the equipment
     * has no measurable pace yet. Zero means it is already due.
     */
    public function daysUntilDue(Equipment $equipment, MaintenancePlan $plan, int $window = 30): ?int
    {
        $remaining = $this->metersRemaining($equipment, $plan);

        if ($remaining === null) {
            return null;
        }

        if ($remaining <= 0) {
            return 0;
        }

        $pace = $this->consumptionPerDay($equipment, $window);

        if ($pace === null || $pace <= 0) {
            return null;
        }

        return (int) ceil($remaining / $pace);
    }

    /**
     * Horas de horómetro que faltan para que el plan venza. `null` si el plan no es
     * por horómetro o nunca se activó; nunca negativo — un plan vencido muestra 0,
     * no una deuda que crece para siempre.
     */
    public function metersRemaining(Equipment $equipment, MaintenancePlan $plan): ?float
    {
        $dueMeter = $plan->schedule?->next_due_meter;

        if ($dueMeter === null) {
            return null;
        }

        return max(0.0, round($dueMeter - $this->accumulatedReading($equipment), 1));
    }

    /**
     * Horas de horómetro que ya lleva el plan desde la última intervención — o desde
     * que se activó, si todavía no ha corrido ninguna. Es el número que responde
     * «¿cuánto le hemos exigido a esta pieza desde el último cambio de aceite?».
     */
    public function metersSinceLastCompletion(Equipment $equipment, MaintenancePlan $plan): ?float
    {
        $schedule = $plan->schedule;

        if ($schedule === null || ($schedule->last_completed_meter === null && $schedule->next_due_meter === null)) {
            return null;
        }

        // Sin ejecución previa, el punto de partida es el horómetro con el que el plan
        // se activó: next_due_meter menos un intervalo completo.
        $baseline = $schedule->last_completed_meter
            ?? ((float) $schedule->next_due_meter - (float) ($plan->meter_interval ?? 0));

        return max(0.0, round($this->accumulatedReading($equipment) - $baseline, 1));
    }
}
