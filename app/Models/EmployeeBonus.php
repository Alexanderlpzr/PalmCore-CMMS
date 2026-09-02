<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\BonusType;
use App\Domain\Shared\Models\BaseModel;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeBonusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una bonificación del trabajador, con su vigencia.
 *
 * El mismo mecanismo cubre la de un solo mes y la que se repite. En el libro actual estas
 * cifras están pegadas a mano y sin fórmula detrás, y son el agujero de auditoría más
 * grande que tiene.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'type',
    'concept',
    'amount',
    'effective_from',
    'effective_to',
    'notes',
])]
class EmployeeBonus extends BaseModel
{
    /** @use HasFactory<EmployeeBonusFactory> */
    use HasFactory;

    protected $table = 'hr_employee_bonuses';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Las bonificaciones que tocan el período.
     *
     * Se usa el solape y no la vigencia en una fecha puntual: una bonificación declarada
     * del 1 al 31 de agosto tiene que entrar en la nómina de agosto completa, y una fecha
     * de corte cualquiera la dejaría dentro o fuera según el día que se elija.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $to)
            ->where(function (Builder $inner) use ($from): void {
                $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            });
    }

    protected function casts(): array
    {
        return [
            'type' => BonusType::class,
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
