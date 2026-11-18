<?php

namespace App\Models;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Enums\Market;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\SalesDispatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cargue de aceite o de palmiste: una fila de la hoja «Despacho 2026». Sale de la planta
 * con su peso y su acidez, el cliente lo recibe y lo liquida, el contador lo factura y el
 * cliente lo paga.
 *
 * El estado no se escoge: sale de los datos (ver {@see self::resolveStatus()}). Lo que
 * cuenta como despachado —para la oferta y para el cliente— es el peso de llegada.
 */
#[Fillable([
    'tenant_id',
    'product',
    'loaded_on',
    'load_number',
    'plate',
    'remission_number',
    'customer_id',
    'sales_offer_id',
    'carrier_id',
    'market',
    'departure_weight_tons',
    'departure_acidity',
    'freight_rate_per_ton',
    'unloaded_on',
    'receipt_number',
    'arrival_weight_tons',
    'arrival_acidity',
    'settlement_amount',
    'quality_bonus',
    'billed_freight',
    'invoice_number',
    'invoiced_on',
    'invoice_amount',
    'paid_on',
    'status',
    'notes',
    'import_key',
    'created_by',
])]
class SalesDispatch extends BaseModel
{
    /** @use HasFactory<SalesDispatchFactory> */
    use HasFactory;

    /** Acidez por encima de esto se marca en rojo, como en la hoja de tanques. */
    public const ACIDITY_LIMIT = 5.0;

    protected static function booted(): void
    {
        static::saving(function (self $dispatch): void {
            $dispatch->status = $dispatch->resolveStatus();
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(SalesOffer::class, 'sales_offer_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Status ────────────────────────────────────────────────────────────────

    /**
     * Con fecha de pago, pagado; con factura, por cobrar; con peso o fecha de llegada, por
     * facturar; si no, en tránsito.
     *
     * Lo importado del Excel puede venir «Pagado» sin fecha de pago (la hoja tiene 293
     * pagados y 188 fechas): ese pagado se respeta mientras siga facturado y nadie haya
     * borrado una fecha de pago.
     */
    public function resolveStatus(): DispatchStatus
    {
        $hasInvoice = filled($this->invoice_number);
        $wasPaidWithoutDate = ($this->exists ? $this->getOriginal('status') : $this->status) === DispatchStatus::Pagado;

        return match (true) {
            $this->paid_on !== null => DispatchStatus::Pagado,
            $hasInvoice && $wasPaidWithoutDate && $this->getOriginal('paid_on') === null => DispatchStatus::Pagado,
            $hasInvoice => DispatchStatus::Facturado,
            $this->arrival_weight_tons !== null || $this->unloaded_on !== null => DispatchStatus::Descargado,
            default => DispatchStatus::Cargado,
        };
    }

    // ── Cifras ────────────────────────────────────────────────────────────────

    /** Lo que se perdió en el camino (tn): salida menos llegada. */
    public function weightDifference(): ?float
    {
        return $this->arrival_weight_tons === null ? null : round((float) $this->departure_weight_tons - (float) $this->arrival_weight_tons, 2);
    }

    /** La merma como fracción del peso de salida. */
    public function shrinkageRatio(): ?float
    {
        $difference = $this->weightDifference();

        return $difference === null || (float) $this->departure_weight_tons <= 0 ? null : $difference / (float) $this->departure_weight_tons;
    }

    /** Las toneladas sobre las que se paga el flete: las que llegaron, o las que salieron si aún no llega. */
    public function freightTons(): float
    {
        return (float) ($this->arrival_weight_tons ?? $this->departure_weight_tons);
    }

    /** Lo que cuesta el transporte: tarifa por tonelada × toneladas. */
    public function freightCost(): ?float
    {
        return $this->freight_rate_per_ton === null ? null : round((float) $this->freight_rate_per_ton * $this->freightTons(), 2);
    }

    /** Valor de la factura menos el flete, por tonelada que salió: lo que de verdad se cobró. */
    public function netPricePerTon(): ?float
    {
        if ($this->invoice_amount === null || (float) $this->departure_weight_tons <= 0) {
            return null;
        }

        return ((float) $this->invoice_amount - (float) ($this->freightCost() ?? 0)) / (float) $this->departure_weight_tons;
    }

    /** Lo que la factura debería decir según la liquidación del cliente: total + bonificación + flete cobrado. */
    public function expectedInvoiceAmount(): ?float
    {
        if ($this->settlement_amount === null) {
            return null;
        }

        return (float) $this->settlement_amount + (float) ($this->quality_bonus ?? 0) + (float) ($this->billed_freight ?? 0);
    }

    /** Factura menos lo liquidado: la «Diferencia Valor» que el Excel dejaba en blanco. */
    public function invoiceDifference(): ?float
    {
        $expected = $this->expectedInvoiceAmount();

        return $expected === null || $this->invoice_amount === null ? null : round((float) $this->invoice_amount - $expected, 2);
    }

    public function hasHighAcidity(): bool
    {
        return (float) ($this->arrival_acidity ?? $this->departure_acidity ?? 0) > self::ACIDITY_LIMIT;
    }

    /** Hoy en la planta, como fecha (el servidor corre en UTC). */
    public static function today(): string
    {
        $timezone = filament()->getTenant()?->plantTimezone() ?? 'America/Bogota';

        return now($timezone)->toDateString();
    }

    /** «XIC607 · 09/01/2026 · Manuelita». */
    public function label(): string
    {
        return "{$this->plate} · {$this->loaded_on?->format('d/m/Y')} · {$this->customer?->name}";
    }

    protected function casts(): array
    {
        return [
            'product' => CommercialProduct::class,
            'status' => DispatchStatus::class,
            'market' => Market::class,
            'loaded_on' => 'date',
            'unloaded_on' => 'date',
            'invoiced_on' => 'date',
            'paid_on' => 'date',
            'departure_weight_tons' => 'decimal:2',
            'departure_acidity' => 'decimal:2',
            'arrival_weight_tons' => 'decimal:2',
            'arrival_acidity' => 'decimal:2',
            'freight_rate_per_ton' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'quality_bonus' => 'decimal:2',
            'billed_freight' => 'decimal:2',
            'invoice_amount' => 'decimal:2',
        ];
    }
}
