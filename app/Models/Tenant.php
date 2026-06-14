<?php

namespace App\Models;

use App\Domain\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

// Tenant does NOT extend BaseModel — BaseModel includes BelongsToTenant, which would create
// a circular scope: tenants filtering themselves by tenant_id.
#[Fillable([
    'name',
    'slug',
    'tax_id',
    'contact_email',
    'contact_phone',
    'country_code',
    'timezone',
    'locale',
    'subscription_plan',
    'is_active',
    'logo_path',
    'settings',
])]
class Tenant extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->using(TenantUser::class)
            ->withPivot('is_primary_tenant', 'is_owner', 'joined_at', 'invited_by')
            ->withTimestamps();
    }

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'subscription_expires_at' => 'date',
            'settings' => 'array',
        ];
    }
}
