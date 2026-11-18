<?php

namespace App\Filament\Resources\Commercial\KernelTollLoad;

use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PeriodFilter;
use App\Filament\Resources\Commercial\KernelTollLoad\Pages\ManageKernelTollLoads;
use App\Models\KernelTollLoad;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * La maquila de la almendra: cada cargue que se manda a procesar a un tercero, con la torta
 * y el aceite estimado que devuelve, lo que cuestan la maquila y el flete, y lo que deja
 * la torta. La tabla «MAQUILA» del Excel de la Directora Administrativa.
 */
class KernelTollLoadResource extends Resource
{
    protected static ?string $model = KernelTollLoad::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'Cargue a maquila';

    protected static ?string $pluralModelLabel = 'Maquila de almendra';

    protected static ?string $navigationLabel = 'Maquila de almendra';

    protected static string|UnitEnum|null $navigationGroup = 'Producción';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'maquila';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('El cargue')
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    DatePicker::make('loaded_on')->label('Fecha de cargue')->default(now()->toDateString())->required(),
                    TextInput::make('plate')->label('Placa')->maxLength(12)
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(str_replace([' ', '-'], '', $state))),
                    TextInput::make('remission_number')->label('Remisión')->maxLength(30),
                    TextInput::make('departure_weight_tons')->label('Peso de salida')->numeric()->minValue(0.01)->suffix('tn')->required(),
                    DatePicker::make('unloaded_on')->label('Fecha de descargue'),
                    TextInput::make('arrival_weight_tons')->label('Peso de llegada')->numeric()->minValue(0)->suffix('tn'),
                ]),
            Section::make('Lo que devolvió la maquila')
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    TextInput::make('processed_kg')->label('Entró a proceso')->numeric()->integer()->minValue(0)->suffix('kg'),
                    TextInput::make('oil_kg')->label('Aceite estimado')->numeric()->integer()->minValue(0)->suffix('kg')
                        ->helperText('Cifra interna: no es una venta.'),
                    TextInput::make('cake_kg')->label('Torta')->numeric()->integer()->minValue(0)->suffix('kg'),
                ]),
            Section::make('Costos')
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    TextInput::make('toll_amount')->label('Precio de la maquila')->numeric()->minValue(0)->prefix('$'),
                    TextInput::make('freight_amount')->label('Flete')->numeric()->minValue(0)->prefix('$'),
                    TextInput::make('cake_price_per_ton')->label('Precio de la torta')->numeric()->minValue(0)->prefix('$')->suffix('/ tn'),
                    Textarea::make('notes')->label('Notas')->rows(2)->maxLength(2000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loaded_on')->label('Cargue')->date('d/m/Y')->sortable(),
                TextColumn::make('plate')->label('Placa')->placeholder('—')->searchable(),
                TextColumn::make('remission_number')->label('Remisión')->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('arrival_weight_tons')->label('Llegada (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->placeholder('—')->alignEnd(),
                TextColumn::make('processed_kg')->label('A proceso (kg)')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: '.')->placeholder('—')->alignEnd(),
                TextColumn::make('shrinkage')->label('Merma (kg)')
                    ->state(fn (KernelTollLoad $record): ?int => $record->shrinkageKg())
                    ->numeric(decimalPlaces: 0, thousandsSeparator: '.')->placeholder('—')->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('oil_kg')->label('Aceite (kg)')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: '.')->placeholder('—')->alignEnd()
                    ->description(fn (KernelTollLoad $record): ?string => $record->oilYield() === null ? null : number_format($record->oilYield() * 100, 1, ',', '.').' %'),
                TextColumn::make('cake_kg')->label('Torta (kg)')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: '.')->placeholder('—')->alignEnd()
                    ->description(fn (KernelTollLoad $record): ?string => $record->cakeYield() === null ? null : number_format($record->cakeYield() * 100, 1, ',', '.').' %'),
                TextColumn::make('toll_amount')->label('Maquila')->money('COP', decimalPlaces: 0)->placeholder('—')->alignEnd(),
                TextColumn::make('freight_amount')->label('Flete')->money('COP', decimalPlaces: 0)->placeholder('—')->alignEnd()->toggleable(),
                TextColumn::make('income')->label('Torta ($)')
                    ->state(fn (KernelTollLoad $record): ?float => $record->cakeIncome())
                    ->money('COP', decimalPlaces: 0)->placeholder('—')->alignEnd(),
                TextColumn::make('balance')->label('Saldo')
                    ->state(fn (KernelTollLoad $record): ?float => $record->balance())
                    ->money('COP', decimalPlaces: 0)->placeholder('—')->alignEnd()
                    ->color(fn (KernelTollLoad $record): ?string => ($record->balance() ?? 0) < 0 ? 'danger' : 'success'),
            ])
            ->headerActions([TableExcelExport::action('Maquila de almendra')])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                PeriodFilter::make('loaded_on', 'Fecha de cargue'),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->defaultSort('loaded_on', 'desc')
            ->emptyStateHeading('No hay cargues a maquila')
            ->emptyStateDescription('Registre cada cargue de almendra que se manda a procesar, o importe el Excel en «Plan anual».');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKernelTollLoads::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
