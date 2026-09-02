<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La nómina de un período.
 *
 * Mientras está en borrador se puede volver a liquidar cuantas veces haga falta. Al
 * cerrarla las cifras dejan de recalcularse, porque ya se pagaron y se aportaron.
 */
#[Fillable([
    'tenant_id',
    'name',
    'period_start',
    'period_end',
    'status',
    'calculated_at',
    'closed_at',
    'closed_by',
    'total_earned',
    'total_deducted',
    'total_net',
    'employee_count',
    'notes',
])]
class PayrollRun extends BaseModel
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    protected $table = 'hr_payroll_runs';

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PayrollRunStatus::Borrador);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /** Los renglones que traen algo que revisar antes de pagar. */
    public function entriesWithWarnings(): HasMany
    {
        return $this->entries()->whereNotNull('warnings');
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => PayrollRunStatus::class,
            'calculated_at' => 'datetime',
            'closed_at' => 'datetime',
            'total_earned' => 'decimal:2',
            'total_deducted' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
        ];
    }
}
