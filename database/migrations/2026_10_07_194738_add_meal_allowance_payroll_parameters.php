<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Los parámetros del auxilio de alimentación para cada empresa que ya tiene nómina: el
 * valor de cada comida y sus franjas.
 *
 * La liquidación exige que existan todos los parámetros, así que una clave nueva sin
 * vigencia dejaría a la empresa sin poder liquidar. El valor entra en 0 —ninguna empresa
 * paga comidas hasta que lo decida— y las franjas, con las de El Pajuil.
 */
return new class extends Migration
{
    private const KEYS = [
        'meal_allowance_value' => 0,
        'meal_night_shift_from' => 13,
        'meal_breakfast_start' => 5.333333,
        'meal_breakfast_end' => 7.333333,
        'meal_lunch_start' => 11.333333,
        'meal_lunch_end' => 13.333333,
        'meal_snack_start' => 17.5,
        'meal_snack_end' => 18.5,
        'meal_dinner_start' => 17.5,
        'meal_dinner_end' => 18.5,
        'meal_night_snack_start' => 20.5,
        'meal_night_snack_end' => 21.5,
        'meal_night_breakfast_start' => 5.5,
        'meal_night_breakfast_end' => 6.5,
    ];

    public function up(): void
    {
        $firstVigencies = DB::table('hr_payroll_parameters')
            ->select('tenant_id', DB::raw('min(effective_from) as first_from'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($firstVigencies as $tenant) {
            foreach (self::KEYS as $key => $value) {
                $exists = DB::table('hr_payroll_parameters')->where('tenant_id', $tenant->tenant_id)->where('key', $key)->exists();

                if ($exists) {
                    continue;
                }

                DB::table('hr_payroll_parameters')->insert([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->tenant_id,
                    'key' => $key,
                    'value' => $value,
                    'effective_from' => $tenant->first_from,
                    'effective_to' => null,
                    'notes' => 'Auxilio de alimentación: valor inicial.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('hr_payroll_parameters')->whereIn('key', array_keys(self::KEYS))->delete();
    }
};
