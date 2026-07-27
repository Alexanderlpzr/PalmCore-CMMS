<?php

use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Revierte la migración `set_existing_equipment_to_daily_hours_capture`: el
     * ingeniero de El Pajuil confirmó que la planta SÍ captura el horómetro
     * acumulado (lo que marca el cuenta-horas), no las horas trabajadas del día —
     * las horas trabajadas son la diferencia entre la lectura de hoy y la de ayer,
     * y esa resta la hace el sistema, no el operador.
     *
     * Se vuelve a 'accumulated' para todo el equipo y se recalcula la cadena de
     * lecturas de cada uno: las que se guardaron mientras el equipo estuvo en
     * 'daily_hours' sumaron el número tecleado tal cual (el horómetro completo)
     * en vez de la diferencia con la lectura anterior, así que el acumulado y las
     * horas trabajadas de esos días quedaron mal.
     */
    public function up(): void
    {
        DB::table('equipment')->update(['meter_capture_mode' => 'accumulated']);

        $service = app(EquipmentMeterReadingService::class);

        Equipment::withoutGlobalScopes()
            ->whereHas('meterReadings')
            ->get()
            ->each(fn (Equipment $equipment) => $service->recomputeChain($equipment));
    }

    /**
     * Backfill de datos: no se revierte (no se guarda el modo previo de cada
     * equipo, igual que la migración que esta corrige).
     */
    public function down(): void
    {
        //
    }
};
