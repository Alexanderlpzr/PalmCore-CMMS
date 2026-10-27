<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uso de la calculadora pública R-100: solo dos contadores por día. Sin IP,
 * sin cookies, sin nada de lo que el proveedor escribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('r100_daily_usages', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('calculations')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('r100_daily_usages');
    }
};
