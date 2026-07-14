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
                ->label('Horas programadas')
                ->helperText('Cero es un dato legítimo: un domingo sin molienda no es un día malo, es un día que nunca debía producir.')
                ->numeric()
                ->minValue(0)
                ->maxValue(24)
                ->required(),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2),
        ]);
    }
}
