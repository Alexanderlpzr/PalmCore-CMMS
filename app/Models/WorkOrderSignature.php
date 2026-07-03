<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'user_id',
    'signature_type',
    'signed_at',
    'notes',
    'image_path',
])]
class WorkOrderSignature extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    // No soft deletes — signatures are permanent audit records

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'signature_type' => WorkOrderSignatureType::class,
            'signed_at' => 'datetime',
        ];
    }
}
