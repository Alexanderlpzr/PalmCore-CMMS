<?php

namespace App\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Enums\SubscriptionStatus;
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
    'address',
    'country_code',
    'timezone',
    'locale',
    'subscription_plan',
    'subscription_status',
    'subscription_expires_at',
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

    // ── Subscription helpers ──────────────────────────────────────────────────

    /**
     * Derives the tenant's operational access state.
     * If the stored status is trial/active but the subscription has expired, the
     * effective status is automatically downgraded to read_only so Super Admins
     * do not need to manually update the status on every expiry date.
     */
    public function effectiveSubscriptionStatus(): SubscriptionStatus
    {
        $stored = $this->subscription_status ?? SubscriptionStatus::Active;

        if (
            $stored->allowsMutations()
            && $this->subscription_expires_at !== null
            && $this->subscription_expires_at->isPast()
        ) {
            return SubscriptionStatus::ReadOnly;
        }

        return $stored;
    }

    /** Days until the subscription expires (negative when already expired). */
    public function daysUntilExpiry(): ?int
    {
        if ($this->subscription_expires_at === null) {
            return null;
        }

        // Use today() (midnight) for whole-day arithmetic: subscription_expires_at is a date column.
        return (int) today()->diffInDays($this->subscription_expires_at, false);
    }

    /** True when the subscription expires within $withinDays days and is not yet expired. */
    public function isExpiringSoon(int $withinDays = 30): bool
    {
        $days = $this->daysUntilExpiry();

        return $days !== null && $days >= 0 && $days <= $withinDays;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'subscription_status' => SubscriptionStatus::class,
            'subscription_expires_at' => 'date',
            'settings' => 'array',
        ];
    }
}
