<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'plant_id',
    'code',
    'name',
    'description',
    'sort_order',
    'is_active',
])]
class Area extends BaseModel
{
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * El orden del flujo de proceso, con desempate.
     *
     * `sort_order` ya no es único por planta (M4): dos áreas pueden compartir
     * número sin que eso sea un error. El orden se vuelve determinístico aquí, en
     * la consulta, en vez de prohibiendo el empate en la base.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
