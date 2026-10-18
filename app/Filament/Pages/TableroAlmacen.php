<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Filament\Widgets\Inventory\MonthlyOutgoingValueChart;
use App\Filament\Widgets\Inventory\MostUsedSparePartsChart;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El tablero de Almacén: lo primero que ve la cuenta de almacén al entrar.
 *
 * Arriba lo que hay que reponer y lo que está por llegar; los tres movimientos del día a
 * día —entrada, salida y conteo— como botones, porque hasta ahora el panel no tenía dónde
 * registrarlos; los accesos a repuestos, almacenes y órdenes de compra, los últimos
 * movimientos y dos gráficos: lo que sale cada mes y los repuestos que más se usan.
 */
class TableroAlmacen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Tablero';

    protected static ?string $title = 'Almacén';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'almacen';

    protected string $view = 'filament.pages.tablero-almacen';

    public static function canAccess(): bool
    {
        return self::allows('inventory.view');
    }

    public function getSubheading(): ?string
    {
        return 'Hoy, '.CarbonImmutable::now($this->tenant()->plantTimezone())->locale('es')->translatedFormat('l j \d\e F \d\e Y').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->movementAction('entrada'),
            $this->movementAction('salida'),
            $this->movementAction('conteo'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [MonthlyOutgoingValueChart::class, MostUsedSparePartsChart::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }

    /**
     * Entrada, salida o conteo: el mismo formulario —almacén, repuesto, cantidad— con lo
     * que cambia en cada uno. El conteo pide la existencia que hay, no la diferencia.
     */
    private function movementAction(string $kind): Action
    {
        $labels = [
            'entrada' => ['Registrar entrada', 'heroicon-o-arrow-down-tray', 'primary', 'inventory.entry', 'Cantidad que entra'],
            'salida' => ['Registrar salida', 'heroicon-o-arrow-up-tray', 'warning', 'inventory.exit', 'Cantidad que sale'],
            'conteo' => ['Ajustar por conteo', 'heroicon-o-clipboard-document-list', 'gray', 'inventory.adjust', 'Existencia contada'],
        ];
        [$label, $icon, $color, $permission, $quantityLabel] = $labels[$kind];

        return Action::make($kind)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->authorize(fn (): bool => self::allows($permission))
            ->modalHeading($label)
            ->modalDescription(match ($kind) {
                'entrada' => 'Lo que llega al almacén: una compra, una devolución, la existencia inicial.',
                'salida' => 'Lo que sale sin orden de trabajo: un daño, una baja, una devolución al proveedor.',
                default => 'Escriba lo que hay en el estante: el sistema registra la diferencia.',
            })
            ->schema([
                Grid::make(2)->schema([
                    Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?string => Warehouse::query()->where('is_active', true)->count() === 1 ? Warehouse::query()->where('is_active', true)->value('id') : null)
                        ->live()
                        ->required(),
                    Select::make('spare_part_id')
                        ->label('Repuesto')
                        ->options(fn (): array => SparePart::query()->where('is_active', true)->orderBy('name')->get()
                            ->mapWithKeys(fn (SparePart $part): array => [$part->id => "{$part->code} · {$part->name}"])->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) use ($kind): void {
                            if ($kind === 'entrada' && $state) {
                                $set('unit_cost', (float) SparePart::query()->whereKey($state)->value('unit_cost'));
                            }
                        })
                        ->helperText(fn (Get $get): ?string => self::stockText($get('warehouse_id'), $get('spare_part_id')))
                        ->required(),
                    TextInput::make('quantity')
                        ->label($quantityLabel)
                        ->numeric()
                        ->minValue($kind === 'conteo' ? 0 : 0.01)
                        ->required(),
                    TextInput::make('unit_cost')
                        ->label('Costo unitario')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->visible($kind === 'entrada')
                        ->required($kind === 'entrada'),
                    TextInput::make('reference')
                        ->label($kind === 'entrada' ? 'Factura o remisión' : 'Referencia')
                        ->maxLength(100)
                        ->visible($kind !== 'conteo'),
                ]),
                Textarea::make('notes')
                    ->label('Observación (opcional)')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel($label)
            ->action(function (array $data, Action $action) use ($kind): void {
                $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
                $part = SparePart::query()->findOrFail($data['spare_part_id']);
                $cost = $kind === 'entrada' ? (float) $data['unit_cost'] : self::averageCost($warehouse, $part);
                /** @var User $user */
                $user = auth()->user();
                $service = app(InventoryService::class);

                try {
                    $movement = match ($kind) {
                        'entrada' => $service->receiveEntry($warehouse, $part, (float) $data['quantity'], $cost, $user, $data['reference'] ?? null, $data['notes'] ?? null),
                        'salida' => $service->recordExit($warehouse, $part, (float) $data['quantity'], $cost, $user, $data['reference'] ?? null, $data['notes'] ?? null),
                        default => $service->adjustStock($warehouse, $part, (float) $data['quantity'], $cost, $user, $data['notes'] ?? null),
                    };
                } catch (\RuntimeException|\InvalidArgumentException $e) {
                    Notification::make()
                        ->title(str_contains($e->getMessage(), 'Insufficient') ? 'No hay suficiente existencia disponible para esa salida.' : 'No se pudo registrar el movimiento.')
                        ->body(self::stockText($warehouse->id, $part->id))
                        ->danger()
                        ->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Movimiento registrado')
                    ->body("{$part->name}: queda en ".self::number((float) $movement->new_stock).' en '.$warehouse->name.'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenant = $this->tenant();
        $dashboard = app(WarehouseDashboard::class);
        $attention = $dashboard->attention($tenant);

        $cards = [
            [
                'label' => 'Bajo el mínimo',
                'value' => number_format($attention['belowMinimum'], 0, ',', '.'),
                'detail' => 'Repuestos en su existencia mínima o por debajo',
                'icon' => 'heroicon-o-arrow-trending-down',
                'tone' => $attention['belowMinimum'] > 0 ? 'warning' : 'ok',
                'url' => SparePartResource::getUrl('index', ['filters' => ['bajo_minimo' => ['isActive' => true]]]),
            ],
            [
                'label' => 'Sin existencias',
                'value' => number_format($attention['outOfStock'], 0, ',', '.'),
                'detail' => 'Repuestos activos en cero',
                'icon' => 'heroicon-o-archive-box-x-mark',
                'tone' => $attention['outOfStock'] > 0 ? 'danger' : 'ok',
                'url' => SparePartResource::getUrl('index', ['filters' => ['sin_existencias' => ['isActive' => true]]]),
            ],
            [
                'label' => 'Órdenes de compra abiertas',
                'value' => number_format($attention['openPurchaseOrders'], 0, ',', '.'),
                'detail' => $attention['overduePurchaseOrders'] > 0
                    ? $attention['overduePurchaseOrders'].' con la fecha de entrega vencida'
                    : 'Ninguna con la entrega vencida',
                'icon' => 'heroicon-o-truck',
                'tone' => $attention['overduePurchaseOrders'] > 0 ? 'danger' : 'neutral',
                'url' => PurchaseOrderResource::getUrl('index'),
            ],
            [
                'label' => 'Valor del inventario',
                'value' => '$ '.number_format($attention['inventoryValue'], 0, ',', '.'),
                'detail' => 'Existencias al costo promedio',
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'neutral',
                'url' => WarehouseResource::getUrl('index'),
            ],
            [
                'label' => 'Movimientos de hoy',
                'value' => number_format($attention['movementsToday'], 0, ',', '.'),
                'detail' => 'Entradas, salidas y ajustes',
                'icon' => 'heroicon-o-arrows-right-left',
                'tone' => 'neutral',
                'url' => WarehouseResource::getUrl('index'),
            ],
        ];

        $shortcuts = array_values(array_filter([
            SparePartResource::canViewAny() ? ['label' => 'Repuestos', 'icon' => 'heroicon-o-wrench-screwdriver', 'url' => SparePartResource::getUrl('index')] : null,
            WarehouseResource::canViewAny() ? ['label' => 'Almacenes', 'icon' => 'heroicon-o-building-storefront', 'url' => WarehouseResource::getUrl('index')] : null,
            PurchaseOrderResource::canViewAny() ? ['label' => 'Órdenes de compra', 'icon' => 'heroicon-o-truck', 'url' => PurchaseOrderResource::getUrl('index')] : null,
            SparePartResource::canCreate() ? ['label' => 'Nuevo repuesto', 'icon' => 'heroicon-o-plus-circle', 'url' => SparePartResource::getUrl('create')] : null,
            PurchaseOrderResource::canCreate() ? ['label' => 'Nueva orden de compra', 'icon' => 'heroicon-o-document-plus', 'url' => PurchaseOrderResource::getUrl('create')] : null,
        ]));

        // La tarjeta grande: lo que vale lo que hay, y qué parte de los repuestos está
        // por encima de su mínimo. El valor y los movimientos de hoy suben allí.
        $sanos = max(0, $attention['activeParts'] - $attention['belowMinimum']);
        $hero = [
            'value' => (float) $attention['inventoryValue'],
            'percent' => $attention['activeParts'] > 0 ? $sanos * 100 / $attention['activeParts'] : 0,
            'progressLabel' => $sanos.' de '.$attention['activeParts'].' repuestos por encima de su mínimo',
            'movementsToday' => $attention['movementsToday'],
            'activeParts' => $attention['activeParts'],
        ];
        $cards = array_values(array_filter($cards, fn (array $card): bool => ! in_array($card['label'], ['Valor del inventario', 'Movimientos de hoy'], true)));

        return [
            'hero' => $hero,
            'cards' => $cards,
            'shortcuts' => $shortcuts,
            'movements' => $dashboard->recentMovements($tenant),
            'timezone' => $tenant->plantTimezone(),
        ];
    }

    /** Lo que hay del repuesto en el almacén elegido, para escribir la cantidad con el dato a la vista. */
    private static function stockText(?string $warehouseId, ?string $sparePartId): ?string
    {
        if (blank($warehouseId) || blank($sparePartId)) {
            return null;
        }

        $stock = WarehouseSparePart::query()->where('warehouse_id', $warehouseId)->where('spare_part_id', $sparePartId)->first();

        if (! $stock) {
            return 'No hay existencias de este repuesto en ese almacén.';
        }

        $reserved = (float) $stock->reserved_stock;

        return 'En el almacén: '.self::number((float) $stock->current_stock)
            .($reserved > 0 ? ' (reservado para órdenes: '.self::number($reserved).')' : '').'.';
    }

    private static function averageCost(Warehouse $warehouse, SparePart $part): float
    {
        $average = WarehouseSparePart::query()->where('warehouse_id', $warehouse->id)->where('spare_part_id', $part->id)->value('average_unit_cost');

        return (float) ($average ?: $part->unit_cost);
    }

    private static function allows(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can($permission));
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
