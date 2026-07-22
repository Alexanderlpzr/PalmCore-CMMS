<?php

namespace App\Filament\Resources\Inventory\PurchaseOrder;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ListPurchaseOrders;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ViewPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $modelLabel = 'Orden de Compra';

    protected static ?string $pluralModelLabel = 'Órdenes de Compra';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        // Oculto para los roles de tenant; solo el superadministrador de plataforma lo ve.
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la orden')
                ->columns(2)
                ->schema([
                    Select::make('supplier_id')
                        ->label('Proveedor')
                        ->options(fn (): array => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    Select::make('warehouse_id')
                        ->label('Almacén de recepción')
                        ->options(fn (): array => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    DatePicker::make('expected_at')
                        ->label('Fecha esperada')
                        ->displayFormat('d/m/Y'),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Renglones')
                ->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->addActionLabel('Agregar repuesto')
                        ->minItems(1)
                        ->columns(3)
                        ->schema([
                            Select::make('spare_part_id')
                                ->label('Repuesto')
                                ->options(fn (): array => SparePart::query()->where('is_active', true)->orderBy('code')
                                    ->get()->mapWithKeys(fn (SparePart $p) => [$p->id => "{$p->code} — {$p->name}"])->all())
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                    if ($state && ($part = SparePart::find($state))) {
                                        $set('unit_cost', (float) $part->unit_cost);
                                    }
                                })
                                ->columnSpan(1),
                            TextInput::make('quantity_ordered')
                                ->label('Cantidad')
                                ->numeric()
                                ->minValue(0.0001)
                                ->required(),
                            TextInput::make('unit_cost')
                                ->label('Costo unitario')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('$')
                                ->required(),
                        ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Orden de Compra')
                ->columns(3)
                ->schema([
                    TextEntry::make('po_number')->label('Número')->weight('bold')->copyable(),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (PurchaseOrderStatus $state): string => $state->color())
                        ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->label()),
                    TextEntry::make('total')->label('Total')->money('COP'),
                    TextEntry::make('supplier.name')->label('Proveedor')->placeholder('—'),
                    TextEntry::make('warehouse.name')->label('Almacén'),
                    TextEntry::make('expected_at')->label('Esperada')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('ordered_at')->label('Enviada')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('received_at')->label('Recibida')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Renglones')
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('sparePart.code')->label('Repuesto')
                                ->formatStateUsing(fn ($state, $record): string => "{$record->sparePart?->code} — {$record->sparePart?->name}")
                                ->columnSpan(1),
                            TextEntry::make('quantity_ordered')->label('Pedido')
                                ->numeric(decimalPlaces: 2),
                            TextEntry::make('quantity_received')->label('Recibido')
                                ->numeric(decimalPlaces: 2)
                                ->color(fn ($record): string => $record->isFullyReceived() ? 'success' : 'warning'),
                            TextEntry::make('line_total')->label('Total renglón')->money('COP'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')->label('N° OC')->searchable()->sortable()->weight('bold'),
                TextColumn::make('supplier.name')->label('Proveedor')->placeholder('—')->searchable(),
                TextColumn::make('warehouse.name')->label('Almacén'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (PurchaseOrderStatus $state): string => $state->color())
                    ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->label()),
                TextColumn::make('lines_count')->counts('lines')->label('Renglones'),
                TextColumn::make('total')->label('Total')->money('COP')->sortable(),
                TextColumn::make('expected_at')->label('Esperada')->date('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['supplier', 'warehouse']);
    }
}
