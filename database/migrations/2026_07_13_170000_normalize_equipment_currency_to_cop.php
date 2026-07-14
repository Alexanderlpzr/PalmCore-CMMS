<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M3 — la moneda por defecto es la de la operación.
 *
 * `equipment.currency_code` nacía en USD mientras la planta compra, repara y
 * reporta en pesos. Nadie eligió ese dólar: era el default de la columna, y basta
 * con que un equipo se cree sin tocar el campo para que su costo quede etiquetado
 * en una moneda que la planta no usa.
 *
 * El backfill es deliberadamente tímido: solo se reetiquetan las filas en USD que
 * **no tienen ningún monto** — ahí la moneda no significa nada, es el default sin
 * elegir. Un equipo con un precio en USD pudo comprarse de verdad en dólares
 * (bombas importadas, cajas reductoras) y reetiquetarlo convertiría 40.000 USD en
 * 40.000 COP de un plumazo. Esas filas se quedan como están: la moneda es una
 * decisión de quien registró el costo, no de una migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('COP')->change();
        });

        DB::statement(<<<'SQL'
            UPDATE equipment
            SET currency_code = 'COP'
            WHERE currency_code = 'USD'
              AND purchase_price IS NULL
              AND replacement_cost IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('USD')->change();
        });

        // Los valores no se revierten: no hay forma de saber cuáles eran COP de
        // verdad y cuáles quedaron en USD por descuido.
    }
};
