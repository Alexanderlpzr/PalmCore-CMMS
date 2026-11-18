<?php

namespace App\Filament\Resources\Commercial\SalesDispatch;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Enums\Market;
use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PeriodFilter;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\CreateSalesDispatch;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\EditSalesDispatch;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ListSalesDispatches;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ViewSalesDispatch;
use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use App\Models\Carrier;
use App\Models\Customer;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Los despachos de aceite y palmiste: la hoja «Despacho 2026» del Excel de la Directora
 * Administrativa, una ficha por cargue. Nace en el cargue y avanza con tres pasos —el
 * descargue, la factura y el pago— que llenan sus datos y le cambian el estado solo.
 */
class SalesDispatchResource extends Resource
{
    protected static ?string $model = SalesDispatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'Despacho';

    protected static ?string $pluralModelLabel = 'Despachos';

    protected static ?string $navigationLabel = 'Despachos';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    // «despachos» ya es la página del almacén: lo que sale a las órdenes de trabajo.
    protected static ?string $slug = 'despachos-venta';

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof SalesDispatch ? "Despacho {$record->label()}" : 'Despacho';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cargue')
                ->description('Lo que sale de la planta.')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->schema([
                    Select::make('product')
                        ->label('Producto')
                        ->options(CommercialProduct::options())
                        ->default(CommercialProduct::Cpo->value)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('sales_offer_id', null))
                        ->required(),
                    DatePicker::make('loaded_on')
                        ->label('Fecha de cargue')
                        ->default(fn (): string => SalesDispatch::today())
                        ->required(),
                    Select::make('customer_id')
                        ->label('Cliente')
                        ->options(fn (): array => Customer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('sales_offer_id', null);
                            $set('market', Customer::query()->find($state)?->market?->value ?? Market::Nacional->value);
                        })
                        ->required(),
                    Select::make('sales_offer_id')
                        ->label('Oferta mercantil')
                        ->options(fn (Get $get, ?SalesDispatch $record): array => self::offerOptions($get('customer_id'), $get('product'), $record?->sales_offer_id))
                        ->searchable()
                        ->placeholder(fn (Get $get): string => blank($get('customer_id')) ? 'Escoja primero el cliente' : 'Sin oferta')
                        ->helperText('Las abiertas del cliente para este producto. El peso de llegada descuenta de su saldo.'),
                    Select::make('market')
                        ->label('Destino')
                        ->options(Market::options())
                        ->default(Market::Nacional->value)
                        ->selectablePlaceholder(false)
                        ->required(),
                    Select::make('carrier_id')
                        ->label('Transportadora')
                        ->options(fn (): array => Carrier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    TextInput::make('plate')
                        ->label('Placa')
                        ->maxLength(12)
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(str_replace([' ', '-'], '', $state)))
                        ->required(),
                    TextInput::make('remission_number')->label('Remisión')->maxLength(30),
                    TextInput::make('load_number')->label('N.º de cargue')->maxLength(30),
                    TextInput::make('departure_weight_tons')
                        ->label('Peso de salida')
                        ->numeric()->minValue(0.01)->maxValue(60)
                        ->suffix('tn')
                        ->required(),
                    TextInput::make('departure_acidity')
                        ->label('Acidez de salida')
                        ->numeric()->minValue(0)->maxValue(100)
                        ->suffix('%'),
                    TextInput::make('freight_rate_per_ton')
                        ->label('Flete por tonelada')
                        ->numeric()->minValue(0)
                        ->prefix('$')
                        ->helperText('Lo que se le paga a la transportadora.'),
                ]),

            Section::make('Descargue y liquidación del cliente')
                ->description('Se llena con «Registrar descargue»; aquí se corrige.')
                ->collapsible()
                ->collapsed(fn (string $operation): bool => $operation === 'create')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->schema([
                    DatePicker::make('unloaded_on')->label('Fecha de descargue'),
                    TextInput::make('receipt_number')->label('Recibo')->maxLength(30),
                    TextInput::make('arrival_weight_tons')->label('Peso de llegada')->numeric()->minValue(0)->suffix('tn'),
                    TextInput::make('arrival_acidity')->label('Acidez de llegada')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('settlement_amount')->label('Total de la liquidación')->numeric()->prefix('$'),
                    TextInput::make('quality_bonus')->label('Bonificación por calidad')->numeric()->prefix('$'),
                ]),

            Section::make('Factura y pago')
                ->collapsible()
                ->collapsed(fn (string $operation): bool => $operation === 'create')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->schema([
                    TextInput::make('invoice_number')->label('N.º de factura')->maxLength(30)
                        ->requiredWith('invoice_amount'),
                    DatePicker::make('invoiced_on')->label('Fecha de factura')
                        ->requiredWith('invoice_number'),
                    TextInput::make('invoice_amount')->label('Valor de la factura')->numeric()->prefix('$')
                        ->requiredWith('invoice_number'),
                    TextInput::make('billed_freight')->label('Flete cobrado al cliente')->numeric()->prefix('$'),
                    DatePicker::make('paid_on')->label('Fecha de pago')
                        ->helperText('Con fecha de pago, el despacho queda pagado.'),
                ]),

            Textarea::make('notes')->label('Notas')->rows(2)->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cargue')
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('status')->label('Estado')->badge()
                        ->formatStateUsing(fn (DispatchStatus $state): string => $state->label())
                        ->color(fn (DispatchStatus $state): string => $state->color()),
                    TextEntry::make('product')->label('Producto')->formatStateUsing(fn (CommercialProduct $state): string => $state->label()),
                    TextEntry::make('customer.name')->label('Cliente'),
                    TextEntry::make('market')->label('Destino')->formatStateUsing(fn (Market $state): string => $state->label()),
                    TextEntry::make('offer.number')->label('Oferta mercantil')->placeholder('Sin oferta')
                        ->url(fn (SalesDispatch $record): ?string => $record->offer ? SalesOfferResource::getUrl('view', ['record' => $record->offer]) : null),
                    TextEntry::make('loaded_on')->label('Fecha de cargue')->date('d/m/Y'),
                    TextEntry::make('plate')->label('Placa'),
                    TextEntry::make('carrier.name')->label('Transportadora')->placeholder('—'),
                    TextEntry::make('remission_number')->label('Remisión')->placeholder('—'),
                    TextEntry::make('load_number')->label('N.º de cargue')->placeholder('—'),
                    TextEntry::make('departure_weight_tons')->label('Peso de salida')->formatStateUsing(fn ($state): string => SalesOfferResource::tons((float) $state)),
                    TextEntry::make('departure_acidity')->label('Acidez de salida')->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' %')->placeholder('—'),
                ]),
            Section::make('Descargue y liquidación')
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('unloaded_on')->label('Fecha de descargue')->date('d/m/Y')->placeholder('En tránsito'),
                    TextEntry::make('receipt_number')->label('Recibo')->placeholder('—'),
                    TextEntry::make('arrival_weight_tons')->label('Peso de llegada')->placeholder('—')
                        ->formatStateUsing(fn ($state): string => SalesOfferResource::tons((float) $state)),
                    TextEntry::make('merma')->label('Merma')
                        ->state(fn (SalesDispatch $record): ?string => $record->weightDifference() === null ? null
                            : SalesOfferResource::tons($record->weightDifference()).' ('.SalesOfferResource::percent($record->shrinkageRatio()).')')
                        ->placeholder('—'),
                    TextEntry::make('arrival_acidity')->label('Acidez de llegada')->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' %')->placeholder('—')
                        ->color(fn (SalesDispatch $record): ?string => $record->hasHighAcidity() ? 'danger' : null),
                    TextEntry::make('settlement_amount')->label('Total liquidación')->money('COP', decimalPlaces: 0)->placeholder('—'),
                    TextEntry::make('quality_bonus')->label('Bonificación')->money('COP', decimalPlaces: 0)->placeholder('—'),
                ]),
            Section::make('Factura, pago y flete')
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('invoice_number')->label('N.º de factura')->placeholder('Sin facturar'),
                    TextEntry::make('invoiced_on')->label('Fecha de factura')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('invoice_amount')->label('Valor de la factura')->money('COP', decimalPlaces: 0)->placeholder('—'),
                    TextEntry::make('diferencia')->label('Diferencia con la liquidación')
                        ->state(fn (SalesDispatch $record): ?float => $record->invoiceDifference())
                        ->money('COP', decimalPlaces: 0)
                        ->color(fn (SalesDispatch $record): ?string => abs((float) $record->invoiceDifference()) >= 1 ? 'danger' : null)
                        ->placeholder('—'),
                    TextEntry::make('paid_on')->label('Fecha de pago')->date('d/m/Y')
                        ->placeholder(fn (SalesDispatch $record): string => $record->status === DispatchStatus::Pagado ? 'Pagado (sin fecha)' : 'Pendiente'),
                    TextEntry::make('billed_freight')->label('Flete cobrado')->money('COP', decimalPlaces: 0)->placeholder('—'),
                    TextEntry::make('flete')->label('Costo del flete')
                        ->state(fn (SalesDispatch $record): ?float => $record->freightCost())
                        ->money('COP', decimalPlaces: 0)
                        ->helperText(fn (SalesDispatch $record): ?string => $record->freight_rate_per_ton === null ? null : '$'.number_format((float) $record->freight_rate_per_ton, 0, ',', '.').' por tonelada')
                        ->placeholder('—'),
                    TextEntry::make('precio')->label('Precio neto por tonelada')
                        ->state(fn (SalesDispatch $record): ?float => $record->netPricePerTon())
                        ->money('COP', decimalPlaces: 0)
                        ->helperText('Factura menos flete, sobre el peso de salida.')
                        ->placeholder('—'),
                    TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'offer', 'carrier']))
            ->columns([
                TextColumn::make('loaded_on')->label('Cargue')->date('d/m/Y')->sortable(),
                TextColumn::make('plate')->label('Placa')->searchable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('product')->label('Producto')
                    ->formatStateUsing(fn (CommercialProduct $state): string => $state->shortLabel()),
                TextColumn::make('offer.number')->label('Oferta')->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('remission_number')->label('Remisión')->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('carrier.name')->label('Transportadora')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('departure_weight_tons')->label('Salida (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('arrival_weight_tons')->label('Llegada (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('arrival_acidity')->label('Acidez (%)')
                    ->state(fn (SalesDispatch $record): ?string => $record->arrival_acidity ?? $record->departure_acidity)
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn (SalesDispatch $record): ?string => $record->hasHighAcidity() ? 'danger' : null)
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('invoice_number')->label('Factura')->placeholder('—')->searchable(),
                TextColumn::make('invoice_amount')->label('Valor factura')->money('COP', decimalPlaces: 0)->placeholder('—')->alignEnd()->sortable(),
                TextColumn::make('net_price')->label('Precio neto / tn')
                    ->state(fn (SalesDispatch $record): ?float => $record->netPricePerTon())
                    ->money('COP', decimalPlaces: 0)
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Solo las por cobrar: cuándo vence y, si ya venció, hace cuánto.
                TextColumn::make('due_on')->label('Vence')
                    ->state(fn (SalesDispatch $record): ?string => $record->status === DispatchStatus::Facturado ? $record->dueOn()?->format('d/m/Y') : null)
                    ->description(fn (SalesDispatch $record): ?string => ($days = $record->daysOverdue()) > 0 ? "hace {$days} días" : null)
                    ->color(fn (SalesDispatch $record): ?string => $record->daysOverdue() > 0 ? 'danger' : null)
                    ->weight(fn (SalesDispatch $record): ?string => $record->daysOverdue() > 0 ? 'bold' : null)
                    ->placeholder('—'),
                TextColumn::make('paid_on')->label('Pago')->date('d/m/Y')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (DispatchStatus $state): string => $state->label())
                    ->color(fn (DispatchStatus $state): string => $state->color()),
            ])
            ->headerActions([
                TableExcelExport::action('Despachos'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                PeriodFilter::make('loaded_on', 'Fecha de cargue'),
                SelectFilter::make('customer_id')->label('Cliente')->relationship('customer', 'name')->searchable()->preload(),
                SelectFilter::make('product')->label('Producto')->options(CommercialProduct::options()),
                SelectFilter::make('market')->label('Destino')->options(Market::options()),
                SelectFilter::make('carrier_id')->label('Transportadora')->relationship('carrier', 'name')->preload(),
                Filter::make('overdue')->label('Solo facturas vencidas')->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->recordActions([
                ActionGroup::make([
                    DispatchActions::unload(),
                    DispatchActions::invoice(),
                    DispatchActions::pay(),
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->recordUrl(fn (SalesDispatch $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('loaded_on', 'desc')
            ->emptyStateHeading('No hay despachos aquí')
            ->emptyStateDescription('Registre el cargue cuando el camión sale, o importe el Excel de despachos.');
    }

    /**
     * Las ofertas abiertas del cliente para el producto, y la que ya tiene el despacho
     * aunque esté cerrada, para no perderla al editar.
     *
     * @return array<string, string>
     */
    public static function offerOptions(?string $customerId, ?string $product, ?string $currentOfferId = null): array
    {
        if (blank($customerId)) {
            return [];
        }

        return SalesOffer::query()
            ->with('customer')
            ->withExecution()
            ->where('customer_id', $customerId)
            ->when($product, fn (Builder $query, string $product) => $query->where('product', $product))
            ->where(fn (Builder $query) => $query->open()->when($currentOfferId, fn (Builder $query, string $id) => $query->orWhere('id', $id)))
            ->orderBy('signed_on')
            ->get()
            ->mapWithKeys(fn (SalesOffer $offer): array => [$offer->id => $offer->optionLabel()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesDispatches::route('/'),
            'create' => CreateSalesDispatch::route('/create'),
            'view' => ViewSalesDispatch::route('/{record}'),
            'edit' => EditSalesDispatch::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
