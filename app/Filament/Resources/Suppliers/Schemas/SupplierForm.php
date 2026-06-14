<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Proveedor')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->label('RUC / NIT')
                            ->maxLength(50),
                        TextInput::make('country_code')
                            ->label('País (ISO 3166-1)')
                            ->maxLength(2)
                            ->placeholder('CO'),
                    ]),

                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Persona de contacto')
                            ->maxLength(255),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(100),
                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
