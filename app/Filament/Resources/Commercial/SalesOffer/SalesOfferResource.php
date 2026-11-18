<?php

namespace App\Filament\Resources\Commercial\SalesOffer;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PeriodFilter;
use App\Filament\Resources\Commercial\SalesOffer\Pages\CreateSalesOffer;
use App\Filament\Resources\Commercial\SalesOffer\Pages\EditSalesOffer;
use App\Filament\Resources\Commercial\SalesOffer\Pages\ListSalesOffers;
use App\Filament\Resources\Commercial\SalesOffer\Pages\ViewSalesOffer;
use App\Filament\Resources\Commercial\SalesOffer\RelationManagers\DispatchesRelationManager;
use App\Models\Customer;
use App\Models\SalesOffer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Las ofertas mercantiles: los contratos de venta. La hoja «Ofertas Mercantiles» del Excel
 * de la Directora Administrativa. Lo despachado, el saldo y el estado salen solos de los
 * despachos que llevan el número de la oferta.
 */
class SalesOfferResource extends Resource
{
    protected static ?string $model = SalesOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $modelLabel = 'Oferta mercantil';

    protected static ?string $pluralModelLabel = 'Ofertas mercantiles';

    protected static ?string $navigationLabel = 'Ofertas mercantiles';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'ofertas-mercantiles';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof SalesOffer ? "Oferta {$record->number}" : 'Oferta mercantil';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('La oferta')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('customer_id')
                        ->label('Cliente')
                        ->options(fn (): array => Customer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    Select::make('product')
                        ->label('Producto')
                        ->options(CommercialProduct::options())
                        ->default(CommercialProduct::Cpo->value)
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('number')
                        ->label('N.º de oferta')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', filament()->getTenant()?->getKey())->whereNull('deleted_at')),
                    TextInput::make('contracted_tons')
                        ->label('Toneladas ofertadas')
                        ->numeric()
                        ->minValue(0.01)
                        ->suffix('tn')
                        ->helperText('Una tractomula son unas 34 tn.')
                        ->required(),
                    DatePicker::make('signed_on')
                        ->label('Fecha de la oferta'),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Avance')
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('customer.name')->label('Cliente'),
                    TextEntry::make('product')->label('Producto')
                        ->formatStateUsing(fn (CommercialProduct $state): string => $state->label()),
                    TextEntry::make('contracted_tons')->label('Ofertadas')
                        ->formatStateUsing(fn ($state): string => self::tons((float) $state)),
                    TextEntry::make('delivered')->label('Despachadas')
                        ->state(fn (SalesOffer $record): string => self::tons($record->deliveredTons())),
                    TextEntry::make('remaining')->label('Saldo')
                        ->state(fn (SalesOffer $record): string => self::tons($record->remainingTons()))
                        ->color(fn (SalesOffer $record): ?string => $record->remainingTons() < -SalesOffer::CLOSING_TOLERANCE_TONS ? 'danger' : null)
                        ->helperText(fn (SalesOffer $record): ?string => $record->remainingTons() < -SalesOffer::CLOSING_TOLERANCE_TONS ? 'Se despachó más de lo ofertado: ¿hubo una ampliación sin registrar?' : null),
                    TextEntry::make('executed')->label('% ejecutado')
                        ->state(fn (SalesOffer $record): string => self::percent($record->executedRatio())),
                    TextEntry::make('status')->label('Estado')->badge()
                        ->state(fn (SalesOffer $record): string => $record->isClosed() ? 'Cerrada' : 'Abierta')
                        ->color(fn (SalesOffer $record): string => $record->isClosed() ? 'gray' : 'success'),
                    TextEntry::make('signed_on')->label('Fecha de la oferta')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customer')->withExecution())
            ->columns([
                TextColumn::make('number')->label('N.º oferta')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('product')->label('Producto')
                    ->formatStateUsing(fn (CommercialProduct $state): string => $state->shortLabel()),
                TextColumn::make('contracted_tons')->label('Ofertadas (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('delivered_tons')->label('Despachadas (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->placeholder('0,00')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('remaining')->label('Saldo (tn)')
                    ->state(fn (SalesOffer $record): float => $record->remainingTons())
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn (SalesOffer $record): ?string => $record->remainingTons() < -SalesOffer::CLOSING_TOLERANCE_TONS ? 'danger' : null)
                    ->alignEnd(),
                TextColumn::make('executed')->label('% ejecutado')
                    ->state(fn (SalesOffer $record): string => self::percent($record->executedRatio()))
                    ->alignEnd(),
                TextColumn::make('dispatches_count')->label('Cargues')->alignEnd()->sortable(),
                TextColumn::make('first_loaded_on')->label('Primer despacho')->date('d/m/Y')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('last_loaded_on')->label('Último despacho')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->state(fn (SalesOffer $record): string => $record->isClosed() ? 'Cerrada' : 'Abierta')
                    ->color(fn (SalesOffer $record): string => $record->isClosed() ? 'gray' : 'success'),
            ])
            ->headerActions([
                TableExcelExport::action('Ofertas mercantiles'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                PeriodFilter::make('signed_on', 'Fecha de la oferta'),
                SelectFilter::make('customer_id')->label('Cliente')->relationship('customer', 'name')->searchable()->preload(),
                SelectFilter::make('product')->label('Producto')->options(CommercialProduct::options()),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('signed_on', 'desc')
            ->emptyStateHeading('No hay ofertas aquí')
            ->emptyStateDescription('Registre la oferta con su cliente, su número y las toneladas: cada despacho que la lleve descontará su saldo.');
    }

    public static function getRelations(): array
    {
        return [
            DispatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOffers::route('/'),
            'create' => CreateSalesOffer::route('/create'),
            'view' => ViewSalesOffer::route('/{record}'),
            'edit' => EditSalesOffer::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function tons(?float $tons): string
    {
        return $tons === null ? '—' : number_format($tons, 2, ',', '.').' tn';
    }

    public static function percent(?float $ratio): string
    {
        return $ratio === null ? '—' : number_format($ratio * 100, 1, ',', '.').' %';
    }
}
