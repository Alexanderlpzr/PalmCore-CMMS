<?php

namespace App\Filament\Platform\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * La ficha de una cuenta: lo que hace falta mirar antes de tocarla.
 *
 * La contraseña no aparece porque no se puede mostrar —se guarda con un hash que no se
 * revierte—; lo que sí se ve es si sigue siendo la temporal que alguien le dio.
 */
final class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cuenta')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre'),
                    TextEntry::make('email')
                        ->label('Correo')
                        ->copyable(),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->state(fn (User $record): string => $record->is_active ? 'Activa' : 'Desactivada')
                        ->color(fn (User $record): string => $record->is_active ? 'success' : 'danger'),
                    IconEntry::make('is_super_admin')
                        ->label('Superadministrador')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedShieldCheck)
                        ->falseIcon(Heroicon::OutlinedMinus)
                        ->falseColor('gray'),
                    TextEntry::make('password_state')
                        ->label('Contraseña')
                        ->badge()
                        ->state(fn (User $record): string => match (true) {
                            $record->must_change_password => 'Temporal',
                            $record->password_changed_at !== null => 'Cambiada '.$record->password_changed_at->diffForHumans(),
                            default => 'Original',
                        })
                        ->color(fn (User $record): string => $record->must_change_password ? 'warning' : 'gray'),
                    TextEntry::make('last_login_at')
                        ->label('Último ingreso')
                        ->since()
                        ->dateTimeTooltip('d/m/Y H:i')
                        ->placeholder('Nunca'),
                    TextEntry::make('two_factor_state')
                        ->label('Verificación en dos pasos')
                        ->badge()
                        ->state(fn (User $record): string => $record->two_factor_confirmed_at !== null ? 'Activa' : 'No')
                        ->color(fn (User $record): string => $record->two_factor_confirmed_at !== null ? 'success' : 'gray'),
                    TextEntry::make('passkeys_count')
                        ->label('Llaves de acceso')
                        ->state(fn (User $record): int => $record->passkeys()->count()),
                    TextEntry::make('created_at')
                        ->label('Cuenta creada')
                        ->date('d/m/Y'),
                ]),
        ]);
    }
}
