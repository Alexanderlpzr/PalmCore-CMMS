<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\WorkedHoursPeriodType;
use App\Models\Equipment;
use App\Models\EquipmentWorkedHours;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Horas trabajadas por equipo, registradas directamente (no vía dial de
 * horómetro). Diario y Semanal son los únicos tipos que se capturan; Mensual y
 * Anual son sumas de esos dos, calculadas al vuelo por summary() — no existe un
 * registro "mensual" o "anual" guardado en la base.
 */
class EquipmentWorkedHoursService
{
    public function record(
        Equipment $equipment,
        WorkedHoursPeriodType $periodType,
        float $hours,
        CarbonInterface $logDate,
        User $recordedBy,
        ?string $notes = null,
    ): EquipmentWorkedHours {
        if ($hours < 0) {
            throw new \InvalidArgumentException('Las horas trabajadas no pueden ser negativas.');
        }

        return EquipmentWorkedHours::create([
            'tenant_id' => $equipment->tenant_id,
            'equipment_id' => $equipment->id,
            'period_type' => $periodType,
            'log_date' => $logDate->toDateString(),
            'hours' => $hours,
            'recorded_by' => $recordedBy->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Suma de horas trabajadas por equipo (cualquier period_type) entre
     * $from y $to, para todos los equipos del tenant. Es la vista de solo
     * lectura detrás de "Registro mensual" y "Registro anual".
     *
     * @return list<array{equipment_id: string, code: ?string, name: ?string, total_hours: float}>
     */
    public function summary(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        return EquipmentWorkedHours::query()
            ->join('equipment', 'equipment.id', '=', 'equipment_worked_hours.equipment_id')
            ->where('equipment_worked_hours.tenant_id', $tenantId)
            ->whereBetween('equipment_worked_hours.log_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('equipment.id', 'equipment.code', 'equipment.name')
            ->orderBy('equipment.code')
            ->select('equipment.id as equipment_id', 'equipment.code', 'equipment.name')
            ->selectRaw('SUM(equipment_worked_hours.hours) as total_hours')
            ->get()
            ->map(fn ($row): array => [
                'equipment_id' => $row->equipment_id,
                'code' => $row->code,
                'name' => $row->name,
                'total_hours' => (float) $row->total_hours,
            ])
            ->all();
    }
}
