<?php

namespace App\Filament\Resources\Inventory\Warehouse\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('location')
                            ->label('Ubicación')
                            ->placeholder('—'),
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Auditoría')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label('Creado por')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Creado el')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updatedBy.name')
                            ->label('Modificado por')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Modificado el')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
