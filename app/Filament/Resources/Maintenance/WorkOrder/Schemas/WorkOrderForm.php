<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Schemas;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Enums\WorkPermitType;
use App\Models\Equipment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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
                        Select::make('equipment_id')
                            ->label('Equipo')
                            ->options(fn (): array => Equipment::orderBy('code')->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->code} — {$e->name}"])->toArray())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($state) {
                                    $equipment = Equipment::find($state);
                                    if ($equipment) {
                                        $set('plant_id', $equipment->plant_id);
                                        $set('area_id', $equipment->area_id);
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
                            ->label('Área de Mtto')
                            ->options(MaintenanceArea::options())
                            ->native(false),
                        Select::make('assigned_supervisor')
                            ->label('Supervisor asignado')
                            ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable(),
                        TextInput::make('executed_by')
                            ->label('Ejecutante(s)')
                            ->helperText('Quién hizo el trabajo — la cuadrilla (ej: «El mecánico y su auxiliar», «Fernando A.»).')
                            ->maxLength(255),
                        TextInput::make('meter_reading')
                            ->label('Horómetro al hacer el trabajo')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('h'),
                        Select::make('technician_ids')
                            ->label('Técnicos del sistema (opcional)')
                            ->helperText('Opcional: solo si quieres vincular usuarios del sistema para costeo por hora. Quién hizo el trabajo se escribe en «Ejecutante(s)».')
                            ->multiple()
                            ->options(fn (): array => User::query()->operationalStaff()->orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visibleOn('create')
                            ->columnSpanFull(),
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
                    ]),

                Section::make('Planificación')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('planned_start_at')
                            ->label('Inicio planificado')
                            ->displayFormat('d/m/Y H:i'),
                        DateTimePicker::make('planned_end_at')
                            ->label('Fin planificado')
                            ->displayFormat('d/m/Y H:i'),
                        TextInput::make('planned_labor_hours')
                            ->label('Horas de labor planificadas')
                            ->numeric()
                            ->suffix('h'),
                        TextInput::make('estimated_cost')
                            ->label('Costo estimado')
                            ->numeric()
                            ->prefix('$'),
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

                Section::make('Seguridad (HSE)')
                    ->description('Lo declara quien planifica: es quien sabe que hay que soldar sobre la nave o entrar al digestor.')
                    ->schema([
                        CheckboxList::make('required_permit_types')
                            ->label('Permisos de trabajo exigidos')
                            ->options(WorkPermitType::options())
                            ->columns(2)
                            // No es un recordatorio: la OT no pasa a ejecución sin el
                            // permiso firmado y vigente de cada tipo marcado aquí.
                            ->helperText('La OT no podrá iniciarse hasta que cada permiso marcado esté emitido, firmado por el ejecutante y vigente.'),
                    ]),

                // Hidden auto-populated fields
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => ReferenceDataService::plants(Filament::getTenant()?->id ?? ''))
                    ->required()
                    ->hidden(),
                Select::make('area_id')
                    ->label('Área')
                    ->options(fn (): array => ReferenceDataService::allAreas(Filament::getTenant()?->id ?? ''))
                    ->hidden(),
            ]);
    }
}
