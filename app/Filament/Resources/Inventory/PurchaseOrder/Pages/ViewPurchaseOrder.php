<?php

namespace App\Filament\Resources\Inventory\PurchaseOrder\Pages;

use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Draft → Sent
            Action::make('send')
                ->label('Enviar al proveedor')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('info')
                ->visible(fn (PurchaseOrder $record): bool => $record->status->isEditable()
                    && (auth()->user()->is_super_admin || auth()->user()->can('inventory.entry')))
                ->requiresConfirmation()
                ->action(fn (PurchaseOrderService $service) => $this->run(fn () => $service->send($this->record), 'Orden enviada al proveedor.')),

            // Sent / Partial → receive everything
            Action::make('receiveAll')
                ->label('Recibir todo')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('success')
                ->visible(fn (PurchaseOrder $record): bool => $record->status->canReceive()
                    && (auth()->user()->is_super_admin || auth()->user()->can('inventory.entry')))
                ->requiresConfirmation()
                ->modalDescription('Ingresa al stock del almacén todas las cantidades pendientes de esta orden.')
                ->action(fn (PurchaseOrderService $service) => $this->run(fn () => $service->receiveAll($this->record, auth()->user()), 'Repuestos ingresados al almacén.')),

            // Cancel (any non-terminal)
            Action::make('cancel')
                ->label('Cancelar')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (PurchaseOrder $record): bool => ! $record->status->isTerminal()
                    && (auth()->user()->is_super_admin || auth()->user()->can('inventory.entry')))
                ->requiresConfirmation()
                ->action(fn (PurchaseOrderService $service) => $this->run(fn () => $service->cancel($this->record), 'Orden de compra cancelada.')),
        ];
    }

    private function run(callable $operation, string $successMessage): void
    {
        try {
            $operation();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();

        Notification::make()->title($successMessage)->success()->send();
    }
}
