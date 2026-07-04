<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'is_super_admin'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    // ─── Filament: FilamentUser ───────────────────────────────────────────────

    // Only checks identity — never Spatie permissions, because Filament calls
    // this before ResolveTenant middleware has run (no team context yet).
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    // ─── Filament: HasTenants ────────────────────────────────────────────────

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->tenants()->where('tenants.id', $tenant->getKey())->exists();
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->is_super_admin) {
            return Tenant::all();
        }

        return $this->tenants;
    }

    // ─── Filament: HasAvatar ─────────────────────────────────────────────────

    public function getFilamentAvatarUrl(): ?string
    {
        return file_signed_url(persistent_disk(), $this->profile?->avatar_path);
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->using(TenantUser::class)
            ->withPivot('is_primary_tenant', 'is_owner', 'joined_at', 'invited_by')
            ->withTimestamps();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class)->withDefault();
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Users eligible for operational assignment (technician/supervisor pickers).
     * Excludes Super Administrador and administrative-only roles (administrador-general,
     * compras, almacenista, gerencia) so they never appear as assignable staff.
     */
    public function scopeOperationalStaff(Builder $query): Builder
    {
        return $query
            ->where('is_super_admin', false)
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [
                'tecnico',
                'supervisor',
                'ingeniero-mantenimiento',
            ]));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
