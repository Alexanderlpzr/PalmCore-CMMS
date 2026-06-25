<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use App\Services\SuperAdminGuard;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cuenta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(User::class, ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpanFull()
                            // Cannot deactivate the last active Super Admin. Disabled fields
                            // are not dehydrated, so the persisted value is preserved on save.
                            ->disabled(fn (?User $record): bool => $record !== null
                                && app(SuperAdminGuard::class)->isLastActiveSuperAdmin($record))
                            ->helperText(fn (?User $record): ?string => $record !== null
                                && app(SuperAdminGuard::class)->isLastActiveSuperAdmin($record)
                                    ? 'Este es el último Super Admin activo de la plataforma: no puede desactivarse.'
                                    : null),
                    ]),

                Section::make('Rol en el Tenant')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(function (): array {
                                $tenantId = Filament::getTenant()?->id;

                                return Role::where('team_id', $tenantId)
                                    ->orderBy('name')
                                    ->pluck('name', 'name')
                                    ->toArray();
                            })
                            ->afterStateHydrated(function (Select $component, $record): void {
                                if ($record) {
                                    setPermissionsTeamId(Filament::getTenant()?->id);
                                    $component->state(
                                        $record->roles->pluck('name')->toArray()
                                    );
                                }
                            })
                            ->helperText('Los roles se aplican dentro del tenant actual'),
                    ]),
            ]);
    }
}
