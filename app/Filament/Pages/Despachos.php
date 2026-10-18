<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderInventoryService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrderPart;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * La cola de despachos: lo que mantenimiento pidió en sus órdenes de trabajo y el almacén
 * no ha entregado. El repuesto sale del inventario cuando el almacenista lo entrega y anota
 * quién lo recibió —no solo, al cerrar la orden—. Lo que no se entregó se suelta.
 */
class Despachos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Despachos';

    protected static ?string $title = 'Despachos a órdenes de trabajo';

    /** Justo debajo del tablero: es lo que el almacén atiende durante el día. */
    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'despachos';

    protected string $view = 'filament.pages.despachos';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('inventory.exit'));
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $pending = WarehouseDashboard::pendingDispatches(WorkOrderPart::query()->forTenant($tenant->id))->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getSubheading(): ?string
    {
        return 'Lo que pidieron las órdenes de trabajo. Sale del inventario cuando usted lo entrega.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => WarehouseDashboard::pendingDispatches(WorkOrderPart::query())
                ->with(['workOrder:id,work_order_number,status,equipment_id', 'workOrder.equipment:id,name', 'sparePart:id,code,name,unit', 'warehouse:id,name']))
            ->columns([
                TextColumn::make('workOrder.work_order_number')
                    ->label('OT')
                    ->searchable()
                    ->description(fn (WorkOrderPart $record): ?string => $record->workOrder?->equipment?->name),
                TextColumn::make('sparePart.name')
                    ->label('Repuesto')
                    ->searchable()
                    ->description(fn (WorkOrderPart $record): ?string => $record->sparePart?->code)
                    ->limitWithTooltip(40),
                TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->description(fn (WorkOrderPart $record): ?string => $this->stockRow($record)?->bin_location),
                TextColumn::make('quantity')
                    ->label('Pedido')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                TextColumn::make('available')
                    ->label('Disponible')
                    ->alignEnd()
                    ->state(fn (WorkOrderPart $record): float => $this->available($record))
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (WorkOrderPart $record): string => $this->available($record) < (float) $record->quantity ? 'danger' : 'success'),
                TextColumn::make('workOrder.status')
                    ->label('Orden')
                    ->badge()
                    ->formatStateUsing(fn (WorkOrderStatus $state): string => in_array($state, [WorkOrderStatus::Completed, WorkOrderStatus::Verified, WorkOrderStatus::Closed], true) ? 'Cerrada' : 'Abierta')
                    ->color(fn (WorkOrderStatus $state): string => in_array($state, [WorkOrderStatus::Completed, WorkOrderStatus::Verified, WorkOrderStatus::Closed], true) ? 'gray' : 'info'),
                TextColumn::make('created_at')
                    ->label('Pedido')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                Action::make('entregar')
                    ->label('Entregar')
                    ->icon(Heroicon::OutlinedHandRaised)
                    ->color('primary')
                    ->modalHeading(fn (WorkOrderPart $record): string => 'Entregar '.$record->sparePart?->name)
                    ->modalDescription(fn (WorkOrderPart $record): string => 'Para la '.$record->workOrder?->work_order_number.'. Si entrega menos de lo pedido, el resto se suelta.')
                    ->fillForm(fn (WorkOrderPart $record): array => ['quantity' => (float) $record->quantity])
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Cantidad que entrega')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (WorkOrderPart $record): float => (float) $record->quantity)
                            ->required(),
                        $this->recipientInput(),
                    ])
                    ->modalSubmitActionLabel('Entregar')
                    ->action(fn (WorkOrderPart $record, array $data): bool => $this->dispatchParts(collect([$record]), $data['recipient_name'], (float) $data['quantity'])),
                Action::make('noEntregado')
                    ->label('No se entregó')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('El pedido sale de la cola y la existencia que tenía apartada queda libre.')
                    ->action(function (WorkOrderPart $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        app(WorkOrderInventoryService::class)->cancelPart($record, $user);

                        Notification::make()->title('Pedido retirado de la cola')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('entregarSeleccionados')
                    ->label('Entregar lo seleccionado')
                    ->icon(Heroicon::OutlinedHandRaised)
                    ->schema([$this->recipientInput()])
                    ->action(fn (Collection $records, array $data): bool => $this->dispatchParts($records, $data['recipient_name']))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at')
            ->emptyStateHeading('No hay nada por despachar')
            ->emptyStateDescription('Cuando mantenimiento pida un repuesto en una orden de trabajo, aparece aquí.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }

    private function recipientInput(): TextInput
    {
        $tenant = Filament::getTenant();

        return TextInput::make('recipient_name')
            ->label('Quién recibe')
            ->placeholder('Nombre del técnico')
            ->datalist(fn (): array => $tenant instanceof Tenant ? app(WarehouseDashboard::class)->recipientSuggestions($tenant) : [])
            ->maxLength(150)
            ->required();
    }

    /**
     * Entrega los pedidos uno por uno: el que no tiene existencia no frena a los demás.
     *
     * @param  \Illuminate\Support\Collection<int, WorkOrderPart>  $parts
     */
    private function dispatchParts(\Illuminate\Support\Collection $parts, string $recipient, ?float $quantity = null): bool
    {
        /** @var User $user */
        $user = auth()->user();
        $service = app(WorkOrderInventoryService::class);
        $delivered = 0;
        $failed = [];

        foreach ($parts as $part) {
            if (! in_array($part->status, [WorkOrderPartStatus::Requested, WorkOrderPartStatus::Reserved], true)) {
                continue;
            }

            try {
                $service->dispatchPart($part, $quantity ?? (float) $part->quantity, $recipient, $user);
                $delivered++;
            } catch (\RuntimeException|\InvalidArgumentException) {
                $failed[] = $part->sparePart?->name ?? $part->id;
            }
        }

        if ($delivered > 0) {
            Notification::make()
                ->title($delivered === 1 ? 'Repuesto entregado' : "{$delivered} repuestos entregados")
                ->body('Recibió: '.$recipient.'.')
                ->success()
                ->send();
        }

        if ($failed !== []) {
            Notification::make()
                ->title('No se pudo entregar')
                ->body(implode(', ', $failed).': revise la existencia disponible en el almacén.')
                ->danger()
                ->send();
        }

        return $failed === [];
    }

    /** @var array<string, WarehouseSparePart|null> */
    private array $stockRows = [];

    private function stockRow(WorkOrderPart $part): ?WarehouseSparePart
    {
        $key = $part->warehouse_id.'|'.$part->spare_part_id;

        if (! array_key_exists($key, $this->stockRows)) {
            $this->stockRows[$key] = WarehouseSparePart::query()
                ->where('warehouse_id', $part->warehouse_id)
                ->where('spare_part_id', $part->spare_part_id)
                ->first();
        }

        return $this->stockRows[$key];
    }

    /** Lo que se puede entregar: la existencia libre más lo que ya estaba apartado para este pedido. */
    private function available(WorkOrderPart $part): float
    {
        $stock = $this->stockRow($part);

        if ($stock === null) {
            return 0.0;
        }

        $own = $part->status === WorkOrderPartStatus::Reserved ? (float) $part->reserved_quantity : 0.0;

        return max(0.0, (float) $stock->current_stock - (float) $stock->reserved_stock + $own);
    }
}
