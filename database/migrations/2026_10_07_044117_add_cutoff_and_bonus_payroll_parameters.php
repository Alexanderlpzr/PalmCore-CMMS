<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dos parámetros nuevos para cada empresa que ya tiene nómina: el día de corte de horas
 * y la regla del bono.
 *
 * La liquidación exige que existan todos los parámetros —falla antes de inventar un
 * valor—, así que una clave nueva sin vigencia dejaría a la empresa sin poder liquidar.
 * Entran apagadas (0: mes calendario y todo como horas extras), que es como liquidaba
 * hasta hoy, y desde la primera vigencia de la empresa, para que los meses ya cargados
 * también las tengan. Prenderlas es una decisión de cada empresa, desde «Parámetros de
 * nómina».
 */
return new class extends Migration
{
    private const KEYS = ['hours_cutoff_day', 'overtime_excess_as_bonus'];

    public function up(): void
    {
        $firstVigencies = DB::table('hr_payroll_parameters')
            ->select('tenant_id', DB::raw('min(effective_from) as first_from'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($firstVigencies as $tenant) {
            foreach (self::KEYS as $key) {
                $exists = DB::table('hr_payroll_parameters')
                    ->where('tenant_id', $tenant->tenant_id)
                    ->where('key', $key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('hr_payroll_parameters')->insert([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->tenant_id,
                    'key' => $key,
                    'value' => 0,
                    'effective_from' => $tenant->first_from,
                    'effective_to' => null,
                    'notes' => 'Entra apagado: así liquidaba la empresa hasta hoy.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('hr_payroll_parameters')->whereIn('key', self::KEYS)->delete();
    }
};
