<?php

namespace App\Filament\Platform\Resources\Users\Schemas;

use App\Domain\Platform\Services\PlatformUserService;
use App\Filament\Resources\Users\Schemas\UserForm as TenantUserForm;
use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Alta y edición de una cuenta desde la plataforma.
 *
 * Al crearla se elige la empresa y el rol, porque en esta plataforma una persona sin
 * empresa no puede entrar a ningún sitio. Al editarla, la empresa y el rol ya no están
 * aquí sino en la pestaña «Empresas y roles» de su ficha: alguien puede tener varias.
 */
final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cuenta')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Correo')
                        ->helperText('Es el usuario con el que entra.')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make('Empresa y rol')
                ->description('Se le genera una contraseña temporal que se muestra una sola vez; tendrá que cambiarla al entrar.')
                ->visibleOn('create')
                ->columns(2)
                ->schema([
                    Select::make('tenant_id')
                        ->label('Empresa')
                        ->options(fn (): array => Tenant::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('role', null)),
                    Select::make('role')
                        ->label('Rol')
                        // Los roles son de cada empresa: hasta elegir una no hay qué ofrecer.
                        ->options(fn (Get $get): array => self::rolesOf($get('tenant_id')))
                        ->disabled(fn (Get $get): bool => blank($get('tenant_id')))
                        ->required()
                        ->native(false),
                ]),

            Section::make('Foto de perfil')
                ->hiddenOn('create')
                ->schema([
                    TenantUserForm::avatarUpload(),
                ]),
        ]);
    }

    /** @return array<string, string> */
    public static function rolesOf(?string $tenantId): array
    {
        $tenant = $tenantId !== null ? Tenant::withoutGlobalScopes()->find($tenantId) : null;

        return $tenant !== null ? app(PlatformUserService::class)->rolesFor($tenant) : [];
    }
}
