<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\CarrierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** La transportadora que lleva el cargue al cliente: Suracarga, Solutrans, Summa… */
#[Fillable([
    'tenant_id',
    'name',
    'tax_id',
    'is_active',
    'notes',
])]
class Carrier extends BaseModel
{
    /** @use HasFactory<CarrierFactory> */
    use HasFactory;

    public function dispatches(): HasMany
    {
        return $this->hasMany(SalesDispatch::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
