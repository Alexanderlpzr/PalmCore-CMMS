<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada hallazgo dice de dónde viene —SST o Ambiental— y, si llegó de un cuadro de Excel,
 * con qué ITM. Así un cuadro ambiental que empieza en el ITM 1 no choca con el de SST:
 * toma los números siguientes y no se repite al importarlo otra vez.
 *
 * Los que ya estaban son todos de SST y se importaron con su ITM como número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sst_findings', function (Blueprint $table) {
            $table->string('origin', 20)->default('sst')->after('number');
            $table->unsignedInteger('source_item')->nullable()->after('origin');

            $table->index(['tenant_id', 'origin', 'source_item']);
        });

        DB::table('sst_findings')->whereNull('source_item')->update(['source_item' => DB::raw('number')]);
    }

    public function down(): void
    {
        Schema::table('sst_findings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'origin', 'source_item']);
            $table->dropColumn(['origin', 'source_item']);
        });
    }
};
