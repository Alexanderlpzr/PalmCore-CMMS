<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El registro fotográfico de la OT pasa de dos fotos a dos galerías.
 *
 * La migración que las creó decía que iban como columnas sueltas «porque son dos fotos
 * fijas del ciclo de la orden, no una galería». Era cierto entonces y dejó de serlo: un
 * rodamiento que se cambia no se explica con una foto, y quien cierra la OT termina
 * eligiendo cuál de las cuatro que tomó es la que vale.
 *
 * El otro motivo por el que aquellas eran columnas sigue en pie y por eso no se mudan a
 * `work_order_attachments`: los adjuntos viven en el disco privado, que hoy devuelve una
 * ruta rota al intentar mostrar una imagen. Estas galerías se quedan en el disco
 * persistente, que sí las sirve. El día que el disco privado sepa firmar URLs, las dos
 * cosas se pueden unificar.
 *
 * Se renombran a plural a propósito: `before_photo_path` con un array dentro es una
 * columna que miente sobre lo que guarda, y el nombre es lo primero que alguien lee.
 *
 * La vuelta atrás conserva la primera foto de cada galería y pierde el resto. No hay
 * forma de evitarlo —una columna de texto no guarda cuatro rutas— y es preferible a que
 * `down()` falle: revertir con datos dentro es justo cuando más falta hace que funcione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->jsonb('before_photos')->nullable()->after('work_performed');
            $table->jsonb('after_photos')->nullable()->after('before_photos');
        });

        // Cada foto que ya existe se convierte en una galería de una.
        DB::statement("
            UPDATE work_orders
            SET before_photos = jsonb_build_array(before_photo_path)
            WHERE before_photo_path IS NOT NULL AND before_photo_path <> ''
        ");

        DB::statement("
            UPDATE work_orders
            SET after_photos = jsonb_build_array(after_photo_path)
            WHERE after_photo_path IS NOT NULL AND after_photo_path <> ''
        ");

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['before_photo_path', 'after_photo_path']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('before_photo_path')->nullable()->after('work_performed');
            $table->string('after_photo_path')->nullable()->after('before_photo_path');
        });

        // Solo la primera de cada galería: ver la nota de cabecera.
        DB::statement("
            UPDATE work_orders
            SET before_photo_path = before_photos->>0
            WHERE jsonb_typeof(before_photos) = 'array' AND jsonb_array_length(before_photos) > 0
        ");

        DB::statement("
            UPDATE work_orders
            SET after_photo_path = after_photos->>0
            WHERE jsonb_typeof(after_photos) = 'array' AND jsonb_array_length(after_photos) > 0
        ");

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['before_photos', 'after_photos']);
        });
    }
};
