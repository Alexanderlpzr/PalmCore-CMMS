<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Services\Concerns\ReadsCommercialWorkbook;
use App\Models\Customer;
use App\Models\KernelInventoryCount;
use App\Models\KernelTollLoad;
use App\Models\OilTank;
use App\Models\OilTankReading;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Trae del libro de la Directora Administrativa lo de la planta:
 *
 *  - «Disponibilidad Aceite»: los niveles y la acidez de los tanques 1 y 2 de cada día
 *    que se midieron (columnas F a I). La producción del Excel no se trae: aquí sale de
 *    los niveles y los despachos.
 *  - «Almendra y PKO (Maquila)», tabla MAQUILA: cada cargue de almendra con su torta,
 *    su aceite estimado y sus costos.
 *  - «Inventario Palmiste PKO»: los cortes de palmiste y torta.
 *
 * Lo ya importado no se repite ni se pisa.
 */
class ProductionWorkbookImporter
{
    use ReadsCommercialWorkbook;

    /**
     * @return array{tank_days: int, toll_loads: int, inventory_counts: int}
     */
    public function import(string $path, string $tenantId, ?User $importedBy): array
    {
        $sheets = $this->sheets($path);
        $summary = ['tank_days' => 0, 'toll_loads' => 0, 'inventory_counts' => 0];

        DB::transaction(function () use ($sheets, $tenantId, $importedBy, &$summary): void {
            $summary['tank_days'] = $this->importTanks($this->sheet($sheets, 'disponibilidad') ?? [], $tenantId, $importedBy);
            $summary['toll_loads'] = $this->importTollLoads($this->sheet($sheets, 'almendra') ?? [], $tenantId, $importedBy);
            $summary['inventory_counts'] = $this->importInventory($this->sheet($sheets, 'inventario palmiste') ?? [], $tenantId, $importedBy);
        });

        return $summary;
    }

    private function importTanks(array $rows, string $tenantId, ?User $importedBy): int
    {
        $days = 0;
        $tanks = [];

        foreach ($this->table($rows, 'fecha') as $cells) {
            $date = self::date($cells['A'] ?? null);

            if ($date === null) {
                continue;
            }

            $levels = ['Tanque 1' => ['F', 'G'], 'Tanque 2' => ['H', 'I']];
            $measured = false;

            foreach ($levels as $name => [$levelColumn, $acidityColumn]) {
                $level = self::number($cells[$levelColumn] ?? null);

                if ($level === null || $level <= 0) {
                    continue;
                }

                $tanks[$name] ??= OilTank::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $name],
                    ['sort_order' => (int) substr($name, -1), 'is_active' => true],
                );

                $exists = OilTankReading::withoutGlobalScopes()->where('oil_tank_id', $tanks[$name]->id)->whereDate('reading_date', $date->toDateString())->exists();

                if (! $exists) {
                    $acidity = self::number($cells[$acidityColumn] ?? null);
                    OilTankReading::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'oil_tank_id' => $tanks[$name]->id,
                        'reading_date' => $date->toDateString(),
                        'level_tons' => round($level, 2),
                        'acidity_percent' => $acidity !== null && $acidity > 0 ? round($acidity, 2) : null,
                        'recorded_by' => $importedBy?->id,
                    ]);
                    $measured = true;
                }
            }

            $days += $measured ? 1 : 0;
        }

        return $days;
    }

    private function importTollLoads(array $rows, string $tenantId, ?User $importedBy): int
    {
        $taken = KernelTollLoad::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('import_key')->pluck('import_key')->flip();
        $count = 0;

        // La primera tabla con «Fecha Cargue» es la de maquila; la de venta de palmiste va después.
        foreach ($this->table($rows, 'fecha cargue') as $cells) {
            $loadedOn = self::date($cells['A'] ?? null);
            $departure = self::positive($cells['F'] ?? null);

            if ($loadedOn === null || $departure === null) {
                continue;
            }

            $plate = self::code($cells['B'] ?? null);
            $remission = self::code($cells['E'] ?? null);
            $key = 'xlsx:'.sha1(implode('|', [$loadedOn->toDateString(), Customer::matchKey((string) $plate), (string) $remission, number_format($departure, 2, '.', '')]));

            if (isset($taken[$key])) {
                continue;
            }

            $kilos = fn (string $column): ?int => ($value = self::number($cells[$column] ?? null)) === null ? null : (int) round($value);

            KernelTollLoad::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'loaded_on' => $loadedOn->toDateString(),
                'plate' => $plate === null ? null : strtoupper(str_replace([' ', '-'], '', $plate)),
                'remission_number' => $remission,
                'unloaded_on' => self::date($cells['D'] ?? null)?->toDateString(),
                'departure_weight_tons' => $departure,
                'arrival_weight_tons' => self::positive($cells['G'] ?? null),
                'processed_kg' => $kilos('I'),
                'oil_kg' => $kilos('J'),
                'cake_kg' => $kilos('K'),
                'toll_amount' => self::positive($cells['M'] ?? null),
                'freight_amount' => self::positive($cells['N'] ?? null),
                'cake_price_per_ton' => self::positive($cells['O'] ?? null),
                'import_key' => $key,
                'created_by' => $importedBy?->id,
            ]);

            $taken[$key] = true;
            $count++;
        }

        return $count;
    }

    private function importInventory(array $rows, string $tenantId, ?User $importedBy): int
    {
        $count = 0;

        foreach ($this->table($rows, 'fecha') as $cells) {
            $date = self::date($cells['A'] ?? null);
            $pko = self::positive($cells['B'] ?? null);
            $cake = self::positive($cells['C'] ?? null);

            if ($date === null || ($pko === null && $cake === null)) {
                continue;
            }

            $exists = KernelInventoryCount::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereDate('counted_on', $date->toDateString())->exists();

            if (! $exists) {
                KernelInventoryCount::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'counted_on' => $date->toDateString(),
                    'pko_tons' => $pko,
                    'cake_tons' => $cake,
                    'recorded_by' => $importedBy?->id,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
