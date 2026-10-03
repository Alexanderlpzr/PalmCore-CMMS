<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\AccountChange;
use App\Exceptions\BusinessRuleException;
use App\Infrastructure\Audit\Jobs\WriteAuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuperAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SensitiveParameter;
use Spatie\Permission\PermissionRegistrar;

/**
 * Todo lo que el superadministrador hace sobre las cuentas de cualquier empresa
 * desde el panel de plataforma.
 *
 * Cada operación deja una entrada en la auditoría con los valores elegidos a mano —
 * qué rol tenía y cuál tiene, si estaba activa o no—, nunca la fila completa. El
 * trait `Auditable` no sirve para `User`: guardaría el hash de la contraseña, el
 * secreto de la verificación en dos pasos y una entrada por cada inicio de sesión.
 *
 * Las reglas hablan español y viajan como {@see BusinessRuleException}: el panel solo
 * las convierte en notificación.
 */
class PlatformUserService
{
    public function __construct(
        private readonly UserPasswordService $passwords,
        private readonly SuperAdminGuard $superAdmins,
    ) {}

    // ── Alta y datos de la cuenta ───────────────────────────────────────────────

    /**
     * Da de alta a una persona en una empresa, con su rol y una contraseña temporal.
     *
     * La temporal obliga a elegir una propia al primer ingreso, así que quien la
     * dicta por teléfono deja de conocer la contraseña real en cuanto la persona entra.
     *
     * @return array{user: User, password: string} La contraseña en claro: se muestra una vez.
     */
    public function create(string $name, string $email, Tenant $tenant, string $role, User $actor): array
    {
        $name = $this->validName($name);
        $email = $this->validEmail($email);

        $this->assertEmailIsFree($email);
        $this->assertRoleExists($tenant, $role);

        return DB::transaction(function () use ($name, $email, $tenant, $role, $actor): array {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                // Inservible a propósito: la que vale es la temporal de abajo.
                'password' => Hash::make(Str::password(40)),
                'is_active' => true,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ])->save();

            $user->tenants()->attach($tenant->id, [
                'is_primary_tenant' => true,
                'joined_at' => now(),
                'invited_by' => $actor->getKey(),
            ]);

            $this->withTeam($tenant, fn () => $user->assignRole($role));

            $password = $this->passwords->generateTemporary($user);

            $this->audit($user, AccountChange::Created, null, [
                'name' => $name,
                'email' => $email,
                'tenant' => $tenant->name,
                'role' => $role,
            ], $actor, $tenant->id);

            return ['user' => $user->refresh(), 'password' => $password];
        });
    }

    /**
     * Cambia el nombre, el correo y la foto.
     *
     * @param  string|null|false  $avatarPath  false deja la foto como está; null la quita.
     */
    public function updateAccount(User $user, string $name, string $email, string|null|false $avatarPath, User $actor): User
    {
        $name = $this->validName($name);
        $email = $this->validEmail($email);

        $this->assertEmailIsFree($email, except: $user);

        return DB::transaction(function () use ($user, $name, $email, $avatarPath, $actor): User {
            $before = ['name' => $user->name, 'email' => $user->email];

            $user->forceFill(['name' => $name, 'email' => $email])->save();

            if ($avatarPath !== false) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['avatar_path' => $avatarPath],
                );
            }

            $after = ['name' => $name, 'email' => $email];

            if ($before !== $after) {
                $this->audit($user, AccountChange::Updated, $before, $after, $actor);
            }

            return $user->refresh();
        });
    }

    // ── Empresas y roles ────────────────────────────────────────────────────────

    /**
     * Los roles que existen en una empresa, con el nombre que entiende una persona.
     *
     * @return array<string, string>
     */
    public function rolesFor(Tenant $tenant): array
    {
        return Role::query()
            ->where('team_id', $tenant->id)
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => Role::humanizeName($name)])
            ->all();
    }

    /**
     * Los roles que la persona tiene en una empresa concreta.
     *
     * @return list<string>
     */
    public function rolesIn(User $user, Tenant $tenant): array
    {
        return $this->withTeam($tenant, function () use ($user): array {
            // La relación queda en memoria con la empresa que estaba activa al cargarla.
            $user->unsetRelation('roles');

            return $user->getRoleNames()->values()->all();
        });
    }

    public function addToTenant(User $user, Tenant $tenant, string $role, User $actor): void
    {
        if ($this->belongsTo($user, $tenant)) {
            throw new BusinessRuleException("{$user->name} ya pertenece a {$tenant->name}.");
        }

        $this->assertRoleExists($tenant, $role);

        DB::transaction(function () use ($user, $tenant, $role, $actor): void {
            $user->tenants()->attach($tenant->id, [
                // La primera empresa de alguien es la principal: es a la que entra.
                'is_primary_tenant' => ! $user->tenants()->exists(),
                'joined_at' => now(),
                'invited_by' => $actor->getKey(),
            ]);

            $this->withTeam($tenant, fn () => $user->assignRole($role));

            $this->audit($user, AccountChange::TenantAdded, null, ['tenant' => $tenant->name, 'role' => $role], $actor, $tenant->id);
        });
    }

    public function changeRole(User $user, Tenant $tenant, string $role, User $actor): void
    {
        if (! $this->belongsTo($user, $tenant)) {
            throw new BusinessRuleException("{$user->name} no pertenece a {$tenant->name}.");
        }

        $this->assertRoleExists($tenant, $role);

        $before = $this->rolesIn($user, $tenant);

        if ($before === [$role]) {
            return;
        }

        DB::transaction(function () use ($user, $tenant, $role, $before, $actor): void {
            $this->withTeam($tenant, fn () => $user->syncRoles([$role]));

            $this->audit($user, AccountChange::RoleChanged, ['tenant' => $tenant->name, 'roles' => $before], ['tenant' => $tenant->name, 'roles' => [$role]], $actor, $tenant->id);
        });
    }

    /**
     * Le quita el acceso a una empresa. Sus OT, paros y registros en ella se quedan:
     * apuntan a la persona, no a su pertenencia.
     */
    public function removeFromTenant(User $user, Tenant $tenant, User $actor): void
    {
        $membership = $user->tenants()->where('tenants.id', $tenant->id)->first();

        if ($membership === null) {
            throw new BusinessRuleException("{$user->name} no pertenece a {$tenant->name}.");
        }

        $roles = $this->rolesIn($user, $tenant);

        DB::transaction(function () use ($user, $tenant, $membership, $roles, $actor): void {
            $this->withTeam($tenant, fn () => $user->syncRoles([]));

            $user->tenants()->detach($tenant->id);

            // Si era su empresa principal, pasa a serlo otra: sin principal, al entrar
            // no sabría a cuál llevarla.
            if ($membership->pivot->is_primary_tenant) {
                $next = $user->tenants()->orderByPivot('joined_at')->first();

                if ($next !== null) {
                    $user->tenants()->updateExistingPivot($next->id, ['is_primary_tenant' => true]);
                }
            }

            $this->audit($user, AccountChange::TenantRemoved, ['tenant' => $tenant->name, 'roles' => $roles], null, $actor, $tenant->id);
        });
    }

    // ── Acceso ──────────────────────────────────────────────────────────────────

    public function setActive(User $user, bool $active, User $actor): void
    {
        $this->assertNotSelf($user, $actor);

        if ($user->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($user, $active, $actor): void {
            if (! $active) {
                $this->superAdmins->assertAnotherActiveSuperAdminExists($user, SuperAdminGuard::MESSAGE_DEACTIVATE);
            }

            $user->forceFill(['is_active' => $active])->save();

            // Desactivar sin cerrar sus sesiones dejaría dentro a quien ya estaba dentro.
            if (! $active) {
                $this->passwords->signOutEverywhere($user);
            }

            $this->audit($user, $active ? AccountChange::Activated : AccountChange::Deactivated, ['is_active' => ! $active], ['is_active' => $active], $actor);
        });
    }

    public function grantSuperAdmin(User $user, User $actor): void
    {
        if ($user->is_super_admin) {
            throw new BusinessRuleException("{$user->name} ya es superadministrador.");
        }

        if (! $user->is_active) {
            throw new BusinessRuleException('Active la cuenta antes de darle acceso de superadministrador.');
        }

        DB::transaction(function () use ($user, $actor): void {
            $user->forceFill(['is_super_admin' => true])->save();

            $this->audit($user, AccountChange::SuperAdminGranted, ['is_super_admin' => false], ['is_super_admin' => true], $actor);
        });
    }

    public function revokeSuperAdmin(User $user, User $actor): void
    {
        $this->assertNotSelf($user, $actor);

        if (! $user->is_super_admin) {
            throw new BusinessRuleException("{$user->name} no es superadministrador.");
        }

        DB::transaction(function () use ($user, $actor): void {
            $this->superAdmins->assertAnotherActiveSuperAdminExists($user, SuperAdminGuard::MESSAGE_DEMOTE);

            $user->forceFill(['is_super_admin' => false])->save();

            $this->audit($user, AccountChange::SuperAdminRevoked, ['is_super_admin' => true], ['is_super_admin' => false], $actor);
        });
    }

    // ── Seguridad ───────────────────────────────────────────────────────────────

    public function setPassword(User $user, #[SensitiveParameter] string $password, bool $mustChange, User $actor): void
    {
        $this->passwords->setPassword($user, $password, $mustChange);

        // La auditoría enmascara `password`: aquí solo consta que se reemplazó.
        $this->audit($user, AccountChange::PasswordSet, null, ['password' => 'reemplazada', 'must_change_password' => $mustChange], $actor);
    }

    /** @return string La temporal en claro: se muestra una vez y no se guarda. */
    public function temporaryPassword(User $user, User $actor): string
    {
        $password = $this->passwords->generateTemporary($user);

        $this->audit($user, AccountChange::PasswordTemporary, null, ['must_change_password' => true], $actor);

        return $password;
    }

    /** Saca a la persona de todos los dispositivos sin tocar su contraseña. */
    public function signOutEverywhere(User $user, User $actor): void
    {
        $this->assertNotSelf($user, $actor);

        $this->passwords->signOutEverywhere($user);

        $this->audit($user, AccountChange::SessionsRevoked, null, null, $actor);
    }

    /**
     * Quita la verificación en dos pasos y las llaves de acceso, para quien perdió el
     * teléfono. Después entra solo con su contraseña y puede volver a configurarlas.
     *
     * Cierra además sus sesiones: si el teléfono se perdió, en él puede haber una abierta.
     */
    public function resetTwoFactor(User $user, User $actor): void
    {
        $this->assertNotSelf($user, $actor);

        DB::transaction(function () use ($user, $actor): void {
            $before = [
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'passkeys' => $user->passkeys()->count(),
            ];

            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $user->passkeys()->delete();

            $this->passwords->signOutEverywhere($user);

            $this->audit($user, AccountChange::TwoFactorReset, $before, ['two_factor' => false, 'passkeys' => 0], $actor);
        });
    }

    // ── Reglas ──────────────────────────────────────────────────────────────────

    /**
     * La propia cuenta se gestiona desde el Perfil. Quitarse a uno mismo el acceso o
     * cerrarse la sesión desde aquí solo puede ser un error.
     */
    private function assertNotSelf(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new BusinessRuleException('Su propia cuenta se gestiona desde el Perfil, no desde aquí.');
        }
    }

    /**
     * El correo es el usuario con el que se entra: no puede repetirse, ni siquiera con
     * otras mayúsculas ni en una cuenta eliminada (la base de datos tampoco lo dejaría).
     */
    private function assertEmailIsFree(string $email, ?User $except = null): void
    {
        $taken = User::withTrashed()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw new BusinessRuleException("Ya existe un usuario con el correo {$email}.");
        }
    }

    private function assertRoleExists(Tenant $tenant, string $role): void
    {
        if (! array_key_exists($role, $this->rolesFor($tenant))) {
            throw new BusinessRuleException("El rol «{$role}» no existe en {$tenant->name}.");
        }
    }

    private function belongsTo(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    private function validName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new BusinessRuleException('El nombre no puede quedar vacío.');
        }

        return $name;
    }

    private function validEmail(string $email): string
    {
        $email = trim($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleException("«{$email}» no es un correo válido.");
        }

        return $email;
    }

    /**
     * Ejecuta algo con los roles de una empresa en contexto, y deja el contexto como
     * estaba. Los roles de Spatie se separan por empresa con una variable global: si
     * se quedara apuntando a otra, el resto de la petición vería permisos ajenos.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withTeam(Tenant $tenant, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(User $user, AccountChange $event, ?array $old, ?array $new, User $actor, ?string $tenantId = null): void
    {
        WriteAuditLog::dispatch(
            modelClass: User::class,
            modelKey: (string) $user->getKey(),
            event: $event->value,
            oldValues: $old,
            newValues: $new,
            userId: (string) $actor->getKey(),
            tenantId: $tenantId,
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent(),
        )->afterResponse();
    }
}
