<?php

namespace App\Filament\Resources\Downtime\Schemas;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Models\Equipment;
use App\Models\Plant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                        ->native(false),
                ]),

            Section::make('Clasificación')
                ->columns(2)
                ->schema([
                    Select::make('stoppage_category')
                        ->label('Tipo I')
                        ->options(StoppageCategory::options())
                        ->required()
                        ->native(false),
                    TextInput::make('stoppage_cause')
                        ->label('Tipo II — causa específica')
                        ->placeholder('Ej.: atasco en prensa 2')
                        ->maxLength(255),
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
                        ->default(true),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
