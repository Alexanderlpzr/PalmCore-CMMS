<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las fotos con que cada área muestra lo que hizo en su parte del hallazgo. Antes había
 * una sola foto del «después» para todo el hallazgo, y la de un área pisaba la de otra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sst_finding_tasks', function (Blueprint $table) {
            $table->json('evidence_paths')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('sst_finding_tasks', function (Blueprint $table) {
            $table->dropColumn('evidence_paths');
        });
    }
};
