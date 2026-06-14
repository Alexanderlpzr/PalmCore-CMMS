<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Models\Equipment;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MaintenancePlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan de Mantenimiento')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Equipo')
                            ->options(fn (): array => Equipment::orderBy('code')->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->code} — {$e->name}"])->toArray())
                            ->searchable()
                            ->required(),
                        Select::make('responsible_user_id')
                            ->label('Responsable')
                            ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->nullable(),
                        TextInput::make('name')
                            ->label('Nombre del plan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Disparador')
                    ->columns(2)
                    ->schema([
                        Select::make('trigger_source')
                            ->label('Tipo de disparador')
                            ->options(MaintenanceTriggerSource::class)
                            ->required()
                            ->live()
                            ->default(MaintenanceTriggerSource::Calendar->value),
                        Select::make('cadence_mode')
                            ->label('Modo de cadencia')
                            ->options([
                                'fixed' => 'Fija (anclado a fecha teórica)',
                                'floating' => 'Flotante (desde última ejecución)',
                            ])
                            ->default('fixed')
                            ->required()
                            ->visible(fn (Get $get): bool => $get('trigger_source') !== MaintenanceTriggerSource::Manual->value),
                        Select::make('time_frequency')
                            ->label('Frecuencia de tiempo')
                            ->options(MaintenanceTimeFrequency::class)
                            ->visible(fn (Get $get): bool => in_array($get('trigger_source'), [
                                MaintenanceTriggerSource::Calendar->value,
                                MaintenanceTriggerSource::Hybrid->value,
                            ]))
                            ->required(fn (Get $get): bool => in_array($get('trigger_source'), [
                                MaintenanceTriggerSource::Calendar->value,
                                MaintenanceTriggerSource::Hybrid->value,
                            ])),
                        TextInput::make('meter_interval')
                            ->label('Intervalo de horómetro (horas)')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('h')
                            ->visible(fn (Get $get): bool => in_array($get('trigger_source'), [
                                MaintenanceTriggerSource::Meter->value,
                                MaintenanceTriggerSource::Hybrid->value,
                            ]))
                            ->required(fn (Get $get): bool => in_array($get('trigger_source'), [
                                MaintenanceTriggerSource::Meter->value,
                                MaintenanceTriggerSource::Hybrid->value,
                            ])),
                    ]),

                Section::make('Configuración Avanzada')
                    ->columns(2)
                    ->schema([
                        TextInput::make('estimated_duration_minutes')
                            ->label('Duración estimada')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('min'),
                        Toggle::make('pause_when_equipment_inactive')
                            ->label('Pausar cuando el equipo está inactivo')
                            ->default(false),
                        TextInput::make('grace_period_days')
                            ->label('Período de gracia (días)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('días')
                            ->visible(fn (Get $get): bool => in_array($get('trigger_source'), [
                                MaintenanceTriggerSource::Calendar->value,
                                MaintenanceTriggerSource::Hybrid->value,
                            ])),
                        TextInput::make('grace_meter_hours')
                            ->label('Gracia de horómetro')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('h')
                            ->visible(fn (Get $get): bool => in_array($get('trigger_source'), [
                                MaintenanceTriggerSource::Meter->value,
                                MaintenanceTriggerSource::Hybrid->value,
                            ])),
                    ]),
            ]);
    }
}
