<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Las meriendas (de día y de noche) se pagan distinto de las comidas principales. La clave
 * nueva entra en 0 para cada empresa con nómina —ninguna paga hasta que lo decida—, desde su
 * primera vigencia, porque la liquidación exige que existan todos los parámetros.
 */
return new class extends Migration
{
    public function up(): void
    {
        $firstVigencies = DB::table('hr_payroll_parameters')
            ->select('tenant_id', DB::raw('min(effective_from) as first_from'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($firstVigencies as $tenant) {
            if (DB::table('hr_payroll_parameters')->where('tenant_id', $tenant->tenant_id)->where('key', 'meal_snack_value')->exists()) {
                continue;
            }

            DB::table('hr_payroll_parameters')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenant->tenant_id,
                'key' => 'meal_snack_value',
                'value' => 0,
                'effective_from' => $tenant->first_from,
                'effective_to' => null,
                'notes' => 'Auxilio de alimentación: valor inicial de la merienda.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('hr_payroll_parameters')->where('key', 'meal_snack_value')->delete();
    }
};
