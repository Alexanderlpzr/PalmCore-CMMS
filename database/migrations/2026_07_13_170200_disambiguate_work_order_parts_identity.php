<?php

use App\Observers\WorkOrderPartObserver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M6 — un repuesto de la OT tenía dos identidades y ninguna mandaba.
 *
 * `part_code` es texto libre (nació antes que el inventario) y `spare_part_id`
 * apunta al maestro. Un renglón podía tener los dos y decir cosas distintas: el
 * técnico escribía «rodamiento 6205» a mano y enlazaba el repuesto RD-0417. Cuál de
 * los dos es el repuesto que salió del almacén no lo sabía nadie, y el Pareto de
 * consumo se calcula sobre uno de ellos.
 *
 * A partir de aquí:
 *
 *  - Enlazado al inventario → `part_code` es un **snapshot congelado** del código
 *    del maestro, escrito por {@see WorkOrderPartObserver}. Deja de
 *    ser un campo que se contradice con el enlace y pasa a ser lo que el maestro
 *    decía el día que se consumió, aunque el código se renombre después.
 *  - Sin enlace → `part_code` sigue siendo texto libre, y puede incluso no existir:
 *    el repuesto que se compró en la ferretería del pueblo un domingo se identifica
 *    por su descripción, que la tabla ya exige NOT NULL. Obligarlo a inventar un
 *    código sería obligar a inventar un dato.
 *
 * Backfill: los renglones enlazados se reescriben con el código del maestro, que es
 * el que dice qué salió del almacén de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE work_order_parts wop
            SET part_code = sp.code
            FROM spare_parts sp
            WHERE wop.spare_part_id = sp.id
              AND (wop.part_code IS NULL OR wop.part_code <> sp.code)
        SQL);

    }

    public function down(): void
    {
        // El backfill no se revierte: no hay a qué volver. Los códigos que se
        // reescribieron eran, precisamente, los que contradecían al maestro.
    }
};
