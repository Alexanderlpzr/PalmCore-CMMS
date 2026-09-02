<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\PayrollParameterVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tramo de vigencia de un parámetro: «el recargo dominical valió 0,80 desde esta
 * fecha hasta esta otra».
 *
 * El modelo se llama versión y no parámetro porque eso es: la fila no es «el recargo
 * dominical», es «lo que valía el recargo dominical en ese rango». Cambiar el valor
 * nunca es un UPDATE; es cerrar el tramo abierto y abrir uno nuevo. Así, reabrir la
 * nómina de enero en abril sigue liquidando con lo que regía en enero.
 */
#[Fillable([
    'tenant_id',
    'key',
    'value',
    'effective_from',
    'effective_to',
    'notes',
    'created_by',
])]
class PayrollParameterVersion extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayrollParameterVersionFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'hr_payroll_parameters';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** El tramo que regía en una fecha. Solo puede haber uno. */
    public function scopeEffectiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $inner) use ($date): void {
                $inner->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function parameter(): ?PayrollParameter
    {
        return PayrollParameter::tryFrom($this->key);
    }

    public function formattedValue(): string
    {
        return $this->parameter()?->unit()->format((float) $this->value)
            ?? (string) $this->value;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
