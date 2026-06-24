<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                            ])
                            ->required()
                            ->default('starter'),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                // Optional initial administrator — only when creating a tenant.
                // Handled in CreateTenant::afterCreate(), not persisted on the model.
                Section::make('Administrador Inicial (opcional)')
                    ->description('Si llenas el email, se creará un usuario con el rol Administrador General de esta empresa.')
                    ->columns(2)
                    ->visibleOn('create')
                    ->schema([
                        TextInput::make('admin_name')
                            ->label('Nombre del administrador')
                            ->maxLength(255)
                            ->default('Administrador'),
                        TextInput::make('admin_email')
                            ->label('Email del administrador')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('admin_password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Si lo dejas vacío, se usará "Admin123".')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
