<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->foreignUuid('preliminary_technician_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->dropForeignIdFor(User::class, 'preliminary_technician_id');
            $table->dropColumn('preliminary_technician_id');
        });
    }
};
