<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

class TenantUser extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'tenant_users';

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    protected function casts(): array
    {
        return [
            'is_primary_tenant' => 'boolean',
            'is_owner' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
