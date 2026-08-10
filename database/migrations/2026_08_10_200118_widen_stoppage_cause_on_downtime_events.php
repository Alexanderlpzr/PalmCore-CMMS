<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La causa del paro cabía en 120 caracteres, pero el formulario ofrece 500: quien
 * escribía una observación larga no recibía un aviso de validación sino un error
 * del servidor. La planilla de la planta ya trae causas de 123 caracteres —
 * «FALTA DE FRUTO ESTERILIZADO, SE ATRAZO LA ESTERILIZADA... SENSORES DE LA
 * PUERTA DEL ESTERILIZADOR»— que es exactamente la clase de detalle que sirve
 * para diagnosticar. La columna se alinea con lo que la pantalla promete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table): void {
            $table->string('stoppage_cause', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table): void {
            $table->string('stoppage_cause', 120)->nullable()->change();
        });
    }
};
