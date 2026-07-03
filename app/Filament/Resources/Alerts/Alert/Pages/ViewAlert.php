<?php

namespace App\Filament\Resources\Alerts\Alert\Pages;

use App\Domain\Alerts\Services\AlertService;
use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Models\Alert;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAlert extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = AlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolve')
                ->label('Resolver')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Marcar alerta como resuelta?')
                ->modalDescription('Confirma que la condición que originó esta alerta ha sido corregida.')
                ->visible(fn (): bool => $this->record->isOpen())
                ->action(function (AlertService $service): void {
                    /** @var Alert $alert */
                    $alert = $this->record;
                    $service->resolve($alert, auth()->user());
                    $this->record->refresh();

                    Notification::make()
                        ->title('Alerta resuelta.')
                        ->success()
                        ->send();
                }),

            Action::make('dismiss')
                ->label('Descartar')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Descartar alerta?')
                ->modalDescription('La alerta quedará cerrada. Si la condición persiste, se generará una nueva en el próximo ciclo de evaluación.')
                ->visible(fn (): bool => $this->record->isOpen())
                ->action(function (AlertService $service): void {
                    /** @var Alert $alert */
                    $alert = $this->record;
                    $service->dismiss($alert, auth()->user());
                    $this->record->refresh();

                    Notification::make()
                        ->title('Alerta descartada.')
                        ->send();
                }),

            Action::make('open_entity')
                ->label('Ver entidad relacionada')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('info')
                ->url(fn (): ?string => AlertResource::getEntityUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => AlertResource::getEntityUrl($this->record) !== null),

            $this->getBackAction(),
        ];
    }
}
