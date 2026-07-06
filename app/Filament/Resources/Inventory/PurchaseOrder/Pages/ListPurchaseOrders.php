<?php

namespace App\Filament\Resources\Inventory\PurchaseOrder\Pages;

use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateFromReorder')
                ->label('Generar desde reorden')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->tooltip('Crea órdenes de compra en borrador para los repuestos bajo su punto de reorden')
                ->requiresConfirmation()
                ->modalDescription('Se crearán órdenes en borrador (una por proveedor) para los repuestos por debajo de su punto de reorden.')
                ->action(function (PurchaseOrderService $service): void {
                    $created = $service->generateFromReorder(Filament::getTenant()->id, auth()->user());

                    if ($created->isEmpty()) {
                        Notification::make()
                            ->title('Nada por reordenar')
                            ->body('Ningún repuesto está por debajo de su punto de reorden.')
                            ->info()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($created->count().' orden(es) de compra creada(s)')
                        ->body('Revísalas en borrador y envíalas a los proveedores.')
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('Nueva orden'),
        ];
    }
}
