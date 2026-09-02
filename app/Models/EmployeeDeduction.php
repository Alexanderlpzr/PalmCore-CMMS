<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeDeductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un descuento que se repite cada mes: seguro funerario, póliza, libranza.
 *
 * Mismo patrón de vigencias que los parámetros: un descuento que terminó no se borra, se
 * cierra. Reabrir la nómina de un mes pasado tiene que volver a aplicarlo tal como estaba.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'concept',
    'amount',
    'effective_from',
    'effective_to',
    'notes',
])]
class EmployeeDeduction extends BaseModel
{
    /** @use HasFactory<EmployeeDeductionFactory> */
    use HasFactory;

    protected $table = 'hr_employee_deductions';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $inner) use ($date): void {
                $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
