<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un techo de cordura para la fruta del día, en la base.
 *
 * Un mes entero se cargó en kilogramos —196.350 en un día que la planta prensa en unas
 * 250 toneladas— y entró sin que nada protestara. La productividad quedó mil veces
 * inflada, 12.867 t/h contra las 13,51 de referencia, y nadie lo notó durante semanas:
 * solo salió a la luz al cruzar la producción contra el consumo eléctrico.
 *
 * La validación se puso primero en el servicio de captura semanal, y eso resultó
 * insuficiente: hay cuatro puertas que escriben esta columna —la rejilla semanal, el
 * formulario del día, la edición en línea de la tabla y la API—, y tres la esquivaban
 * porque escriben el modelo directamente. Poner el límite en cada una es repetirlo
 * cuatro veces y olvidarlo en la quinta.
 *
 * Aquí abajo es el único punto por el que pasan todas, incluidos los seeders, los
 * comandos de importación y cualquier consola. Las cuatro puertas conservan su
 * validación propia para dar un mensaje legible; esta es la red que atrapa lo que se
 * escape.
 *
 * El número no es la capacidad de ninguna planta concreta: es el orden de magnitud a
 * partir del cual la cifra ya no puede ser toneladas.
 */
return new class extends Migration
{
    private const MAX_DAILY_TONS = 2000;

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE production_calendar
             ADD CONSTRAINT production_calendar_processed_tons_sane
             CHECK (processed_tons >= 0 AND processed_tons <= '.self::MAX_DAILY_TONS.')'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE production_calendar
             DROP CONSTRAINT IF EXISTS production_calendar_processed_tons_sane'
        );
    }
};
