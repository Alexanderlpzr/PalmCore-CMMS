<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Enums\Market;
use App\Domain\Commercial\Services\Concerns\ReadsCommercialWorkbook;
use App\Models\Carrier;
use App\Models\Customer;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trae a Fronda el libro «Proyeccion_vs_Despacho_El Pajuil.xlsx» de la Directora
 * Administrativa: los clientes, las transportadoras, las ofertas mercantiles y los cargues.
 *
 *  - «Despacho 2026» (encabezado en la fila de «Fecha Cargue»): un despacho de CPO por fila.
 *  - «Almendra y PKO (Maquila)», tabla «VENTA PALMISTE»: un despacho de PKO por fila. La
 *    maquila (torta) no es una venta y no se importa.
 *  - «Ofertas Mercantiles»: cliente, número y toneladas de cada oferta.
 *  - «Proyección Mensual»: de ahí sale si un cliente es nacional o de exportación.
 *
 * Lo que se arregla al importar, porque el Excel lo traía así:
 *  - Las ofertas que los despachos usan y nunca se anotaron en su hoja se crean con las
 *    toneladas de la columna «Tn Oferta», y se avisa cuáles fueron.
 *  - Un número de recibo escrito en la columna de la transportadora («134392-1») pasa a
 *    ser el recibo.
 *  - «Pagado» sin fecha de pago entra pagado; «Pagado» sin factura, no (queda por facturar).
 *  - Los números que Excel guardó como decimales («4800016340.0») se leen sin el «.0».
 *
 * Cada cargue guarda una huella de sus datos de salida: importar el mismo libro otra vez
 * no repite ninguno, ni pisa lo que ya se corrigió en Fronda.
 */
class DispatchWorkbookImporter
{
    use ReadsCommercialWorkbook;

    /** Cómo se llaman los clientes en otros archivos, como los homologó el propio Excel. */
    private const KNOWN_ALIASES = [
        'BioD' => ['Bio D'],
        'Herbert - Golden Agri' => ['Golden Agri'],
        'Palmar de Altamira' => ['Altamira'],
    ];

    private string $tenantId;

    /** @var Collection<int, Customer> */
    private Collection $customers;

    /** @var array<string, Carrier> */
    private array $carriers = [];

    /** @var array<string, SalesOffer> */
    private array $offers = [];

    /** @var array<string, string> cliente (clave) → mercado, según «Proyección Mensual» */
    private array $markets = [];

    /** @var array{dispatches: int, offers: int, customers: int, carriers: int, skipped: int, warnings: list<string>} */
    private array $summary;

    /**
     * @return array{dispatches: int, offers: int, customers: int, carriers: int, skipped: int, warnings: list<string>}
     */
    public function import(string $path, string $tenantId, ?User $importedBy): array
    {
        $this->tenantId = $tenantId;
        $this->summary = ['dispatches' => 0, 'offers' => 0, 'customers' => 0, 'carriers' => 0, 'skipped' => 0, 'warnings' => []];

        $sheets = $this->sheets($path);
        $dispatchSheet = $this->sheet($sheets, 'despacho');

        if ($dispatchSheet === null) {
            throw new \DomainException('El libro no tiene la hoja «Despacho»: ¿es el Excel de proyección vs despacho?');
        }

        $this->customers = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->get();
        Carrier::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()
            ->each(fn (Carrier $carrier) => $this->carriers[Customer::matchKey($carrier->name)] = $carrier);
        SalesOffer::withoutGlobalScopes()->with('customer')->where('tenant_id', $tenantId)->get()
            ->each(fn (SalesOffer $offer) => $this->offers[$offer->number] = $offer);

        DB::transaction(function () use ($sheets, $dispatchSheet, $importedBy): void {
            $this->readMarkets($this->sheet($sheets, 'proyeccion mensual') ?? []);
            $this->importOffers($this->sheet($sheets, 'ofertas') ?? [], $importedBy);

            $existingKeys = SalesDispatch::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $this->tenantId)
                ->whereNotNull('import_key')
                ->pluck('import_key')
                ->flip()
                ->all();

            $created = [];
            $paidWithoutInvoice = 0;
            $receiptsInCarrier = 0;
            $missingOffers = [];

            $rows = [
                ...array_map(fn (array $row): array => [CommercialProduct::Cpo, $this->cpoRow($row)], $this->table($dispatchSheet, 'fecha cargue')),
                ...array_map(fn (array $row): array => [CommercialProduct::Pko, $this->pkoRow($row)], $this->table($this->sheet($sheets, 'almendra') ?? [], 'fecha cargue', 'venta palmiste')),
            ];

            foreach ($rows as [$product, $row]) {
                if ($row['loaded_on'] === null || blank($row['customer']) || $row['departure'] === null) {
                    continue;
                }

                $key = $this->importKey($product, $row);
                $occurrence = ($created[$key] ?? 0) + 1;
                $created[$key] = $occurrence;
                $key = $occurrence > 1 ? "{$key}#{$occurrence}" : $key;

                if (isset($existingKeys[$key])) {
                    $this->summary['skipped']++;

                    continue;
                }

                $customer = $this->customer($row['customer'], $row['market']);

                if ($row['carrier'] !== null && preg_match('/^\d+(-\d+)?$/', self::code($row['carrier']))) {
                    $row['receipt'] ??= self::code($row['carrier']);
                    $row['carrier'] = null;
                    $receiptsInCarrier++;
                }

                $offer = null;

                if ($row['offer'] !== null) {
                    $offer = $this->offers[$row['offer']] ?? null;

                    if ($offer === null) {
                        $offer = $this->createOffer($customer, $product, $row['offer'], $row['offer_tons'] ?? $row['departure'], $importedBy, 'Creada al importar los despachos: no estaba en la hoja de ofertas.');
                        $missingOffers[] = $row['offer'];
                    } elseif ($offer->customer_id !== $customer->id) {
                        $this->summary['warnings'][] = "La oferta {$row['offer']} es de otro cliente; el cargue de {$row['plate']} del {$row['loaded_on']->format('d/m/Y')} quedó sin oferta.";
                        $offer = null;
                    } elseif ($offer->product !== $product && $offer->wasRecentlyCreated) {
                        // La hoja de ofertas no dice el producto: la que llevan cargues de palmiste es de palmiste.
                        $offer->update(['product' => $product->value]);
                    }
                }

                $paid = $row['paid'] && filled($row['invoice']);
                $paidWithoutInvoice += $row['paid'] && blank($row['invoice']) ? 1 : 0;

                $dispatch = new SalesDispatch([
                    'tenant_id' => $this->tenantId,
                    'product' => $product->value,
                    'loaded_on' => $row['loaded_on']->toDateString(),
                    'load_number' => $row['load_number'],
                    'plate' => $row['plate'] ?? 'SIN PLACA',
                    'remission_number' => $row['remission'],
                    'customer_id' => $customer->id,
                    'sales_offer_id' => $offer?->id,
                    'carrier_id' => $row['carrier'] === null ? null : $this->carrier($row['carrier'])->id,
                    'market' => ($row['market'] ?? $customer->market)->value,
                    'departure_weight_tons' => $row['departure'],
                    'departure_acidity' => $row['departure_acidity'],
                    'freight_rate_per_ton' => $row['freight_rate'],
                    'unloaded_on' => $row['unloaded_on']?->toDateString(),
                    'receipt_number' => $row['receipt'],
                    'arrival_weight_tons' => $row['arrival'],
                    'arrival_acidity' => $row['arrival_acidity'],
                    'settlement_amount' => $row['settlement'],
                    'quality_bonus' => $row['bonus'],
                    'billed_freight' => $row['billed_freight'],
                    'invoice_number' => $row['invoice'],
                    'invoiced_on' => filled($row['invoice']) ? $row['invoiced_on']?->toDateString() : null,
                    'invoice_amount' => filled($row['invoice']) ? $row['invoice_amount'] : null,
                    'paid_on' => $paid ? $row['paid_on']?->toDateString() : null,
                    'status' => $paid ? DispatchStatus::Pagado->value : DispatchStatus::Cargado->value,
                    'import_key' => $key,
                    'created_by' => $importedBy?->id,
                ]);
                $dispatch->save();

                $this->summary['dispatches']++;
            }

            if ($missingOffers !== []) {
                $missingOffers = array_values(array_unique($missingOffers));
                $this->summary['warnings'][] = count($missingOffers).' ofertas usadas en los despachos no estaban en la hoja de ofertas y se crearon con las toneladas de «Tn Oferta»: '.implode(', ', $missingOffers).'.';
            }

            if ($receiptsInCarrier > 0) {
                $this->summary['warnings'][] = "{$receiptsInCarrier} cargues tenían el número de recibo en la columna de la transportadora: quedó como recibo.";
            }

            if ($paidWithoutInvoice > 0) {
                $this->summary['warnings'][] = "{$paidWithoutInvoice} cargues decían «Pagado» sin número de factura: quedaron por facturar.";
            }
        });

        return $this->summary;
    }

    // ── Hojas ─────────────────────────────────────────────────────────────────

    /**
     * Los clientes de la proyección de ventas con su destino. Se crean aunque todavía no
     * tengan despachos (Sigra): son los clientes del año.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function readMarkets(array $rows): void
    {
        foreach ($this->table($rows, 'cliente') as $cells) {
            $market = self::market($cells['B'] ?? null);
            $name = self::text($cells['A'] ?? null);

            if ($market !== null && $name !== null) {
                $this->markets[Customer::matchKey($name)] = $market->value;
                $this->customer($name, $market);
            }
        }
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function importOffers(array $rows, ?User $importedBy): void
    {
        foreach ($this->table($rows, 'cliente') as $cells) {
            $number = self::code($cells['B'] ?? null);
            $tons = self::number($cells['C'] ?? null);

            if ($number === null || $tons === null || blank($cells['A'] ?? null) || isset($this->offers[$number])) {
                continue;
            }

            $this->createOffer($this->customer((string) $cells['A'], null), CommercialProduct::Cpo, $number, $tons, $importedBy);
        }
    }

    // ── Filas ─────────────────────────────────────────────────────────────────

    /**
     * Una fila de «Despacho 2026».
     *
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    private function cpoRow(array $cells): array
    {
        return [
            'loaded_on' => self::date($cells['A'] ?? null),
            'load_number' => self::code($cells['B'] ?? null),
            'plate' => self::plate($cells['C'] ?? null),
            'unloaded_on' => self::date($cells['E'] ?? null),
            'remission' => self::code($cells['F'] ?? null),
            'receipt' => self::code($cells['G'] ?? null),
            'customer' => self::text($cells['H'] ?? null),
            'carrier' => self::text($cells['I'] ?? null),
            'invoice' => self::code($cells['J'] ?? null),
            'invoiced_on' => self::date($cells['K'] ?? null),
            'departure' => self::positive($cells['L'] ?? null),
            'arrival' => self::positive($cells['M'] ?? null),
            'billed_freight' => self::nonZero($cells['P'] ?? null),
            'bonus' => self::nonZero($cells['Q'] ?? null),
            'settlement' => self::nonZero($cells['R'] ?? null),
            'invoice_amount' => self::nonZero($cells['S'] ?? null),
            'paid' => Str::lower(trim((string) ($cells['U'] ?? ''))) === 'pagado',
            'paid_on' => self::date($cells['V'] ?? null),
            'offer' => self::code($cells['W'] ?? null),
            'offer_tons' => self::positive($cells['X'] ?? null),
            'market' => self::market($cells['Z'] ?? null),
            'freight_rate' => self::positive($cells['AA'] ?? null),
            'departure_acidity' => self::positive($cells['AD'] ?? null),
            'arrival_acidity' => self::positive($cells['AE'] ?? null),
        ];
    }

    /**
     * Una fila de la tabla «VENTA PALMISTE». No trae cliente: todo el palmiste de 2026 es
     * de exportación por oferta, y el cliente sale de la oferta —o de «Acepalma», el único
     * comprador de PKO en la proyección— cuando la oferta no está anotada.
     *
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    private function pkoRow(array $cells): array
    {
        $offer = self::code($cells['O'] ?? null);

        return [
            'loaded_on' => self::date($cells['A'] ?? null),
            'load_number' => null,
            'plate' => self::plate($cells['B'] ?? null),
            'unloaded_on' => self::date($cells['D'] ?? null),
            'remission' => self::code($cells['E'] ?? null),
            'receipt' => null,
            'customer' => $offer !== null && isset($this->offers[$offer]) ? $this->offers[$offer]->customer?->name ?? 'Acepalma' : 'Acepalma',
            'carrier' => self::text($cells['F'] ?? null),
            'invoice' => self::code($cells['G'] ?? null),
            'invoiced_on' => self::date($cells['H'] ?? null),
            'departure' => self::positive($cells['I'] ?? null),
            'arrival' => self::positive($cells['J'] ?? null),
            'billed_freight' => null,
            'bonus' => null,
            'settlement' => null,
            'invoice_amount' => self::nonZero($cells['L'] ?? null),
            'paid' => Str::lower(trim((string) ($cells['M'] ?? ''))) === 'pagado',
            'paid_on' => self::date($cells['N'] ?? null),
            'offer' => $offer,
            'offer_tons' => self::positive($cells['P'] ?? null),
            'market' => self::market($cells['R'] ?? null),
            'freight_rate' => self::positive($cells['S'] ?? null),
            'departure_acidity' => null,
            'arrival_acidity' => null,
        ];
    }

    /** La huella del cargue: lo que se sabe cuando sale de la planta y no cambia después. */
    private function importKey(CommercialProduct $product, array $row): string
    {
        $parts = [
            $product->value,
            $row['loaded_on']->toDateString(),
            Customer::matchKey((string) $row['plate']),
            (string) $row['remission'],
            Customer::matchKey((string) $row['customer']),
            number_format((float) $row['departure'], 2, '.', ''),
            (string) $row['offer'],
            (string) ($row['receipt'] ?? $row['carrier']),
        ];

        return 'xlsx:'.sha1(implode('|', $parts));
    }

    // ── Catálogos ─────────────────────────────────────────────────────────────

    private function customer(string $name, ?Market $market): Customer
    {
        $key = Customer::matchKey($name);
        $found = $this->customers->first(fn (Customer $customer): bool => in_array($key, $customer->matchKeys(), true));

        if ($found !== null) {
            return $found;
        }

        $canonical = collect(self::KNOWN_ALIASES)->search(fn (array $aliases): bool => in_array($key, array_map(Customer::matchKey(...), $aliases), true));
        $name = $canonical === false ? trim($name) : $canonical;

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'market' => $this->markets[Customer::matchKey($name)] ?? $market?->value ?? Market::Nacional->value,
            'aliases' => self::KNOWN_ALIASES[$name] ?? null,
            'is_active' => true,
        ]);

        $this->customers->push($customer);
        $this->summary['customers']++;

        return $customer;
    }

    private function carrier(string $name): Carrier
    {
        $key = Customer::matchKey($name);

        if (! isset($this->carriers[$key])) {
            $this->carriers[$key] = Carrier::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenantId,
                'name' => trim($name),
                'is_active' => true,
            ]);
            $this->summary['carriers']++;
        }

        return $this->carriers[$key];
    }

    private function createOffer(Customer $customer, CommercialProduct $product, string $number, float $tons, ?User $importedBy, ?string $notes = null): SalesOffer
    {
        $offer = SalesOffer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'customer_id' => $customer->id,
            'product' => $product->value,
            'number' => $number,
            'contracted_tons' => $tons,
            'notes' => $notes,
            'created_by' => $importedBy?->id,
        ]);
        $offer->setRelation('customer', $customer);

        $this->summary['offers']++;

        return $this->offers[$number] = $offer;
    }
}
