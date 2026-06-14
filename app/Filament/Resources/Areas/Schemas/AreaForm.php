<?php

namespace App\Filament\Resources\Areas\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Área')
                    ->columns(2)
                    ->schema([
                        Select::make('plant_id')
                            ->label('Planta')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship(
                                name: 'plant',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where(
                                    'tenant_id',
                                    Filament::getTenant()?->id
                                )
                            )
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ]),
            ]);
    }
}
