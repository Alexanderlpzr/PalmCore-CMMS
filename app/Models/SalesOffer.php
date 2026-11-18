<?php

namespace App\Models;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\SalesOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una oferta mercantil: el contrato con que un cliente compra cierta cantidad de toneladas.
 * Cada despacho que la lleva descuenta su peso de llegada del saldo, y la oferta se cierra
 * sola cuando le queda una tonelada o menos —la tolerancia del peso, como en el Excel—.
 */
#[Fillable([
    'tenant_id',
    'customer_id',
    'product',
    'number',
    'contracted_tons',
    'signed_on',
    'notes',
    'nearly_closed_notified_at',
    'created_by',
])]
class SalesOffer extends BaseModel
{
    /** @use HasFactory<SalesOfferFactory> */
    use HasFactory;

    /** Lo que puede quedar sin despachar y aun así darse por cumplida (tn). */
    public const CLOSING_TOLERANCE_TONS = 1.0;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(SalesDispatch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Las toneladas ya recibidas por el cliente y cuántos cargues lleva, para la lista. */
    public function scopeWithExecution(Builder $query): Builder
    {
        return $query
            ->withSum('dispatches as delivered_tons', 'arrival_weight_tons')
            ->withCount('dispatches')
            ->withMin('dispatches as first_loaded_on', 'loaded_on')
            ->withMax('dispatches as last_loaded_on', 'loaded_on');
    }

    /** Todavía le queda más de la tolerancia por despachar. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereRaw('contracted_tons - '.self::deliveredSql().' > ?', [self::CLOSING_TOLERANCE_TONS]);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereRaw('contracted_tons - '.self::deliveredSql().' <= ?', [self::CLOSING_TOLERANCE_TONS]);
    }

    private static function deliveredSql(): string
    {
        return '(select coalesce(sum(d.arrival_weight_tons), 0) from sales_dispatches d '
            .'where d.sales_offer_id = sales_offers.id and d.deleted_at is null)';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Peso de llegada de sus despachos: lo que cuenta contra el contrato. */
    public function deliveredTons(): float
    {
        return (float) ($this->delivered_tons ?? $this->dispatches()->sum('arrival_weight_tons'));
    }

    /** Lo que falta; negativo si se despachó de más. */
    public function remainingTons(): float
    {
        return round((float) $this->contracted_tons - $this->deliveredTons(), 2);
    }

    public function executedRatio(): ?float
    {
        return (float) $this->contracted_tons > 0 ? $this->deliveredTons() / (float) $this->contracted_tons : null;
    }

    public function isClosed(): bool
    {
        return $this->remainingTons() <= self::CLOSING_TOLERANCE_TONS;
    }

    /** «4800016484 · Acepalma · saldo 120 tn», para escogerla en el despacho. */
    public function optionLabel(): string
    {
        $remaining = number_format($this->remainingTons(), 2, ',', '.');

        return "{$this->number} · {$this->customer?->name} · saldo {$remaining} tn";
    }

    protected function casts(): array
    {
        return [
            'product' => CommercialProduct::class,
            'contracted_tons' => 'decimal:2',
            'signed_on' => 'date',
            'nearly_closed_notified_at' => 'datetime',
        ];
    }
}
