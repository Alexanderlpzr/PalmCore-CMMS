<?php

namespace Database\Seeders;

use App\Domain\Energy\Enums\EnergySource;
use App\Models\EnergyMeter;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Los tres contadores de energía de la extractora.
 *
 * Son los mismos tres de la hoja de cálculo, y en el mismo orden en que el operario los
 * lee: red pública, planta eléctrica y turbina.
 *
 * Dos se enlazan al equipo que los genera, para poder cruzar algún día los kWh con las
 * horas de operación de esa máquina. **La red pública se queda sin equipo**, porque no
 * lo tiene: es la acometida de la electrificadora, no un activo que se mantenga. Ese
 * hueco es la razón por la que los contadores no se modelaron como equipos con
 * horómetro.
 *
 * Aditivo e idempotente: nunca borra ni pisa lo que ya esté.
 */
class TenantEnergyMetersSeeder extends Seeder
{
    private const METERS = [
        ['code' => 'ENE-RED', 'name' => 'Red pública', 'source' => EnergySource::Grid, 'equipment' => null, 'sort' => 1],
        ['code' => 'ENE-PLA', 'name' => 'Planta eléctrica', 'source' => EnergySource::Genset, 'equipment' => 'A10SPG.26.01', 'sort' => 2],
        ['code' => 'ENE-TUR', 'name' => 'Turbina', 'source' => EnergySource::Turbine, 'equipment' => 'A10SPG.26.03', 'sort' => 3],
    ];

    public function run(Tenant $tenant, Plant $plant): void
    {
        foreach (self::METERS as $row) {
            $equipmentId = $row['equipment'] === null
                ? null
                : Equipment::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('code', $row['equipment'])
                    ->value('id');

            EnergyMeter::withoutGlobalScopes()->firstOrCreate(
                ['plant_id' => $plant->id, 'code' => $row['code']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $row['name'],
                    'source' => $row['source']->value,
                    'equipment_id' => $equipmentId,
                    'is_active' => true,
                    'sort_order' => $row['sort'],
                ],
            );
        }
    }
}
