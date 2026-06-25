<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Audit record of a Super Admin impersonation session. Platform-level (not
 * tenant-scoped): it deliberately does not use BelongsToTenant because it spans
 * the impersonator (staff) and a target user that lives inside a tenant.
 */
#[Fillable([
    'impersonator_id',
    'impersonated_user_id',
    'tenant_id',
    'started_at',
    'ended_at',
    'duration_seconds',
    'ip_address',
    'user_agent',
    'reason',
])]
class ImpersonationLog extends Model
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function impersonatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }
}
