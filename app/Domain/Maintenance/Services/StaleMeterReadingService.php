<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\MaintenancePlan;
use Illuminate\Support\Carbon;

/**
 * A7 — el horómetro que nadie leyó.
 *
 * Un plan preventivo por horómetro no falla con un error: falla en silencio. Deja
 * de proyectar la próxima OT porque el acumulado se quedó quieto, y la máquina
 * sigue trabajando horas que nadie está contando. Seis semanas después el
 * reductor se rompe y el plan «estaba activo».
 *
 * Este servicio solo mira los equipos cuyo programa preventivo *depende* de una
 * lectura: alertar por un horómetro que no alimenta ningún plan sería ruido, y el
 * ruido es lo que hace que se ignoren las alertas que sí importan.
 */
class StaleMeterReadingService
{
    public function __construct(private readonly AlertService $alerts) {}

    /**
     * Los equipos con plan por horómetro que llevan demasiado tiempo sin lectura.
     *
     * Un equipo que nunca fue leído también cuenta, y su antigüedad se mide desde
     * que el plan existe: un plan creado ayer no lleva 400 días roto, lleva uno.
     *
     * @return list<array{
     *     equipment: Equipment,
     *     days_without_reading: int,
     *     last_reading_at: ?Carbon,
     *     plan_numbers: list<string>,
     * }>
     */
    public function detect(string $tenantId, ?int $thresholdDays = null): array
    {
        $threshold = $thresholdDays ?? $this->thresholdDays();

        $plans = MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('trigger_source', [
                MaintenanceTriggerSource::Meter->value,
                MaintenanceTriggerSource::Hybrid->value,
            ])
            ->with(['equipment' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            // Una máquina dada de baja no tiene horómetro que leer. El plan sigue
            // «activo» en la base, pero el equipo ya no está en la planta.
            ->filter(fn (MaintenancePlan $plan): bool => $plan->equipment !== null
                && ! in_array($plan->equipment->status, [
                    EquipmentStatus::Retired,
                    EquipmentStatus::Disposed,
                ], strict: true))
            ->groupBy('equipment_id');

        if ($plans->isEmpty()) {
            return [];
        }

        $lastReadings = EquipmentMeterReading::withoutGlobalScopes()
            ->whereIn('equipment_id', $plans->keys())
            ->selectRaw('equipment_id, MAX(recorded_at) AS last_recorded_at')
            ->groupBy('equipment_id')
            ->pluck('last_recorded_at', 'equipment_id');

        $stale = [];

        foreach ($plans as $equipmentId => $equipmentPlans) {
            $lastReadingAt = isset($lastReadings[$equipmentId])
                ? Carbon::parse($lastReadings[$equipmentId])
                : null;

            // Sin ninguna lectura, el plan lleva sin proyectarse desde que nació.
            $since = $lastReadingAt ?? $equipmentPlans->min('created_at');

            if ($since === null) {
                continue;
            }

            $days = (int) floor(Carbon::parse($since)->diffInDays(now()));

            if ($days < $threshold) {
                continue;
            }

            $stale[] = [
                'equipment' => $equipmentPlans->first()->equipment,
                'days_without_reading' => $days,
                'last_reading_at' => $lastReadingAt,
                'plan_numbers' => $equipmentPlans->pluck('plan_number')->values()->all(),
            ];
        }

        usort($stale, fn (array $a, array $b): int => $b['days_without_reading'] <=> $a['days_without_reading']);

        return $stale;
    }

    /**
     * Levanta (o agrava) una alerta por cada equipo mudo. Idempotente: mientras la
     * alerta siga abierta no se crea una segunda, pero si el silencio se alarga la
     * alerta existente sube de severidad en vez de quedarse congelada en «warning».
     *
     * @return int alertas creadas o agravadas
     */
    public function raiseAlerts(string $tenantId, ?int $thresholdDays = null): int
    {
        $threshold = $thresholdDays ?? $this->thresholdDays();
        $touched = 0;

        foreach ($this->detect($tenantId, $threshold) as $row) {
            $equipment = $row['equipment'];
            $days = $row['days_without_reading'];
            $severity = $this->severityFor($days, $threshold);

            $message = $row['last_reading_at'] === null
                ? "El equipo nunca ha registrado una lectura de horómetro y sus planes preventivos ({$this->planList($row)}) no pueden proyectarse."
                : "Última lectura hace {$days} días ("
                    .$row['last_reading_at']->format('d/m/Y')
                    ."). Los planes preventivos por horómetro ({$this->planList($row)}) dejaron de proyectarse.";

            $metadata = [
                'equipment_code' => $equipment->code,
                'days_without_reading' => $days,
                'last_reading_at' => $row['last_reading_at']?->toISOString(),
                'threshold_days' => $threshold,
                'plan_numbers' => $row['plan_numbers'],
            ];

            $alert = $this->alerts->create(new CreateAlertData(
                tenantId: $tenantId,
                severity: $severity,
                category: AlertCategory::Maintenance,
                title: "Horómetro sin lectura: {$equipment->code}",
                message: $message,
                entityType: 'equipment',
                entityId: $equipment->id,
                metadata: $metadata,
            ));

            if ($alert !== null) {
                $touched++;

                continue;
            }

            if ($this->escalate($tenantId, $equipment->id, $severity, $message, $metadata)) {
                $touched++;
            }
        }

        return $touched;
    }

    /**
     * Llegó la lectura: la alerta se cierra sola. Obligar a alguien a ir a cerrarla
     * a mano es la forma más rápida de que el tablero de alertas deje de leerse.
     */
    public function resolveFor(Equipment $equipment): void
    {
        Alert::withoutGlobalScopes()
            ->where('tenant_id', $equipment->tenant_id)
            ->where('entity_type', 'equipment')
            ->where('entity_id', $equipment->id)
            ->where('category', AlertCategory::Maintenance->value)
            ->where('status', AlertStatus::Open->value)
            ->whereNull('deleted_at')
            ->whereNotNull('metadata->days_without_reading')
            ->each(function (Alert $alert): void {
                $metadata = $alert->metadata ?? [];
                $metadata['auto_resolved'] = 'meter_reading_recorded';

                $alert->forceFill([
                    'status' => AlertStatus::Resolved->value,
                    'closed_at' => now(),
                    'metadata' => $metadata,
                ])->save();
            });
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * El doble del plazo ya no es un olvido: es un equipo que dejó de existir para
     * el programa preventivo.
     */
    private function severityFor(int $days, int $threshold): AlertSeverity
    {
        return $days >= $threshold * 2 ? AlertSeverity::Critical : AlertSeverity::Warning;
    }

    /** @param array{plan_numbers: list<string>} $row */
    private function planList(array $row): string
    {
        return implode(', ', $row['plan_numbers']);
    }

    /** @param array<string, mixed> $metadata */
    private function escalate(
        string $tenantId,
        string $equipmentId,
        AlertSeverity $severity,
        string $message,
        array $metadata,
    ): bool {
        $alert = Alert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', 'equipment')
            ->where('entity_id', $equipmentId)
            ->where('category', AlertCategory::Maintenance->value)
            ->where('status', AlertStatus::Open->value)
            ->whereNull('deleted_at')
            ->whereNotNull('metadata->days_without_reading')
            ->first();

        if ($alert === null || $alert->severity === $severity || $severity !== AlertSeverity::Critical) {
            return false;
        }

        $alert->forceFill([
            'severity' => $severity->value,
            'message' => $message,
            'metadata' => $metadata,
        ])->save();

        return true;
    }

    private function thresholdDays(): int
    {
        return (int) config('palmcore.meters.stale_reading_days', 7);
    }
}
