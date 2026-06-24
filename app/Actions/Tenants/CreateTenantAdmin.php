<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class CreateTenantAdmin
{
    /**
     * Create (or reuse) a user and make them the administrador-general of the
     * given tenant: attached as primary owner and granted the full admin role.
     *
     * Assumes the tenant's role matrix already exists (see
     * ProvisionTenantBaseStructure). Returns the user.
     */
    public function handle(Tenant $tenant, string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($tenant, $name, $email, $password): User {
            // forceFill: is_super_admin and email_verified_at are guarded against
            // mass assignment, so set them explicitly on a fresh user.
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'is_super_admin' => false,
                    'email_verified_at' => now(),
                ])->save();
            }

            if (! $user->tenants()->where('tenants.id', $tenant->id)->exists()) {
                $user->tenants()->attach($tenant->id, [
                    'is_primary_tenant' => true,
                    'is_owner' => true,
                    'joined_at' => now(),
                ]);
            }

            // Spatie roles are scoped per tenant via team_id.
            setPermissionsTeamId($tenant->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if (! $user->hasRole('administrador-general')) {
                $user->assignRole('administrador-general');
            }

            return $user;
        });
    }
}
