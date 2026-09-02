<?php

namespace App\Filament\Resources\PayrollRuns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayrollRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre del período')
                ->required()
                ->maxLength(120)
                ->default(fn (): string => 'Nómina de '.now()->translatedFormat('F \d\e Y')),

            DatePicker::make('period_start')
                ->label('Desde')
                ->required()
                ->default(now()->startOfMonth()),

            DatePicker::make('period_end')
                ->label('Hasta')
                ->required()
                ->afterOrEqual('period_start')
                // El día 30 y no el 31: la nómina colombiana liquida sobre meses de 30
                // días, y así lo hace el libro actual sin excepciones.
                ->default(fn (): string => now()->startOfMonth()->addDays(29)->toDateString())
                ->helperText('La nómina se liquida sobre meses de 30 días, como el libro actual.'),

            Textarea::make('notes')->label('Notas')->rows(2),
        ]);
    }
}
