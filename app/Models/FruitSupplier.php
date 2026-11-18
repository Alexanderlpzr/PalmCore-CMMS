<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\FruitSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un proveedor de fruta de palma: Soagrocol, Palmeras del Carmen, Guariapalma… No es el
 * {@see Supplier} del almacén, que vende repuestos.
 */
#[Fillable([
    'tenant_id',
    'name',
    'aliases',
    'is_active',
    'sort_order',
    'notes',
])]
class FruitSupplier extends BaseModel
{
    /** @use HasFactory<FruitSupplierFactory> */
    use HasFactory;

    public function receptions(): HasMany
    {
        return $this->hasMany(FruitReception::class);
    }

    /** El nombre y sus alias, escritos para compararlos sin tildes ni espacios. */
    public function matchKeys(): array
    {
        return collect([$this->name, ...($this->aliases ?? [])])
            ->map(fn (string $name): string => Customer::matchKey($name))
            ->unique()
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
