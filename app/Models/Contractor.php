<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\ContractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quien ejecuta el trabajo sin estar en la nómina.
 *
 * A contractor is not a User: he has no account, no login and no ability. He is a
 * company the plant hires, and the OT has to be able to say so — on the printed
 * programme and in the cost.
 */
#[Fillable([
    'tenant_id',
    'name',
    'tax_id',
    'specialty',
    'contact_name',
    'contact_phone',
    'contact_email',
    'hourly_rate',
    'currency_code',
    'is_active',
    'notes',
])]
class Contractor extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ContractorFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrderAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderContractor::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hourly_rate' => 'decimal:2',
        ];
    }
}
