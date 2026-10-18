<?php

namespace App\Filament\Resources\Inventory\PurchaseOrder\Pages;

use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    /** Los renglones con su repuesto, de una vez: la ficha los muestra todos. */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load('lines.sparePart');
    }

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

            // Sent / Partial → lo que llegó, renglón por renglón
            Action::make('receive')
                ->label('Recibir lo que llegó')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('primary')
                ->visible(fn (PurchaseOrder $record): bool => $record->status->canReceive()
                    && (auth()->user()->is_super_admin || auth()->user()->can('inventory.entry')))
                ->modalHeading('Recibir lo que llegó')
                ->modalDescription('Escriba lo que llegó de cada repuesto. Lo que falte queda pendiente en la orden.')
                ->fillForm(fn (PurchaseOrder $record): array => [
                    'lines' => $record->lines()->with('sparePart:id,code,name')->get()
                        ->filter(fn (PurchaseOrderLine $line): bool => $line->pendingQuantity() > 0)
                        ->map(fn (PurchaseOrderLine $line): array => [
                            'line_id' => $line->id,
                            'part' => "{$line->sparePart?->code} · {$line->sparePart?->name}",
                            'pending' => $line->pendingQuantity(),
                            'quantity' => $line->pendingQuantity(),
                        ])->values()->all(),
                ])
                ->schema([
                    TextInput::make('invoice')
                        ->label('Factura o remisión')
                        ->maxLength(60),
                    Repeater::make('lines')
                        ->label('Renglones pendientes')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(3)
                        ->schema([
                            Hidden::make('line_id'),
                            TextInput::make('part')->label('Repuesto')->disabled()->dehydrated(false)->columnSpan(['default' => 2, 'sm' => 1]),
                            TextInput::make('pending')->label('Pendiente')->disabled()->dehydrated(false),
                            TextInput::make('quantity')
                                ->label('Llegó')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): float => (float) $get('pending'))
                                ->required(),
                        ]),
                ])
                ->modalSubmitActionLabel('Ingresar al almacén')
                ->action(function (array $data, PurchaseOrderService $service): void {
                    $quantities = collect($data['lines'] ?? [])
                        ->mapWithKeys(fn (array $line): array => [$line['line_id'] => (float) $line['quantity']])
                        ->all();

                    $this->run(fn () => $service->receiveQuantities($this->record, $quantities, auth()->user(), $data['invoice'] ?? null), 'Repuestos ingresados al almacén.');
                }),

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

        $this->record->refresh()->load('lines.sparePart');

        Notification::make()->title($successMessage)->success()->send();
    }
}
