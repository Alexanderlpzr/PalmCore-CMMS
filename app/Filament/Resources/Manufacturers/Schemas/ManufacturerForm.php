<?php

namespace App\Filament\Resources\Manufacturers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManufacturerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Fabricante')
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
                        TextInput::make('country_code')
                            ->label('País (ISO 3166-1)')
                            ->maxLength(2)
                            ->placeholder('US'),
                        TextInput::make('website')
                            ->label('Sitio web')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://'),
                    ]),

                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
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
