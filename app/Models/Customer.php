<?php

namespace App\Models;

use App\Domain\Commercial\Enums\Market;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un cliente que compra aceite o palmiste: Manuelita, Acepalma, Cargill…
 *
 * Los alias son los otros nombres con que aparece en los Excel («Bio D» es BioD, «Golden
 * Agri» es Herbert - Golden Agri): el importador los reconoce sin crear un cliente nuevo.
 */
#[Fillable([
    'tenant_id',
    'name',
    'market',
    'payment_terms_days',
    'tax_id',
    'aliases',
    'is_active',
    'notes',
])]
class Customer extends BaseModel
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public function offers(): HasMany
    {
        return $this->hasMany(SalesOffer::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(SalesDispatch::class);
    }

    /** El nombre y sus alias, escritos para compararlos sin tildes, mayúsculas ni espacios. */
    public function matchKeys(): array
    {
        return collect([$this->name, ...($this->aliases ?? [])])
            ->map(fn (string $name): string => self::matchKey($name))
            ->unique()
            ->values()
            ->all();
    }

    public static function matchKey(string $name): string
    {
        return Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->toString();
    }

    protected function casts(): array
    {
        return [
            'market' => Market::class,
            'aliases' => 'array',
            'is_active' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }
}
