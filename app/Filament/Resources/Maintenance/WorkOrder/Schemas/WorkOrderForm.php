<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Schemas;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Orden de Trabajo')
                    ->columns(2)
                    ->schema([
                        Select::make('area_filter')
                            ->label('Sección')
                            ->helperText('Elige la sección para ver solo sus equipos.')
                            ->options(fn (): array => ReferenceDataService::allAreas(Filament::getTenant()?->id ?? ''))
                            // El valor puede venir del equipo elegido (afterStateUpdated de
                            // abajo) en vez de una elección manual dentro de la lista de
                            // arriba, así que la validación del `in()` de Filament necesita
                            // resolver la etiqueta directo por id, no solo buscarla en esa
                            // lista — si no, un equipo cuya área quedó fuera del alcance del
                            // tenant en la consulta de arriba tumba el guardado sin motivo.
                            ->getOptionLabelUsing(fn ($value): ?string => $value ? Area::withoutGlobalScopes()->find($value)?->name : null)
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->default(fn (?WorkOrder $record): ?string => $record?->area_id)
                            ->afterStateUpdated(fn (Set $set) => $set('equipment_id', null)),
                        Select::make('equipment_id')
                            ->label('Equipo')
                            ->options(fn (Get $get): array => Equipment::query()
                                ->when($get('area_filter'), fn ($query, $areaId) => $query->where('area_id', $areaId))
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn ($e) => [$e->id => "{$e->code} — {$e->name}"])
                                ->toArray())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($state) {
                                    $equipment = Equipment::find($state);
                                    if ($equipment) {
                                        $set('plant_id', $equipment->plant_id);
                                        $set('area_id', $equipment->area_id);
                                        $set('area_filter', $equipment->area_id);
                                    }
                                }
                            }),
                        Select::make('work_order_type')
                            ->label('Tipo')
                            ->options(WorkOrderType::options())
                            ->required(),
                        Select::make('priority')
                            ->label('Prioridad')
                            ->options(WorkOrderPriority::options())
                            ->required()
                            ->default(WorkOrderPriority::P3Medium->value),
                        Select::make('process')
                            ->label('Proceso')
                            ->options(PlantProcess::options())
                            ->native(false)
                            ->searchable(),
                        Select::make('maintenance_area')
                            ->label('Clase de mantenimiento')
                            ->options(MaintenanceArea::options())
                            ->native(false),
                        TextInput::make('executed_by')
                            ->label('Responsable(s)')
                            ->helperText('Quién hizo el trabajo — la cuadrilla (ej: «El mecánico y su auxiliar», «Fernando A.»).')
                            ->maxLength(255),
                        TextInput::make('meter_reading')
                            ->label('Horómetro al hacer el trabajo')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('h'),
                        DatePicker::make('planned_start_at')
                            ->label('Fecha planificada')
                            ->helperText('Para cuándo se planea hacer el trabajo. La fecha en que se hizo se registra al cerrar la OT.')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('instructions')
                            ->label('Instrucciones de trabajo')
                            ->rows(4)
                            ->columnSpanFull(),
                        // Foto del «antes»: cómo se encontró el equipo. La del
                        // «después» se pide al cerrar la OT, en su modal.
                        FileUpload::make('before_photo_path')
                            ->label('Foto del antes')
                            ->helperText('Cómo se encontró el equipo. Sirve de referencia para comparar con la foto del después al cerrar la OT.')
                            ->image()
                            ->imageEditor()
                            ->disk(persistent_disk())
                            ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                            ->directory('work-order-photos')
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ]),

                Section::make('Impacto en Equipo')
                    ->columns(2)
                    ->schema([
                        Toggle::make('equipment_stopped')
                            ->label('Equipo detenido')
                            ->default(false)
                            ->live(),
                        TextInput::make('downtime_minutes')
                            ->label('Tiempo de paro (minutos)')
                            ->numeric()
                            ->visible(fn (Get $get): bool => (bool) $get('equipment_stopped')),
                    ]),

                // Hidden auto-populated fields
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => ReferenceDataService::plants(Filament::getTenant()?->id ?? ''))
                    ->required()
                    ->hidden(),
                Select::make('area_id')
                    ->label('Sección')
                    ->options(fn (): array => ReferenceDataService::allAreas(Filament::getTenant()?->id ?? ''))
                    ->hidden(),
            ]);
    }
}
