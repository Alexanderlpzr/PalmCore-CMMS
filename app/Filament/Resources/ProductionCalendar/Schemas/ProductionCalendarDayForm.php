<?php

namespace App\Filament\Resources\ProductionCalendar\Schemas;

use App\Models\Plant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductionCalendarDayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('plant_id')
                ->label('Planta')
                ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->native(false),
            DatePicker::make('calendar_date')
                ->label('Fecha')
                ->required(),
            TextInput::make('programmed_hours')
                ->label('Horas pagadas')
                ->helperText('Cero es un dato legítimo: un domingo sin molienda no es un día malo, es un día que nunca debía producir.')
                ->numeric()
                ->minValue(0)
                ->maxValue(24)
                ->required(),
            TextInput::make('processed_tons')
                ->label('Fruta procesada (toneladas)')
                ->helperText('Toneladas de RFF que entraron ese día, no kilos. Un día bueno son unas 250 t. Es el numerador de la productividad de planta.')
                ->numeric()
                ->minValue(0)
                // Un mes entero se cargó una vez en kilogramos y entró sin protestar.
                ->maxValue(2000)
                ->default(0)
                ->required(),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2),
        ]);
    }
}
