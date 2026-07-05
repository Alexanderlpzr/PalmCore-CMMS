<?php

namespace App\Domain\Shared\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';

    /** Abilities blocked for tenants that cannot mutate data. */
    public const BLOCKED_ABILITIES = ['create', 'update', 'delete', 'restore', 'forceDelete'];

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Prueba',
            self::Active => 'Activo',
            self::ReadOnly => 'Solo Lectura',
            self::Suspended => 'Suspendido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::ReadOnly => 'warning',
            self::Suspended => 'danger',
        };
    }

    /**
     * Trial and Active tenants can create, edit and delete records.
     * ReadOnly and Suspended tenants are limited to read + export operations.
     * Artemis constraint: exports are ALWAYS allowed regardless of status.
     */
    public function allowsMutations(): bool
    {
        return match ($this) {
            self::Trial, self::Active => true,
            self::ReadOnly, self::Suspended => false,
        };
    }

    /** CSS hex color used by the persistent HTML subscription banner. */
    public function bannerHexColor(): string
    {
        return match ($this) {
            self::Trial => '#2563EB',
            self::ReadOnly => '#D97706',
            self::Suspended => '#DC2626',
            self::Active => '#059669',
        };
    }

    /**
     * Returns the banner message for this status, or null if no banner is needed
     * (Active tenants with a healthy subscription show no banner).
     */
    public function bannerMessage(): ?string
    {
        return match ($this) {
            self::Trial => 'Estás en período de prueba.',
            self::ReadOnly => 'Acceso en solo lectura — renueva tu suscripción para continuar editando datos.',
            self::Suspended => 'Cuenta suspendida — contacta a soporte para reactivar el acceso.',
            self::Active => null,
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
