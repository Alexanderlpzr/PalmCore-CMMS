<?php

namespace App\Filament\Resources\MaintenanceBudgets\Schemas;

use App\Models\Plant;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaintenanceBudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::where('tenant_id', Filament::getTenant()->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?string => Plant::where('tenant_id', Filament::getTenant()->id)
                        ->orderBy('name')
                        ->value('id'))
                    ->required()
                    ->native(false),
                Select::make('year')
                    ->label('Año')
                    ->options(fn (): array => collect(range((int) now()->year + 1, (int) now()->year - 3))
                        ->mapWithKeys(fn (int $y): array => [$y => (string) $y])
                        ->all())
                    ->default((int) now()->year)
                    ->required()
                    ->native(false),
                Select::make('month')
                    ->label('Mes')
                    ->options([
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                    ])
                    ->default((int) now()->month)
                    ->required()
                    ->native(false),
                TextInput::make('amount')
                    ->label('Monto asignado')
                    ->helperText('Lo que la gerencia le asigna al área de mantenimiento para ese mes.')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('COP')
                    ->required(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
