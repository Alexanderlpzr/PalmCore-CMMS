<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Actions;

use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Models\MaintenancePlan;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * «Ya se hizo» sin pasar por el ciclo completo de una orden de trabajo.
 *
 * El único camino que existía para avanzar un plan era completar una OT formal —
 * con técnico asignado, permisos y checklist respondido. Un mantenimiento hecho
 * de verdad pero registrado fuera de ese flujo (el caso normal en una planta que
 * recién está adoptando el sistema) no tenía dónde anotarse: el horómetro del
 * plan se quedaba vencido para siempre y nadie sabía cuántas veces se había hecho.
 */
class RegisterManualExecutionAction
{
    public static function make(): Action
    {
        return Action::make('registerManualExecution')
            ->label('Registrar ejecución')
            ->tooltip('Marca que este mantenimiento ya se hizo sin pasar por una orden de trabajo, y reinicia el conteo')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (MaintenancePlan $record): bool => $record->is_active)
            ->modalHeading(fn (MaintenancePlan $record): string => "Registrar ejecución — {$record->name}")
            ->modalDescription('Úsalo cuando el mantenimiento ya se hizo pero no pasó por una orden de trabajo. Reinicia el horómetro del plan y suma una ejecución a su historial.')
            ->modalSubmitActionLabel('Registrar')
            ->schema([
                DateTimePicker::make('completed_at')
                    ->label('Fecha en que se hizo')
                    ->default(now())
                    ->native(false)
                    ->required(),
                TextInput::make('completed_meter')
                    ->label('Horómetro en ese momento')
                    ->helperText('Lectura acumulada del equipo cuando se hizo la intervención.')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('h')
                    ->default(fn (MaintenancePlan $record): ?float => $record->equipment?->accumulated_meter_reading)
                    ->visible(fn (MaintenancePlan $record): bool => $record->isMeterBased())
                    ->required(fn (MaintenancePlan $record): bool => $record->isMeterBased()),
            ])
            ->action(function (MaintenancePlan $record, array $data, MaintenancePlanService $service, PreventiveWorkOrderGenerator $generator): void {
                if ($generator->hasOpenWorkOrder($record)) {
                    Notification::make()
                        ->title('Ya hay una OT abierta para este plan')
                        ->body('Complétala desde la orden de trabajo en vez de registrar aquí — así no se cuenta la misma ejecución dos veces.')
                        ->danger()
                        ->send();

                    return;
                }

                $completedMeter = $record->isMeterBased() && isset($data['completed_meter'])
                    ? (float) $data['completed_meter']
                    : null;

                $schedule = $service->recordManualExecution(
                    plan: $record,
                    actor: auth()->user(),
                    completedAt: Carbon::parse($data['completed_at']),
                    completedMeter: $completedMeter,
                );

                Notification::make()
                    ->title('Ejecución registrada')
                    ->body("Van {$schedule->times_executed} ejecuciones de «{$record->name}».")
                    ->success()
                    ->send();
            });
    }
}
