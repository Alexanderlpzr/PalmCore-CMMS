<?php

namespace App\Domain\Platform\Enums;

use App\Models\Role;

/**
 * Lo que el superadministrador le puede hacer a una cuenta desde la plataforma, tal
 * como queda en la auditoría.
 *
 * El servicio escribe con estos valores y la ficha del usuario los lee con ellos: un
 * evento no puede llamarse de una forma al guardarse y de otra al mostrarse.
 */
enum AccountChange: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case PasswordSet = 'password_set';
    case PasswordTemporary = 'password_temporary';
    case SessionsRevoked = 'sessions_revoked';
    case TwoFactorReset = 'two_factor_reset';
    case SuperAdminGranted = 'superadmin_granted';
    case SuperAdminRevoked = 'superadmin_revoked';
    case TenantAdded = 'tenant_added';
    case TenantRemoved = 'tenant_removed';
    case RoleChanged = 'role_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Cuenta creada',
            self::Updated => 'Datos cambiados',
            self::Activated => 'Activada',
            self::Deactivated => 'Desactivada',
            self::PasswordSet => 'Contraseña cambiada',
            self::PasswordTemporary => 'Contraseña temporal',
            self::SessionsRevoked => 'Sesiones cerradas',
            self::TwoFactorReset => 'Verificación en dos pasos quitada',
            self::SuperAdminGranted => 'Nombrado superadministrador',
            self::SuperAdminRevoked => 'Deja de ser superadministrador',
            self::TenantAdded => 'Acceso a una empresa',
            self::TenantRemoved => 'Sin acceso a una empresa',
            self::RoleChanged => 'Rol cambiado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created, self::Activated, self::TenantAdded => 'success',
            self::Deactivated, self::TenantRemoved, self::SuperAdminRevoked => 'danger',
            self::SuperAdminGranted, self::TwoFactorReset, self::SessionsRevoked => 'warning',
            default => 'gray',
        };
    }

    /**
     * Una línea legible con lo que cambió. Solo lee las claves que el servicio escribe
     * para cada evento: la auditoría nunca guarda la fila entera de la cuenta.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function describe(?array $old, ?array $new): ?string
    {
        $old ??= [];
        $new ??= [];

        return match ($this) {
            self::Created => sprintf('En %s como %s', $new['tenant'] ?? '—', self::role($new['role'] ?? null)),
            self::Updated => self::diff($old, $new, ['name' => 'Nombre', 'email' => 'Correo']),
            self::RoleChanged => sprintf(
                '%s: %s → %s',
                $new['tenant'] ?? $old['tenant'] ?? '—',
                self::roles($old['roles'] ?? []),
                self::roles($new['roles'] ?? []),
            ),
            self::TenantAdded => sprintf('%s como %s', $new['tenant'] ?? '—', self::role($new['role'] ?? null)),
            self::TenantRemoved => sprintf('%s (tenía %s)', $old['tenant'] ?? '—', self::roles($old['roles'] ?? [])),
            self::PasswordSet => ($new['must_change_password'] ?? false)
                ? 'Debe elegir una propia al entrar'
                : 'Definitiva',
            self::TwoFactorReset => sprintf(
                'Tenía verificación en dos pasos: %s · llaves de acceso: %d',
                ($old['two_factor'] ?? false) ? 'sí' : 'no',
                (int) ($old['passkeys'] ?? 0),
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, string>  $labels
     */
    private static function diff(array $old, array $new, array $labels): ?string
    {
        $parts = [];

        foreach ($labels as $key => $label) {
            if (($old[$key] ?? null) !== ($new[$key] ?? null)) {
                $parts[] = sprintf('%s: %s → %s', $label, $old[$key] ?? '—', $new[$key] ?? '—');
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function role(?string $role): string
    {
        return $role === null ? '—' : Role::humanizeName($role);
    }

    /** @param  array<int, string>  $roles */
    private static function roles(array $roles): string
    {
        return $roles === [] ? 'ningún rol' : implode(', ', array_map(self::role(...), $roles));
    }
}
