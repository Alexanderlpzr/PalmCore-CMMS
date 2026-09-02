<?php

namespace App\Filament\Resources\AttendanceDays\Pages;

use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Filament\Resources\AttendanceDays\AttendanceDayResource;
use App\Models\AttendanceDay;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListAttendanceDays extends ListRecords
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reconstruir')
                ->label('Reconstruir período')
                ->icon('heroicon-o-arrow-path')
                ->authorize(fn (): bool => auth()->user()?->can('build', AttendanceDay::class) ?? false)
                ->schema([
                    DatePicker::make('desde')
                        ->label('Desde')
                        ->required()
                        ->default(now()->startOfMonth()),
                    DatePicker::make('hasta')
                        ->label('Hasta')
                        ->required()
                        ->default(now()->endOfMonth())
                        ->afterOrEqual('desde'),
                ])
                ->modalHeading('Reconstruir las horas desde el reloj')
                ->modalDescription(
                    'Vuelve a leer las marcas de portería y rehace las propuestas del período. '
                    .'Los días que ya firmó alguien no se tocan.'
                )
                ->modalSubmitActionLabel('Reconstruir')
                ->action(function (array $data): void {
                    $built = app(AttendanceDayBuilder::class)->buildForTenant(
                        Filament::getTenant()->id,
                        Carbon::parse($data['desde']),
                        Carbon::parse($data['hasta']),
                    );

                    $conAvisos = $built->filter(fn (AttendanceDay $day): bool => $day->hasAnomalies())->count();

                    Notification::make()
                        ->title("Se reconstruyeron {$built->count()} días")
                        ->body($conAvisos > 0
                            ? "{$conAvisos} tienen avisos por revisar antes de firmar."
                            : 'Ninguno tiene avisos.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        $pending = static::getResource()::getEloquentQuery()->proposed()->count();

        if ($pending === 0) {
            return null;
        }

        return "El reloj propuso {$pending} días que nadie ha firmado todavía. "
            .'Solo lo confirmado entra a la liquidación.';
    }
}
