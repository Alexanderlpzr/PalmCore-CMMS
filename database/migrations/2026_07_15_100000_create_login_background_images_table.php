<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las fotos del carrusel de login. Sin tenant a propósito: el login ocurre antes de
 * que exista un contexto de empresa —un visitante todavía no ha elegido a cuál
 * organización pertenece— así que estas imágenes son de la plataforma entera, no de
 * ninguna empresa en particular. Las administra solo el superadministrador.
 *
 * Nada de `starts_at`/`ends_at`/botones como CarouselSlide: ese modelo es del portal
 * de inicio de una empresa ya autenticada —otro problema, con programación de
 * vigencia y llamadas a la acción—. Aquí solo hace falta la foto, el orden y un
 * interruptor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_background_images', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('image_path', 500);
            $table->string('caption', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_background_images');
    }
};
