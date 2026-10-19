<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Enums\InventoryExitReason;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use App\Domain\Inventory\Enums\SparePartUnit;
use App\Domain\Inventory\Services\InventoryCountService;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Inventory\Services\SparePartService;
use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Resources\Inventory\InventoryCount\InventoryCountResource;
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
use Filament\Schemas\Schema;
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
            $this->surveyAction(),
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
                'salida' => 'El vale de lo que sale sin orden de trabajo: consumo, dotación, un daño, una baja. Lo que piden las órdenes se entrega en Despachos.',
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
                    Select::make('exit_reason')
                        ->label('Motivo')
                        ->options(InventoryExitReason::options())
                        ->default(InventoryExitReason::PlantConsumption->value)
                        ->live()
                        ->visible($kind === 'salida')
                        ->required($kind === 'salida'),
                    TextInput::make('recipient_name')
                        ->label('Quién recibe')
                        ->placeholder('Nombre de quien se lo lleva')
                        ->datalist(fn (): array => $kind === 'salida' ? app(WarehouseDashboard::class)->recipientSuggestions($this->tenant()) : [])
                        ->maxLength(150)
                        ->visible($kind === 'salida')
                        ->required(fn (Get $get): bool => $kind === 'salida' && (InventoryExitReason::tryFrom((string) $get('exit_reason'))?->requiresRecipient() ?? false)),
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
                        'salida' => $service->recordExit(
                            $warehouse, $part, (float) $data['quantity'], $cost, $user, $data['reference'] ?? null, $data['notes'] ?? null,
                            InventoryExitReason::from($data['exit_reason']), filled($data['recipient_name'] ?? null) ? $data['recipient_name'] : null,
                        ),
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
     * El levantamiento: parado frente al estante, con el celular, se anota lo que hay. El
     * repuesto nace con su código, su ubicación y su existencia inicial en un solo paso, y
     * el formulario queda abierto en el mismo estante para seguir con el siguiente.
     */
    private function surveyAction(): Action
    {
        return Action::make('levantar')
            ->label('Levantar repuesto')
            ->icon('heroicon-o-qr-code')
            ->color('info')
            ->authorize(fn (): bool => self::allows('spare-parts.create') && self::allows('inventory.entry'))
            ->modalHeading('Levantar repuesto')
            ->modalDescription('Lo que encuentra en el estante: el sistema le asigna el código y lo deja como existencia inicial.')
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->placeholder('Rodamiento 6205 2RS')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('category_type')
                        ->label('Categoría')
                        ->options(SparePartCategoryType::options())
                        ->default(SparePartCategoryType::Mechanical->value)
                        ->required(),
                    Select::make('unit')
                        ->label('Unidad')
                        ->options(SparePartUnit::options())
                        ->default(SparePartUnit::Unit->value)
                        ->required(),
                    Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?string => Warehouse::query()->where('is_active', true)->count() === 1 ? Warehouse::query()->where('is_active', true)->value('id') : null)
                        ->required(),
                    TextInput::make('bin_location')
                        ->label('Ubicación')
                        ->placeholder('Estante A · nivel 2')
                        ->datalist(fn (): array => WarehouseSparePart::query()->whereNotNull('bin_location')->distinct()->orderBy('bin_location')->pluck('bin_location')->all())
                        ->maxLength(50),
                    TextInput::make('quantity')
                        ->label('Cantidad que hay')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('unit_cost')
                        ->label('Costo unitario')
                        ->helperText('Si no lo sabe, déjelo en cero y se corrige con la primera factura.')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('$'),
                    TextInput::make('minimum_stock')
                        ->label('Mínimo')
                        ->helperText('Con cuánto avisar para pedir.')
                        ->numeric()
                        ->minValue(0),
                    Select::make('criticality')
                        ->label('Criticidad')
                        ->options(SparePartCriticality::options())
                        ->default(SparePartCriticality::Medium->value)
                        ->required(),
                ]),
            ])
            ->modalSubmitActionLabel('Guardar')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('levantarOtro', arguments: ['otro' => true])->label('Guardar y levantar otro'),
            ])
            ->action(function (array $data, array $arguments, Action $action, Schema $schema): void {
                /** @var User $user */
                $user = auth()->user();
                $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

                $part = app(SparePartService::class)->create([
                    'tenant_id' => $this->tenant()->id,
                    'name' => $data['name'],
                    'category_type' => $data['category_type'],
                    'criticality' => $data['criticality'],
                    'unit' => $data['unit'],
                    'unit_cost' => (float) ($data['unit_cost'] ?? 0),
                    'minimum_stock' => filled($data['minimum_stock'] ?? null) ? (float) $data['minimum_stock'] : null,
                ], $user);

                app(InventoryService::class)->registerInitialCount(
                    $warehouse, $part, (float) $data['quantity'], (float) ($data['unit_cost'] ?? 0), $user, $data['bin_location'] ?? null,
                );

                Notification::make()
                    ->title("{$part->code} · {$part->name}")
                    ->body('Quedó con '.self::number((float) $data['quantity']).(filled($data['bin_location'] ?? null) ? ' en '.$data['bin_location'] : '').'.')
                    ->success()
                    ->send();

                if ($arguments['otro'] ?? false) {
                    // Mismo almacén y mismo estante: lo normal es seguir con el de al lado.
                    $schema->fill([
                        'warehouse_id' => $data['warehouse_id'],
                        'bin_location' => $data['bin_location'] ?? null,
                        'category_type' => $data['category_type'],
                    ]);
                    $action->halt();
                }
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
                'label' => 'Por despachar',
                'value' => number_format($attention['pendingDispatches'], 0, ',', '.'),
                'detail' => 'Repuestos pedidos por órdenes de trabajo',
                'icon' => 'heroicon-o-clipboard-document-check',
                'tone' => $attention['pendingDispatches'] > 0 ? 'warning' : 'ok',
                'url' => Despachos::getUrl(),
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
                'label' => 'Por contar',
                'value' => number_format($dueCount = app(InventoryCountService::class)->dueCount($tenant->id), 0, ',', '.'),
                'detail' => 'Les toca conteo cíclico según su ABC',
                'icon' => 'heroicon-o-clipboard-document-list',
                'tone' => $dueCount > 0 ? 'warning' : 'ok',
                'url' => InventoryCountResource::canCreate() ? InventoryCountResource::getUrl('create') : InventoryCountResource::getUrl('index'),
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
            // Sin almacén no se puede levantar ni registrar nada: es lo primero.
            WarehouseResource::canCreate() && ! Warehouse::query()->exists() ? ['label' => 'Crear el primer almacén', 'icon' => 'heroicon-o-plus-circle', 'url' => WarehouseResource::getUrl('create')] : null,
            Despachos::canAccess() ? ['label' => 'Despachos', 'icon' => 'heroicon-o-clipboard-document-check', 'url' => Despachos::getUrl()] : null,
            SparePartResource::canViewAny() ? ['label' => 'Repuestos', 'icon' => 'heroicon-o-wrench-screwdriver', 'url' => SparePartResource::getUrl('index')] : null,
            WarehouseResource::canViewAny() ? ['label' => 'Almacenes', 'icon' => 'heroicon-o-building-storefront', 'url' => WarehouseResource::getUrl('index')] : null,
            PurchaseOrderResource::canViewAny() ? ['label' => 'Órdenes de compra', 'icon' => 'heroicon-o-truck', 'url' => PurchaseOrderResource::getUrl('index')] : null,
            InventoryCountResource::canViewAny() ? ['label' => 'Conteos', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => InventoryCountResource::getUrl('index')] : null,
            Kardex::canAccess() ? ['label' => 'Kardex', 'icon' => 'heroicon-o-arrows-right-left', 'url' => Kardex::getUrl()] : null,
            ReportesAlmacen::canAccess() ? ['label' => 'Reportes', 'icon' => 'heroicon-o-chart-bar', 'url' => ReportesAlmacen::getUrl()] : null,
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
            // El indicador de talento humano: solo para quien ve la nómina, no para la cuenta de almacén.
            'factor' => FactorDeHoras::dashboardIndicator($tenant),
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
