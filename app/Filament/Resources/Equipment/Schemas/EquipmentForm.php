<?php

namespace App\Filament\Resources\Equipment\Schemas;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;

class EquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código de activo')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label('Modelo')
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label('Número de serie')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('asset_tag')
                            ->label('Etiqueta de activo')
                            ->maxLength(100),
                    ]),

                Section::make('Clasificación')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Categoría')
                            ->options(fn () => EquipmentCategory::query()
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable(),
                        Select::make('status')
                            ->label('Estado')
                            ->options(EquipmentStatus::options())
                            ->default(EquipmentStatus::Active->value)
                            ->required(),
                        Select::make('criticality')
                            ->label('Criticidad')
                            ->options(EquipmentCriticality::options())
                            ->default(EquipmentCriticality::Medium->value)
                            ->required(),
                        Select::make('priority')
                            ->label('Prioridad')
                            ->options(EquipmentPriority::options())
                            ->default(EquipmentPriority::P3->value)
                            ->required(),
                    ]),

                Section::make('Ubicación')
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
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('area_id', null)),
                        Select::make('area_id')
                            ->label('Área')
                            ->options(fn (Get $get) => Area::query()
                                ->where('plant_id', $get('plant_id'))
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable()
                            ->disabled(fn (Get $get): bool => blank($get('plant_id')))
                            ->live(),
                        Select::make('parent_equipment_id')
                            ->label('Equipo padre (componente de)')
                            ->options(fn (?Equipment $record) => Equipment::query()
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder('Equipo independiente')
                            ->columnSpanFull(),
                        TextInput::make('location_notes')
                            ->label('Notas de ubicación')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Fabricante y Proveedor')
                    ->columns(2)
                    ->schema([
                        Select::make('manufacturer_id')
                            ->label('Fabricante')
                            ->options(fn () => Manufacturer::query()
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable(),
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(fn () => Supplier::query()
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable(),
                    ]),

                Section::make('Ciclo de Vida')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('purchase_date')
                            ->label('Fecha de compra')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('installation_date')
                            ->label('Fecha de instalación')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('commissioning_date')
                            ->label('Fecha de puesta en marcha')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('warranty_expiry_date')
                            ->label('Vencimiento de garantía')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('useful_life_years')
                            ->label('Vida útil (años)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5),
                    ]),

                Section::make('Información Financiera')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('Precio de compra')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0),
                        TextInput::make('replacement_cost')
                            ->label('Costo de reemplazo')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0),
                        TextInput::make('currency_code')
                            ->label('Moneda')
                            ->maxLength(3)
                            ->default('USD')
                            ->placeholder('USD'),
                    ]),

                Section::make('Notas')
                    ->columns(1)
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas adicionales')
                            ->maxLength(5000),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ]),
            ]);
    }
}
