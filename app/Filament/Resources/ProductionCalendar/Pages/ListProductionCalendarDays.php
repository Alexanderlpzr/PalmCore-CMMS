<?php

namespace App\Filament\Resources\ProductionCalendar\Pages;

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProductionCalendarDays extends ListRecords
{
    protected static string $resource = ProductionCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->programMonthAction(),
            CreateAction::make()->label('Agregar día'),
        ];
    }

    /**
     * La acción que hace que el planificador suelte el Excel: el mes entero de una
     * vez. Lo que ya estaba escrito se respeta salvo que se pida lo contrario —
     * quien corrigió un domingo a mano no puede perder esa corrección por recargar.
     */
    private function programMonthAction(): Action
    {
        return Action::make('programMonth')
            ->label('Programar mes')
            ->icon('heroicon-o-calendar-days')
            ->visible(fn (): bool => auth()->user()->can('create', ProductionCalendarDay::class))
            ->schema([
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all())
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
                TextInput::make('year')
                    ->label('Año')
                    ->numeric()
                    ->minValue(2020)
                    ->maxValue(2100)
                    ->default((int) now()->year)
                    ->required(),
                TextInput::make('hours_per_day')
                    ->label('Horas de molienda por día')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(24)
                    ->default(22)
                    ->required(),
                CheckboxList::make('rest_days')
                    ->label('Días sin molienda')
                    ->helperText('Se programan en cero: no producen, pero tampoco cuentan como horas perdidas.')
                    ->options([
                        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
                    ])
                    ->columns(3),
                Checkbox::make('overwrite_existing')
                    ->label('Sobrescribir los días ya programados')
                    ->helperText('Por defecto, un día ya cargado se respeta.'),
            ])
            ->action(function (array $data): void {
                $plant = Plant::findOrFail($data['plant_id']);

                try {
                    $result = app(ProductionCalendarService::class)->programMonth(
                        plant: $plant,
                        year: (int) $data['year'],
                        month: (int) $data['month'],
                        hoursPerDay: (float) $data['hours_per_day'],
                        restDays: array_map('intval', $data['rest_days'] ?? []),
                        overwriteExisting: (bool) ($data['overwrite_existing'] ?? false),
                    );
                } catch (\Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Mes programado')
                    ->body("{$result['created']} días creados · {$result['updated']} actualizados · {$result['skipped']} respetados")
                    ->success()
                    ->send();
            });
    }
}
