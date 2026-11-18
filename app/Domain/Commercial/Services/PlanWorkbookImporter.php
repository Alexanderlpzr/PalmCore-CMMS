<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\Market;
use App\Domain\Commercial\Enums\PlanVersion;
use App\Domain\Commercial\Services\Concerns\ReadsCommercialWorkbook;
use App\Models\AnnualPlan;
use App\Models\Customer;
use App\Models\FruitReception;
use App\Models\FruitSupplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trae el plan del año del libro «Proyeccion_vs_Despacho_El Pajuil.xlsx»:
 *
 *  - «Proyección Fruta»: la fruta por proveedor y mes (columnas B a M), la capacidad de la
 *    planta y la TEA, y la tabla «Entrega real de fruta por proveedor».
 *  - «Proyección Mensual»: las ventas de aceite por cliente (columnas C a N), «Por
 *    concretar», el «Saldo anterior» como inventario inicial y la tabla de PKO.
 *
 * Entra como plan original aprobado, con su reproyección: el Excel ya había pisado los
 * meses pasados con lo real, así que no hay un original más fiel que ese. Si el año ya
 * tiene plan, el plan no se toca. La fruta recibida solo llena los meses que falten.
 */
class PlanWorkbookImporter
{
    use ReadsCommercialWorkbook;

    /** @var Collection<int, FruitSupplier> */
    private Collection $suppliers;

    /** @var Collection<int, Customer> */
    private Collection $customers;

    /** @var array{plan: bool, suppliers: int, customers: int, receptions: int, warnings: list<string>} */
    private array $summary;

    public function __construct(private AnnualPlanService $plans) {}

    /**
     * @return array{plan: bool, suppliers: int, customers: int, receptions: int, warnings: list<string>}
     */
    public function import(string $path, string $tenantId, int $year, ?User $importedBy): array
    {
        $this->summary = ['plan' => false, 'suppliers' => 0, 'customers' => 0, 'receptions' => 0, 'warnings' => []];

        $sheets = $this->sheets($path);
        $fruitSheet = $this->sheet($sheets, 'proyeccion fruta');
        $salesSheet = $this->sheet($sheets, 'proyeccion mensual');

        if ($fruitSheet === null && $salesSheet === null) {
            throw new \DomainException('El libro no tiene las hojas «Proyección Fruta» ni «Proyección Mensual».');
        }

        $this->suppliers = FruitSupplier::withoutGlobalScopes()->where('tenant_id', $tenantId)->get();
        $this->customers = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->get();

        DB::transaction(function () use ($tenantId, $year, $importedBy, $fruitSheet, $salesSheet): void {
            $fruitPlan = $this->monthRows($this->table($fruitSheet ?? [], 'proveedor'), 'B');
            $fruitReal = $this->monthRows($this->table($fruitSheet ?? [], 'proveedor', 'entrega real'), 'B');
            $salesCpo = $this->monthRows($this->table($salesSheet ?? [], 'cliente'), 'C', withMarket: true);
            $salesPko = $this->monthRows($this->table($salesSheet ?? [], 'cliente', 'pko'), 'C', withMarket: true);

            $order = 0;
            foreach (array_keys($fruitPlan + $fruitReal) as $name) {
                $this->supplier($tenantId, $name, ++$order);
            }

            if ($this->plans->find($tenantId, $year, PlanVersion::Original) === null) {
                $plan = $this->plans->createOriginal($tenantId, $year, $importedBy);

                foreach ($fruitPlan as $name => $row) {
                    foreach ($row['months'] as $month => $tons) {
                        $this->cell(fn () => $this->plans->setFruit($plan, $this->supplier($tenantId, $name)->id, $month, $tons), "{$name}, mes {$month}: {$tons} tn de fruta");
                    }
                }

                foreach ([[CommercialProduct::Cpo, $salesCpo], [CommercialProduct::Pko, $salesPko]] as [$product, $rows]) {
                    foreach ($rows as $name => $row) {
                        $customerId = str_starts_with(self::normalize($name), 'por concretar')
                            ? AnnualPlanService::UNASSIGNED
                            : $this->customer($tenantId, $name, $row['market'])->id;

                        foreach ($row['months'] as $month => $tons) {
                            $this->cell(fn () => $this->plans->setSale($plan, $customerId, $product, $month, $tons), "{$name} ({$product->shortLabel()}), mes {$month}: {$tons} tn");
                        }
                    }
                }

                $this->importPlant($plan, $fruitSheet ?? []);
                $this->plans->setOpeningOil($plan, $this->openingOil($salesSheet ?? []));
                $this->plans->approve($plan->refresh(), $importedBy);
                $this->summary['plan'] = true;
            }

            $taken = FruitReception::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('year', $year)
                ->get(['fruit_supplier_id', 'month'])
                ->map(fn (FruitReception $r): string => "{$r->fruit_supplier_id}-{$r->month}")
                ->flip();

            foreach ($fruitReal as $name => $row) {
                $supplier = $this->supplier($tenantId, $name);

                foreach ($row['months'] as $month => $tons) {
                    if ($tons <= 0 || isset($taken["{$supplier->id}-{$month}"])) {
                        continue;
                    }

                    $this->plans->setReception($tenantId, $supplier->id, $year, $month, $tons, $importedBy);
                    $this->summary['receptions']++;
                }
            }
        });

        return $this->summary;
    }

    /** La capacidad (fila «Capacidad Planta») y la TEA (fila «TEA», en fracción o en %). */
    private function importPlant(AnnualPlan $plan, array $rows): void
    {
        foreach ($rows as $cells) {
            $label = is_string($cells['A'] ?? null) ? self::normalize($cells['A']) : '';
            $field = match (true) {
                str_starts_with($label, 'capacidad planta') => 'plant_capacity_tons',
                str_starts_with($label, 'tea') => 'tea_percent',
                default => null,
            };

            if ($field === null) {
                continue;
            }

            foreach (range(1, 12) as $month) {
                $value = self::number($cells[self::letter($month)] ?? null);

                if ($value === null) {
                    continue;
                }

                // La TEA viene como fracción (0,2049): se guarda en porcentaje.
                if ($field === 'tea_percent' && $value < 1) {
                    $value *= 100;
                }

                $this->plans->setMonthParameter($plan, $month, $field, round($value, 2));
            }
        }
    }

    /** El «Saldo anterior» de la hoja de ventas: el aceite con que arranca el año. */
    private function openingOil(array $rows): ?float
    {
        foreach ($rows as $cells) {
            if (is_string($cells['A'] ?? null) && str_starts_with(self::normalize($cells['A']), 'saldo anterior')) {
                return self::number($cells['B'] ?? null);
            }
        }

        return null;
    }

    /**
     * Las filas de una tabla mensual por nombre: doce meses desde la columna $firstMonth.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{months: array<int, float>, market: ?Market}>
     */
    private function monthRows(array $rows, string $firstMonth, bool $withMarket = false): array
    {
        $offset = ord($firstMonth) - ord('A');
        $result = [];

        foreach ($rows as $cells) {
            $name = self::text($cells['A'] ?? null);

            if ($name === null) {
                continue;
            }

            $months = [];

            foreach (range(1, 12) as $month) {
                $tons = self::number($cells[self::letter($offset + $month - 1)] ?? null);

                if ($tons !== null && abs($tons) >= 0.005) {
                    $months[$month] = round($tons, 2);
                }
            }

            $result[$name] = [
                'months' => $months,
                'market' => $withMarket ? $this->marketOf($cells['B'] ?? null) : null,
            ];
        }

        return $result;
    }

    /** Una celda que no entra (toneladas negativas) se avisa en vez de tumbar la importación. */
    private function cell(callable $save, string $what): void
    {
        try {
            $save();
        } catch (\DomainException $e) {
            $this->summary['warnings'][] = "{$what} no se importó: {$e->getMessage()}";
        }
    }

    private function supplier(string $tenantId, string $name, ?int $order = null): FruitSupplier
    {
        $key = Customer::matchKey($name);
        $found = $this->suppliers->first(fn (FruitSupplier $supplier): bool => in_array($key, $supplier->matchKeys(), true));

        if ($found !== null) {
            return $found;
        }

        $supplier = FruitSupplier::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => trim($name),
            'is_active' => true,
            'sort_order' => $order ?? $this->suppliers->count() + 1,
        ]);

        $this->suppliers->push($supplier);
        $this->summary['suppliers']++;

        return $supplier;
    }

    private function customer(string $tenantId, string $name, ?Market $market): Customer
    {
        $key = Customer::matchKey($name);
        $found = $this->customers->first(fn (Customer $customer): bool => in_array($key, $customer->matchKeys(), true));

        if ($found !== null) {
            return $found;
        }

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => trim($name),
            'market' => ($market ?? Market::Nacional)->value,
            'is_active' => true,
        ]);

        $this->customers->push($customer);
        $this->summary['customers']++;

        return $customer;
    }

    private function marketOf(mixed $value): ?Market
    {
        return self::market($value);
    }
}
