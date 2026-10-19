<?php

namespace App\Filament\Resources\Inventory\InventoryCount\Pages;

use App\Domain\Inventory\Services\InventoryCountService;
use App\Domain\Reports\Excel\InventoryCountSheetExcel;
use App\Filament\Resources\Inventory\InventoryCount\InventoryCountResource;
use App\Filament\Widgets\Inventory\InventoryCountProgress;
use App\Models\InventoryCount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewInventoryCount extends ViewRecord
{
    protected static string $resource = InventoryCountResource::class;

    /** El avance del conteo arriba de la planilla. */
    protected function getHeaderWidgets(): array
    {
        return [InventoryCountProgress::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('planilla')
                ->label('Planilla para contar')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->action(fn (InventoryCount $record): StreamedResponse => app(InventoryCountSheetExcel::class)->download($record)),

            Action::make('aplicar')
                ->label('Aplicar al inventario')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->visible(fn (InventoryCount $record): bool => $record->isOpen() && InventoryCountResource::canEdit($record))
                ->requiresConfirmation()
                ->modalDescription(function (InventoryCount $record): string {
                    $pending = $record->lines()->whereNull('counted_quantity')->count();

                    return 'Cada repuesto contado queda con lo que se encontró, y la diferencia como ajuste en el kardex.'
                        .($pending > 0 ? " Hay {$pending} sin contar: esos no se tocan." : '');
                })
                ->action(function (InventoryCount $record): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        $summary = app(InventoryCountService::class)->apply($record, $user);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->warning()->send();

                        return;
                    }

                    $this->record->refresh();
                    $this->dispatch('conteo-actualizado');

                    Notification::make()
                        ->title('Conteo aplicado')
                        ->body("{$summary['counted']} contados, {$summary['adjusted']} con diferencia (".($summary['value'] < 0 ? '−' : '').'$ '.number_format(abs($summary['value']), 0, ',', '.').').')
                        ->success()
                        ->send();
                }),

            Action::make('cancelar')
                ->label('Cancelar conteo')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (InventoryCount $record): bool => $record->isOpen() && InventoryCountResource::canEdit($record))
                ->requiresConfirmation()
                ->modalDescription('El conteo se cierra sin tocar el inventario.')
                ->action(function (InventoryCount $record): void {
                    app(InventoryCountService::class)->cancel($record);
                    $this->record->refresh();
                    $this->dispatch('conteo-actualizado');

                    Notification::make()->title('Conteo cancelado')->success()->send();
                }),
        ];
    }
}
