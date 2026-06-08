<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            // PK bigint — el vendor model Laravel\Passkeys\Passkey no tiene HasUuids
            // UUID solo en user_id (FK hacia users.id que sí es UUID v7)
            $table->id();

            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestampTz('last_used_at')->nullable();

            $table->timestampsTz();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
