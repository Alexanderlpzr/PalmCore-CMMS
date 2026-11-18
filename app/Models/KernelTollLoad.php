<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\KernelTollLoadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Un cargue de almendra mandado a maquila: un tercero la procesa y devuelve torta (que se
 * vende) y un estimado de aceite. Se paga la maquila y el flete; la torta lo compensa.
 * Una fila de la tabla «MAQUILA» de la hoja «Almendra y PKO» del Excel.
 */
#[Fillable([
    'tenant_id',
    'loaded_on',
    'plate',
    'remission_number',
    'unloaded_on',
    'departure_weight_tons',
    'arrival_weight_tons',
    'processed_kg',
    'oil_kg',
    'cake_kg',
    'toll_amount',
    'freight_amount',
    'cake_price_per_ton',
    'notes',
    'import_key',
    'created_by',
])]
class KernelTollLoad extends BaseModel
{
    /** @use HasFactory<KernelTollLoadFactory> */
    use HasFactory;

    /** La merma (kg): lo que llegó menos lo que entró a proceso. Negativa si entró más. */
    public function shrinkageKg(): ?int
    {
        return $this->arrival_weight_tons === null || $this->processed_kg === null
            ? null
            : (int) round((float) $this->arrival_weight_tons * 1000) - $this->processed_kg;
    }

    /** Lo que vale la torta que devolvió: toneladas × precio. */
    public function cakeIncome(): ?float
    {
        return $this->cake_kg === null || $this->cake_price_per_ton === null
            ? null
            : round($this->cake_kg / 1000 * (float) $this->cake_price_per_ton, 2);
    }

    /**
     * Torta menos maquila y flete: lo que dejó el cargue. Sin el precio de la maquila
     * todavía no se sabe: contar la torta sola lo haría parecer ganancia.
     */
    public function balance(): ?float
    {
        $income = $this->cakeIncome();

        return $income === null || $this->toll_amount === null
            ? null
            : round($income - (float) $this->toll_amount - (float) ($this->freight_amount ?? 0), 2);
    }

    /** El rendimiento en aceite: aceite estimado sobre lo que entró a proceso. */
    public function oilYield(): ?float
    {
        return $this->processed_kg && $this->oil_kg !== null ? $this->oil_kg / $this->processed_kg : null;
    }

    public function cakeYield(): ?float
    {
        return $this->processed_kg && $this->cake_kg !== null ? $this->cake_kg / $this->processed_kg : null;
    }

    protected function casts(): array
    {
        return [
            'loaded_on' => 'date',
            'unloaded_on' => 'date',
            'departure_weight_tons' => 'decimal:2',
            'arrival_weight_tons' => 'decimal:2',
            'processed_kg' => 'integer',
            'oil_kg' => 'integer',
            'cake_kg' => 'integer',
            'toll_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'cake_price_per_ton' => 'decimal:2',
        ];
    }
}
