<?php

namespace App\Filament\Resources\Commercial\KernelInventoryCount;

use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PeriodFilter;
use App\Filament\Resources\Commercial\KernelInventoryCount\Pages\ManageKernelInventoryCounts;
use App\Models\KernelInventoryCount;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Los cortes de inventario de palmiste (PKO) y de torta en bodega: la hoja «Inventario
 * Palmiste PKO» del Excel. Un corte por fecha.
 */
class KernelInventoryCountResource extends Resource
{
    protected static ?string $model = KernelInventoryCount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'Corte de inventario';

    protected static ?string $pluralModelLabel = 'Inventario de palmiste y torta';

    protected static ?string $navigationLabel = 'Inventario de palmiste';

    protected static string|UnitEnum|null $navigationGroup = 'Producción';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'inventario-palmiste';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'sm' => 3])->components([
            DatePicker::make('counted_on')
                ->label('Fecha del corte')
                ->default(now()->toDateString())
                ->maxDate(now()->toDateString())
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', filament()->getTenant()?->getKey())->whereNull('deleted_at'))
                ->required(),
            TextInput::make('pko_tons')->label('Palmiste (PKO)')->numeric()->minValue(0)->suffix('tn')
                ->requiredWithout('cake_tons'),
            TextInput::make('cake_tons')->label('Torta de palmiste')->numeric()->minValue(0)->suffix('tn'),
            Textarea::make('notes')->label('Notas')->rows(2)->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('counted_on')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('pko_tons')->label('Palmiste (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->placeholder('—')->alignEnd(),
                TextColumn::make('cake_tons')->label('Torta (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->placeholder('—')->alignEnd(),
                TextColumn::make('total')->label('Total (tn)')
                    ->state(fn (KernelInventoryCount $record): float => $record->totalTons())
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->alignEnd(),
                TextColumn::make('notes')->label('Notas')->placeholder('—')->limit(50)
                    ->tooltip(fn (KernelInventoryCount $record): ?string => $record->notes),
            ])
            ->headerActions([TableExcelExport::action('Inventario de palmiste y torta')])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([PeriodFilter::make('counted_on', 'Fecha del corte')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('counted_on', 'desc')
            ->emptyStateHeading('No hay cortes de inventario')
            ->emptyStateDescription('Registre cuánto palmiste y cuánta torta hay en bodega.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKernelInventoryCounts::route('/'),
        ];
    }
}
