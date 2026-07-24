<?php

namespace App\Filament\Resources\Downtime\Schemas;

use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageReason;
use App\Models\Equipment;
use App\Models\Plant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DowntimeEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('¿Qué paró?')
                ->description('Un paro de planta —falta de fruta, corte de energía— no es de ningún equipo. Déjalo sin equipo y elige la planta.')
                ->columns(2)
                ->schema([
                    Select::make('plant_id')
                        ->label('Planta')
                        ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->native(false),
                    Select::make('section')
                        ->label('Sección')
                        ->options(PlantSection::options())
                        ->native(false)
                        ->required(),
                    Select::make('equipment_id')
                        ->label('Equipo')
                        ->helperText('Vacío = paro de toda la planta.')
                        ->options(fn (Get $get): array => Equipment::query()
                            ->when($get('plant_id'), fn ($query, $plantId) => $query->where('plant_id', $plantId))
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Equipment $e): array => [$e->id => "{$e->code} — {$e->name}"])
                            ->all())
                        ->searchable()
                        ->native(false)
                        ->columnSpanFull(),
                ]),

            Section::make('Clasificación')
                ->columns(2)
                ->schema([
                    Select::make('reported_type')
                        ->label('Tipo I')
                        ->options(ReportedStoppageType::options())
                        ->required()
                        ->native(false)
                        ->live()
                        // Cambiar el Tipo I invalida el Tipo II ya elegido: era de otra rama.
                        ->afterStateUpdated(fn (Set $set) => $set('stoppage_reason', null)),
                    Select::make('stoppage_reason')
                        ->label('Tipo II')
                        ->helperText('La causa concreta; la lista depende del Tipo I.')
                        ->options(fn (Get $get): array => filled($get('reported_type'))
                            ? StoppageReason::optionsFor(ReportedStoppageType::from($get('reported_type')))
                            : [])
                        ->disabled(fn (Get $get): bool => blank($get('reported_type')))
                        ->native(false)
                        ->required(),
                    Textarea::make('stoppage_cause')
                        ->label('Causa de falla / Observación')
                        ->placeholder('Ej.: se rompió la cadena de transmisión del elevador de fruto')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Duración')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('started_at')
                        ->label('Inicio')
                        ->seconds(false)
                        ->default(now())
                        ->required(),
                    DateTimePicker::make('ended_at')
                        ->label('Fin')
                        ->helperText('Vacío = el paro sigue en curso.')
                        ->seconds(false)
                        ->after('started_at'),
                    Toggle::make('affects_production')
                        ->label('Restó horas de producción')
                        ->helperText('Una falla con la línea andando no le quita horas a la planta.')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
