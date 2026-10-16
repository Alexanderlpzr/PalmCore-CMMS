<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La jornada contada por semana. La clave nueva entra apagada (0) para cada empresa con
 * nómina, desde su primera vigencia, porque la liquidación exige que existan todos los
 * parámetros. La extractora la enciende con `payroll:weekly-hours`, que además recalcula
 * el corte en curso.
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
            if (DB::table('hr_payroll_parameters')->where('tenant_id', $tenant->tenant_id)->where('key', 'weekly_ordinary_hours')->exists()) {
                continue;
            }

            DB::table('hr_payroll_parameters')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenant->tenant_id,
                'key' => 'weekly_ordinary_hours',
                'value' => 0,
                'effective_from' => $tenant->first_from,
                'effective_to' => null,
                'notes' => 'Jornada: se cuenta por día hasta que la empresa decida contar por semana.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('hr_payroll_parameters')->where('key', 'weekly_ordinary_hours')->delete();
    }
};
