<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los tres renglones amarillos de la hoja de energía: cambios de fuente, horas de la
 * planta eléctrica y combustible.
 *
 * Las **horas no se guardan aquí**. Salen del horómetro de los generadores —lo que
 * avanzó el dial en el mes— y ese dato ya vive en `equipment_meter_readings`. Escribirlas
 * otra vez sería un segundo sitio donde la misma cifra puede discrepar, que es
 * exactamente el error que este módulo existe para no repetir.
 *
 * Lo que sí es nuevo son el combustible y los cambios de fuente: no son la lectura de
 * ningún contador —no hay dial que restar— así que no caben en `energy_meter_readings`,
 * cuya forma entera es «lectura acumulada menos la anterior».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_energy_daily_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->cascadeOnDelete();

            $table->date('log_date');

            // Nullable las dos, y es la misma distinción que en los contadores: cero
            // galones dice que ese día no se cargó diésel; vacío dice que nadie lo anotó.
            // Sumar un mes sobre ceros inventados daría un consumo que nadie midió.
            $table->decimal('fuel_gallons', 10, 1)->nullable();
            $table->unsignedSmallInteger('energy_switch_count')->nullable();

            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestampsTz(0);

            // Un día, una fila: la ronda se hace una vez.
            $table->unique(['plant_id', 'log_date']);
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            // Qué generadores son «la planta eléctrica» a efectos del informe. Hoy son el
            // de 1250 kVA y el de 72 kVA; el contador ENE-PLA solo apunta al primero, así
            // que sin esto no habría forma de sumar las horas de los dos.
            //
            // Marcado a mano y no deducido de la unidad del horómetro: deducirlo acertaría
            // hoy y fallaría callado el día que alguien cree otro equipo medido en horas.
            $table->boolean('counts_as_power_plant')->default(false)->after('meter_capture_mode');
        });

        Schema::table('plant_monthly_kpis', function (Blueprint $table) {
            // Las tres del mes, al lado de los kWh con los que se leen. Nullable por lo
            // mismo que `kwh_turbine`: un mes sin lecturas no es un mes de cero horas.
            $table->decimal('genset_hours', 10, 1)->nullable()->after('energy_is_imported');
            $table->decimal('genset_fuel_gallons', 12, 1)->nullable()->after('genset_hours');
            $table->unsignedInteger('energy_switch_count')->nullable()->after('genset_fuel_gallons');
        });
    }

    public function down(): void
    {
        Schema::table('plant_monthly_kpis', function (Blueprint $table) {
            $table->dropColumn(['genset_hours', 'genset_fuel_gallons', 'energy_switch_count']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('counts_as_power_plant');
        });

        Schema::dropIfExists('plant_energy_daily_logs');
    }
};
