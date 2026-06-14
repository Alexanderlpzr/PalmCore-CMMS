<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use Carbon\CarbonInterface;

class EquipmentMeterReadingService
{
    /**
     * Record a new meter reading for the equipment.
     * Updates equipment.current_meter_reading as a denormalized cache.
     *
     * @throws \RuntimeException if the new reading is lower than the current value (backwards reading)
     */
    public function record(
        Equipment $equipment,
        float $readingValue,
        User $recordedBy,
        MeterReadingUnit $unit = MeterReadingUnit::Hours,
        ?CarbonInterface $recordedAt = null,
        ?string $notes = null,
    ): EquipmentMeterReading {
        $this->validateReading($equipment, $readingValue);

        $reading = EquipmentMeterReading::create([
            'tenant_id' => $equipment->tenant_id,
            'equipment_id' => $equipment->id,
            'reading_value' => $readingValue,
            'reading_unit' => $unit->value,
            'recorded_at' => $recordedAt ?? now(),
            'recorded_by' => $recordedBy->id,
            'notes' => $notes,
        ]);

        // Update denormalized cache on equipment
        $equipment->update([
            'current_meter_reading' => $readingValue,
            'meter_unit' => $unit->value,
        ]);

        return $reading;
    }

    /**
     * Get the latest confirmed reading value for an equipment.
     * Returns null if no readings have been recorded.
     */
    public function currentReading(Equipment $equipment): ?float
    {
        return $equipment->current_meter_reading
            ?? EquipmentMeterReading::withoutGlobalScopes()
                ->where('equipment_id', $equipment->id)
                ->orderByDesc('recorded_at')
                ->value('reading_value');
    }

    private function validateReading(Equipment $equipment, float $newValue): void
    {
        $current = $this->currentReading($equipment);

        if ($current !== null && $newValue < $current) {
            throw new \RuntimeException(
                sprintf(
                    'La lectura %.1f es menor al valor actual %.1f. Los horómetros no retroceden.',
                    $newValue,
                    $current,
                )
            );
        }
    }
}
