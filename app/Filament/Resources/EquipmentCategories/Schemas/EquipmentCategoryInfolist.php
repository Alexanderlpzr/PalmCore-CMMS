<?php

namespace App\Filament\Resources\EquipmentCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Categoría')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código'),
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('parent.name')
                            ->label('Categoría padre')
                            ->placeholder('Sin categoría padre'),
                        TextEntry::make('icon')
                            ->label('Icono')
                            ->placeholder('—'),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->columnSpanFull()
                            ->placeholder('Sin descripción'),
                        TextEntry::make('sort_order')
                            ->label('Orden'),
                        IconEntry::make('is_active')
                            ->label('Activa')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Actualizada')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
