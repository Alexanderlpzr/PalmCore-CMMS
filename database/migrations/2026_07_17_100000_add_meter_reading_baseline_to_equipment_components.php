<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El bug: «Horas de vida» (worked_hours) era un número que alguien escribía una
 * vez al crear el componente y que nunca más se movía. El horómetro del equipo
 * seguía acumulando de verdad —eso ya funcionaba— pero nada conectaba esa
 * acumulación con la pieza. Un rodamiento instalado hace un año con 200 h
 * escritas a mano seguía diciendo 200 h para siempre, sin importar cuánto más
 * hubiera trabajado el equipo desde entonces.
 *
 * `meter_reading_baseline` es el punto de referencia: el acumulado del equipo en
 * el momento en que `worked_hours` se fijó como cierto (al crear el componente, o
 * al corregirlo a mano). Desde ahí, cada lectura nueva de horómetro adelanta
 * `worked_hours` exactamente lo que avanzó el acumulado — ni una hora más, ni una
 * menos.
 *
 * El backfill no inventa historia: preserva el valor que ya existía (o lo deja en
 * 0 si nunca se escribió nada) y fija el punto de partida en el acumulado de HOY.
 * El pasado que nadie registró sigue sin inventarse; lo único que cambia es que a
 * partir de este momento el número empieza a moverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_components', function (Blueprint $table) {
            $table->decimal('meter_reading_baseline', 12, 1)->nullable()->after('worked_hours');
        });

        DB::statement(<<<'SQL'
            UPDATE equipment_components ec
            SET worked_hours = COALESCE(ec.worked_hours, 0),
                meter_reading_baseline = eq.accumulated_meter_reading
            FROM equipment eq
            WHERE eq.id = ec.equipment_id
        SQL);
    }

    public function down(): void
    {
        Schema::table('equipment_components', function (Blueprint $table) {
            $table->dropColumn('meter_reading_baseline');
        });
    }
};
