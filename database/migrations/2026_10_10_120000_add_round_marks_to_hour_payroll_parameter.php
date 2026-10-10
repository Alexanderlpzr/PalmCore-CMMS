<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Redondear la entrada y la salida a la hora en punto. La clave nueva entra apagada para
 * cada empresa con nómina, desde su primera vigencia, porque la liquidación exige que
 * existan todos los parámetros. Cada empresa la enciende con la vigencia que decida.
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
            if (DB::table('hr_payroll_parameters')->where('tenant_id', $tenant->tenant_id)->where('key', 'round_marks_to_hour')->exists()) {
                continue;
            }

            DB::table('hr_payroll_parameters')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenant->tenant_id,
                'key' => 'round_marks_to_hour',
                'value' => 0,
                'effective_from' => $tenant->first_from,
                'effective_to' => null,
                'notes' => 'Jornada: las marcas se cuentan con sus minutos hasta que se decida redondear.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('hr_payroll_parameters')->where('key', 'round_marks_to_hour')->delete();
    }
};
