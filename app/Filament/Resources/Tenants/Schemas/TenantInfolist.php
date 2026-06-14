<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('tax_id')
                            ->label('RUC / NIT'),
                        TextEntry::make('contact_email')
                            ->label('Email de contacto'),
                        TextEntry::make('contact_phone')
                            ->label('Teléfono'),
                        TextEntry::make('subscription_plan')
                            ->label('Plan')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'trial' => 'warning',
                                'starter' => 'info',
                                'professional' => 'success',
                                'enterprise' => 'primary',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Ubicación y Configuración')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('country_code')
                            ->label('País'),
                        TextEntry::make('timezone')
                            ->label('Zona horaria'),
                        TextEntry::make('locale')
                            ->label('Locale'),
                        TextEntry::make('subscription_expires_at')
                            ->label('Suscripción vence')
                            ->date('d/m/Y'),
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
