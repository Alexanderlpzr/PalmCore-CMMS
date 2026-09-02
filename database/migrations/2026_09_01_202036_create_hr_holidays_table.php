<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los festivos del año, capturados y no calculados.
 *
 * Los festivos colombianos no salen de una fórmula: la Ley Emiliani corre buena parte
 * de ellos al lunes siguiente, y los que dependen de Pascua se mueven con ella. Una
 * función que los derive es una función que va a fallar un año cualquiera, y el error
 * se paga a 2,05 veces la hora ordinaria en toda la planta.
 *
 * En la nómina de agosto de 2026 hay dos —el 7 y el 17— y el libro los marca a mano en
 * las 48 filas de cada día. Esa marca, además, hoy no la lee ninguna fórmula: es una
 * ayuda visual para quien captura, no un control. Aquí sí gobierna el cálculo, y por eso
 * la tabla es del tenant: una extractora puede tener un festivo local que el resto del
 * país no tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->date('holiday_date');
            $table->string('name', 120);

            // Distingue el festivo de ley del que agrega la empresa. No cambia el
            // cálculo; cambia quién puede discutirlo.
            $table->boolean('is_national')->default(true);

            $table->timestampsTz(0);

            $table->unique(['tenant_id', 'holiday_date']);
            $table->index(['tenant_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_holidays');
    }
};
