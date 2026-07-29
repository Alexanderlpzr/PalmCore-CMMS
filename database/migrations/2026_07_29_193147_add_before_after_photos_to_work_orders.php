<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro fotográfico de la OT: una foto del «antes» al crearla y una del
 * «después» al cerrarla.
 *
 * Van como columnas de la OT y no en work_order_attachments porque son dos
 * fotos fijas del ciclo de la orden, no una galería, y porque los adjuntos
 * viven en el disco privado, que hoy no sabe servir imágenes para mostrarlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('before_photo_path')->nullable()->after('work_performed');
            $table->string('after_photo_path')->nullable()->after('before_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['before_photo_path', 'after_photo_path']);
        });
    }
};
