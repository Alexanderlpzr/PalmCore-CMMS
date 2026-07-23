<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El reporte de novedad ahora pide el cargo de quien reporta (en vez del teléfono)
 * y permite adjuntar una foto tomada desde el celular en planta. `reporter_phone`
 * se deja en la tabla por los reportes viejos, pero ya no se captura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_issue_reports', function (Blueprint $table) {
            $table->string('reporter_position', 255)->nullable()->after('reporter_name');
            $table->string('photo_path', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_issue_reports', function (Blueprint $table) {
            $table->dropColumn(['reporter_position', 'photo_path']);
        });
    }
};
