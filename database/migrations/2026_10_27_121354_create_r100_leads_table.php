<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos de los interesados que dejan sus datos en la calculadora R-100,
 * con la fecha en que autorizaron guardarlos (Ley 1581 de 2012).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('r100_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('consented_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('r100_leads');
    }
};
