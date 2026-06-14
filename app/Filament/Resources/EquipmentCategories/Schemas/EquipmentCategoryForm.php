<?php

namespace App\Filament\Resources\EquipmentCategories\Schemas;

use App\Models\EquipmentCategory;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Categoría')
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
                        Select::make('parent_id')
                            ->label('Categoría padre')
                            ->options(fn (?EquipmentCategory $record) => EquipmentCategory::query()
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder('Sin categoría padre')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('icon')
                            ->label('Icono')
                            ->maxLength(100)
                            ->placeholder('heroicon-o-tag')
                            ->helperText('Nombre de icono Heroicon (opcional)'),
                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
