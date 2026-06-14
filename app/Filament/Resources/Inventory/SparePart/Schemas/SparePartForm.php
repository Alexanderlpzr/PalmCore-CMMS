<?php

namespace App\Filament\Resources\Inventory\SparePart\Schemas;

use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use App\Domain\Inventory\Enums\SparePartUnit;
use App\Models\Manufacturer;
use App\Models\SparePart;
use App\Models\Supplier;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SparePartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(100)
                            ->unique(
                                table: SparePart::class,
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', Filament::getTenant()?->id),
                            ),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Clasificación')
                    ->columns(2)
                    ->schema([
                        Select::make('category_type')
                            ->label('Categoría')
                            ->options(SparePartCategoryType::options())
                            ->required(),
                        Select::make('criticality')
                            ->label('Criticidad')
                            ->options(SparePartCriticality::options())
                            ->required(),
                        Select::make('abc_classification')
                            ->label('Clasificación ABC')
                            ->options(SparePartAbcClassification::options())
                            ->required(),
                        Select::make('unit')
                            ->label('Unidad de medida')
                            ->options(SparePartUnit::options())
                            ->required(),
                    ]),

                Section::make('Costos y Stock')
                    ->columns(2)
                    ->schema([
                        TextInput::make('unit_cost')
                            ->label('Costo unitario')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->default(0),
                        TextInput::make('lead_time_days')
                            ->label('Días de reposición')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('días'),
                        TextInput::make('minimum_stock')
                            ->label('Stock mínimo')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('maximum_stock')
                            ->label('Stock máximo')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('reorder_point')
                            ->label('Punto de reorden')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('reorder_quantity')
                            ->label('Cantidad de reorden')
                            ->numeric()
                            ->minValue(0),
                    ]),

                Section::make('Proveedor y Fabricante')
                    ->columns(2)
                    ->schema([
                        Select::make('manufacturer_id')
                            ->label('Fabricante')
                            ->options(fn (): array => Manufacturer::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->nullable(),
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(fn (): array => Supplier::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->nullable(),
                    ]),

                Section::make('Notas y Estado')
                    ->columns(2)
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->hiddenOn('create'),
                    ]),
            ]);
    }
}
