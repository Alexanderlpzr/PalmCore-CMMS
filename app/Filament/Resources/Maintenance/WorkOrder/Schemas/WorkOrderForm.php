<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Schemas;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                        Select::make('assigned_supervisor')
                            ->label('Supervisor asignado')
                            ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable(),
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

                // Hidden auto-populated fields
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::pluck('name', 'id')->toArray())
                    ->required()
                    ->hidden(),
                Select::make('area_id')
                    ->label('Área')
                    ->options(fn (): array => Area::pluck('name', 'id')->toArray())
                    ->hidden(),
            ]);
    }
}
