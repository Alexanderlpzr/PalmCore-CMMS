<?php

namespace App\Filament\Resources\Holidays\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('holiday_date')
                ->label('Fecha')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(120),
            Toggle::make('is_national')
                ->label('Festivo de ley')
                ->default(true)
                ->helperText('Desmárquelo si es un día que da la empresa y no el calendario nacional. No cambia el cálculo; cambia quién puede discutirlo.'),
        ]);
    }
}
