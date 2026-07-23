<?php

namespace App\Filament\Resources\Equipment\Schemas;

use App\Domain\Assets\Enums\ComponentStatus;
use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                            ->options(fn () => ReferenceDataService::categories(Filament::getTenant()?->id ?? ''))
                            ->searchable()
                            ->nullable()
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Código')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $tenantId = Filament::getTenant()->id;

                                $category = EquipmentCategory::create([
                                    ...$data,
                                    'tenant_id' => $tenantId,
                                ]);

                                ReferenceDataService::forgetCategories($tenantId);

                                return $category->id;
                            }),
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
                        Select::make('reading_frequency')
                            ->label('Frecuencia de lectura del horómetro')
                            ->helperText('Define en qué ronda aparece: Registro Diario o Semanal. Vacío = no lleva ronda de horómetro.')
                            ->options(MeterReadingFrequency::options())
                            ->native(false)
                            ->nullable(),
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
                            ->options(fn (Get $get) => blank($get('plant_id'))
                                ? []
                                : ReferenceDataService::areas($get('plant_id'))
                            )
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('plant_id')))
                            ->placeholder(fn (Get $get): string => blank($get('plant_id'))
                                ? 'Selecciona una planta primero'
                                : 'Selecciona un área'
                            )
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
                            ->nullable()
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Código')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => Manufacturer::create([
                                ...$data,
                                'tenant_id' => Filament::getTenant()->id,
                            ])->id),
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(fn () => Supplier::query()
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable()
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Código')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): string => Supplier::create([
                                ...$data,
                                'tenant_id' => Filament::getTenant()->id,
                            ])->id),
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
                        Select::make('currency_code')
                            ->label('Moneda')
                            ->options([
                                'COP' => 'COP — Peso colombiano',
                                'USD' => 'USD — Dólar estadounidense',
                                'MXN' => 'MXN — Peso mexicano',
                                'EUR' => 'EUR — Euro',
                            ])
                            // La operación es en pesos. El dólar se elige a propósito
                            // (una bomba importada), no por descuido del formulario.
                            ->default('COP'),
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

                Section::make('Fotografía')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        FileUpload::make('primary_photo_path')
                            ->label('Foto principal (opcional)')
                            ->image()
                            ->disk(persistent_disk())
                            ->directory('equipment-photos/tmp')
                            ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                            ->maxSize(10240)
                            ->imageResizeMode('contain')
                            ->helperText('Puedes agregar más fotos y documentos después, desde las pestañas del equipo.'),
                    ]),

                // Solo al crear: registrar de una vez las piezas del equipo. En la edición
                // se administran desde la pestaña Componentes (el relation manager), que ya
                // permite editarlas, borrarlas y programarles mantenimiento.
                Section::make('Componentes')
                    ->description('Opcional. Registra las piezas del equipo. Podrás agregar más o editarlas luego desde la ficha.')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Repeater::make('components')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('code')
                                    ->label('Código')
                                    ->maxLength(50),
                                TextInput::make('part_number')
                                    ->label('N° de parte')
                                    ->maxLength(100),
                                Select::make('criticality')
                                    ->label('Criticidad')
                                    ->options(EquipmentCriticality::options())
                                    ->default(EquipmentCriticality::Medium->value)
                                    ->required(),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(ComponentStatus::options())
                                    ->default(ComponentStatus::Active->value)
                                    ->required(),
                                TextInput::make('useful_life_hours')
                                    ->label('Vida útil')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('h'),
                                TextInput::make('unit_cost')
                                    ->label('Valor del repuesto')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar componente')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),
            ]);
    }
}
