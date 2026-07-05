<?php

namespace App\Filament\Resources\Inventory\SparePart\Schemas;

use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SparePartInfolist
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
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Clasificación')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('category_type')
                            ->label('Categoría')
                            ->badge()
                            ->color(fn (SparePartCategoryType $state): string => $state->color())
                            ->formatStateUsing(fn (SparePartCategoryType $state): string => $state->label()),
                        TextEntry::make('criticality')
                            ->label('Criticidad')
                            ->badge()
                            ->color(fn (SparePartCriticality $state): string => $state->color())
                            ->formatStateUsing(fn (SparePartCriticality $state): string => $state->label()),
                        TextEntry::make('abc_classification')
                            ->label('Clasificación ABC')
                            ->badge()
                            ->color(fn (SparePartAbcClassification $state): string => $state->color())
                            ->formatStateUsing(fn (SparePartAbcClassification $state): string => $state->label()),
                        TextEntry::make('unit')
                            ->label('Unidad de medida'),
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                    ]),

                Section::make('Costos y Stock')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('unit_cost')
                            ->label('Costo unitario')
                            ->money('COP'),
                        TextEntry::make('minimum_stock')
                            ->label('Stock mínimo')
                            ->placeholder('—'),
                        TextEntry::make('maximum_stock')
                            ->label('Stock máximo')
                            ->placeholder('—'),
                        TextEntry::make('reorder_point')
                            ->label('Punto de reorden')
                            ->placeholder('—'),
                        TextEntry::make('reorder_quantity')
                            ->label('Cantidad de reorden')
                            ->placeholder('—'),
                        TextEntry::make('lead_time_days')
                            ->label('Días de reposición')
                            ->suffix(' días')
                            ->placeholder('—'),
                    ]),

                Section::make('Proveedor y Fabricante')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('manufacturer.name')
                            ->label('Fabricante')
                            ->placeholder('—'),
                        TextEntry::make('supplier.name')
                            ->label('Proveedor')
                            ->placeholder('—'),
                    ]),

                Section::make('Notas')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
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
