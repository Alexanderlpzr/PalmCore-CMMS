<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\PayrollConceptType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PayrollConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Un concepto del desprendible y, sobre todo, a qué bases suma.
 *
 * Esta es la tabla que evita más pleitos del módulo. El libro de la extractora calcula
 * cuatro bases distintas y ninguna coincide con otra: el auxilio de transporte entra a
 * la base de prima pero no al IBC; las horas extras entran a prima pero no a la base de
 * vacaciones; la bonificación constitutiva entra al IBC y la no constitutiva no. Ese
 * mapa cambia por convención colectiva, por pacto y cada vez que se inventa un concepto
 * nuevo, así que no puede vivir cableado en PHP.
 */
#[Fillable([
    'tenant_id',
    'code',
    'name',
    'type',
    'counts_ibc_health',
    'counts_ibc_pension',
    'counts_severance_base',
    'counts_vacation_base',
    'is_active',
    'sort_order',
    'notes',
])]
class PayrollConcept extends BaseModel
{
    /** @use HasFactory<PayrollConceptFactory> */
    use HasFactory;

    protected $table = 'hr_payroll_concepts';

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', PayrollConceptType::Devengado);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'type' => PayrollConceptType::class,
            'counts_ibc_health' => 'boolean',
            'counts_ibc_pension' => 'boolean',
            'counts_severance_base' => 'boolean',
            'counts_vacation_base' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
