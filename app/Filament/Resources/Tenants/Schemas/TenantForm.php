<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Identificador único para URLs (ej: elpajuil)'),
                        TextInput::make('tax_id')
                            ->label('RUC / NIT')
                            ->maxLength(50),
                        TextInput::make('contact_email')
                            ->label('Email de contacto')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                    ]),

                Section::make('Ubicación y Configuración')
                    ->columns(2)
                    ->schema([
                        TextInput::make('country_code')
                            ->label('País (ISO 3166-1)')
                            ->maxLength(2)
                            ->placeholder('CO'),
                        TextInput::make('timezone')
                            ->label('Zona horaria')
                            ->maxLength(100)
                            ->placeholder('America/Bogota'),
                        TextInput::make('locale')
                            ->label('Locale')
                            ->maxLength(10)
                            ->placeholder('es_CO'),
                        Select::make('subscription_plan')
                            ->label('Plan de suscripción')
                            ->options([
                                'trial' => 'Prueba',
                                'starter' => 'Inicial',
                                'professional' => 'Profesional',
                                'enterprise' => 'Empresarial',
                            ]),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
