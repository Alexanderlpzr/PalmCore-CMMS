<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta cuándo va el contrato a término fijo. Se propone desde la fecha de ingreso en
 * tramos de 3, 6, 9 o 12 meses y sirve para avisar con 30 días si no se renueva, como pide
 * la ley para el preaviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->date('contract_end_date')->nullable()->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn('contract_end_date');
        });
    }
};
