<?php

namespace App\Filament\Resources\Equipment\Schemas;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código de activo'),
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('model')
                            ->label('Modelo')
                            ->placeholder('—'),
                        TextEntry::make('serial_number')
                            ->label('Número de serie')
                            ->placeholder('—'),
                        TextEntry::make('asset_tag')
                            ->label('Etiqueta de activo')
                            ->placeholder('—'),
                    ]),

                Section::make('Clasificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (EquipmentStatus $state): string => $state->color()),
                        TextEntry::make('criticality')
                            ->label('Criticidad')
                            ->badge()
                            ->color(fn (EquipmentCriticality $state): string => $state->color()),
                        TextEntry::make('priority')
                            ->label('Prioridad')
                            ->badge()
                            ->color(fn (EquipmentPriority $state): string => $state->color()),
                        TextEntry::make('category.name')
                            ->label('Categoría')
                            ->placeholder('Sin categoría'),
                    ]),

                Section::make('Ubicación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('plant.name')
                            ->label('Planta'),
                        TextEntry::make('area.name')
                            ->label('Área')
                            ->placeholder('—'),
                        TextEntry::make('parent.name')
                            ->label('Equipo padre')
                            ->placeholder('Equipo independiente'),
                        TextEntry::make('location_notes')
                            ->label('Notas de ubicación')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),

                Section::make('Fabricante y Proveedor')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('manufacturer.name')
                            ->label('Fabricante')
                            ->placeholder('—'),
                        TextEntry::make('supplier.name')
                            ->label('Proveedor')
                            ->placeholder('—'),
                    ]),

                Section::make('Ciclo de Vida')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('purchase_date')
                            ->label('Compra')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('installation_date')
                            ->label('Instalación')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('commissioning_date')
                            ->label('Puesta en marcha')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('warranty_expiry_date')
                            ->label('Vencimiento garantía')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('useful_life_years')
                            ->label('Vida útil (años)')
                            ->placeholder('—'),
                        TextEntry::make('retired_at')
                            ->label('Retirado el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Información Financiera')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('purchase_price')
                            ->label('Precio de compra')
                            ->money(fn ($record) => $record->currency_code ?? 'USD')
                            ->placeholder('—'),
                        TextEntry::make('replacement_cost')
                            ->label('Costo de reemplazo')
                            ->money(fn ($record) => $record->currency_code ?? 'USD')
                            ->placeholder('—'),
                        TextEntry::make('currency_code')
                            ->label('Moneda'),
                    ]),

                Section::make('Auditoría')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->placeholder('Sin notas'),
                        TextEntry::make('createdBy.name')
                            ->label('Creado por')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updatedBy.name')
                            ->label('Actualizado por')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Actualizado')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
