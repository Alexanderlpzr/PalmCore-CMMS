<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El tercero que ejecutó esta OT, y lo que se pactó por ella.
 *
 * `agreed_cost` is frozen here and not read from the contractor's rate card: what
 * a job cost in June cannot change because somebody edits a rate in September.
 */
#[Fillable([
    'tenant_id',
    'work_order_id',
    'contractor_id',
    'scope',
    'agreed_cost',
    'currency_code',
    'invoice_number',
    'notes',
])]
class WorkOrderContractor extends Model
{
    use BelongsToTenant;
    use HasUuids;

    // No soft deletes — removal is operational

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    protected function casts(): array
    {
        return [
            'agreed_cost' => 'decimal:2',
        ];
    }
}
