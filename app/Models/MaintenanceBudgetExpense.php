<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\MaintenanceBudgetExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un gasto de mantenimiento, registrado a mano (semana a semana). Es lo que se
 * compara contra el presupuesto del mes. Se asocia al mes por su fecha.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'expense_date',
    'amount',
    'category',
    'description',
    'created_by',
])]
class MaintenanceBudgetExpense extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceBudgetExpenseFactory> */
    use HasFactory;

    use HasUuids;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'float',
            'category' => ExpenseCategory::class,
        ];
    }
}
