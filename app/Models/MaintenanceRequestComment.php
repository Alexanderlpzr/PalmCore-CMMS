<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'maintenance_request_id',
    'user_id',
    'body',
    'is_internal',
])]
class MaintenanceRequestComment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    // No soft deletes — comments are immutable audit records

    // ── Relationships ─────────────────────────────────────────────────────────

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }
}
