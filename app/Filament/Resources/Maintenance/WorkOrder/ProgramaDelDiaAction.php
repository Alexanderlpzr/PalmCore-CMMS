<?php

namespace App\Filament\Resources\Maintenance\WorkOrder;

use App\Domain\Reports\Services\DailySchedulePdfService;
use App\Models\Plant;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Imprimir programa del día»: el PDF de la programación de mantenimiento —las OT de un
 * día con su hora, equipo y responsable—, el que El Pajuil imprimía a mano para cada
 * parada. El PDF ya existía, pero solo desde el panel de operaciones; aquí queda en
 * Órdenes de trabajo y en el Calendario, donde trabaja mantenimiento.
 */
final class ProgramaDelDiaAction
{
    public static function make(): Action
    {
        return Action::make('programaDelDia')
            ->label('Programa del día')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('work-orders.view') ?? false)
            ->modalHeading('Imprimir el programa del día')
            ->modalDescription('Las órdenes de trabajo programadas para ese día, por hora, equipo y responsable.')
            ->schema([
                DatePicker::make('dia')
                    ->label('Día')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => CarbonImmutable::now(Filament::getTenant()?->plantTimezone() ?? 'America/Bogota')->toDateString())
                    ->required(),
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('Todas')
                    ->visible(fn (): bool => Plant::query()->count() > 1),
            ])
            ->modalSubmitActionLabel('Descargar PDF')
            ->action(function (array $data): StreamedResponse {
                $service = app(DailySchedulePdfService::class);
                $day = CarbonImmutable::parse($data['dia']);
                $bytes = $service->generate((string) Filament::getTenant()?->getKey(), $day, $data['plant_id'] ?? null);

                return response()->streamDownload(fn () => print ($bytes), $service->filename($day), ['Content-Type' => 'application/pdf']);
            });
    }
}
