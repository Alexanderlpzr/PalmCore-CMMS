<?php

namespace App\Filament\Resources\Plants\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Planta')
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
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(100),
                        TextInput::make('state_province')
                            ->label('Departamento / Estado')
                            ->maxLength(100),
                        TextInput::make('country_code')
                            ->label('País (ISO 3166-1)')
                            ->maxLength(2)
                            ->placeholder('CO'),
                        TextInput::make('timezone')
                            ->label('Zona horaria')
                            ->maxLength(100)
                            ->placeholder('America/Bogota'),
                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
