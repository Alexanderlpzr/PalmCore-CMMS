<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Reports\Excel\KardexExcel;
use App\Filament\Filters\PeriodFilter;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * El kardex de todo el almacén: cada entrada, salida, ajuste y despacho, con su saldo y su
 * valor al costo promedio. Se filtra por fechas, almacén, tipo o repuesto y se descarga en
 * Excel tal como se ve, para contabilidad o una auditoría.
 */
class Kardex extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Kardex';

    protected static ?string $title = 'Kardex de movimientos';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'kardex';

    protected string $view = 'filament.pages.kardex';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('inventory.view'));
    }

    /**
     * Los movimientos que muestra la tabla —con sus filtros—, en pesos, para las tarjetas
     * de arriba. Los ajustes van netos: lo que sobró menos lo que faltó.
     *
     * @return array{movements: int, parts: int, inValue: float, inCount: int, outValue: float, outCount: int, adjustValue: float, adjustCount: int}
     */
    public function resumen(): array
    {
        $query = $this->getFilteredTableQuery();

        if ($query === null) {
            return ['movements' => 0, 'parts' => 0, 'inValue' => 0.0, 'inCount' => 0, 'outValue' => 0.0, 'outCount' => 0, 'adjustValue' => 0.0, 'adjustCount' => 0];
        }

        $base = $query->toBase()->cloneWithout(['columns', 'orders']);
        $adjust = InventoryTransactionType::Adjustment->value;
        $totals = (clone $base)->selectRaw(
            'count(*) as movements,
            count(distinct spare_part_id) as parts,
            coalesce(sum(case when type <> ? and quantity > 0 then quantity * unit_cost end), 0) as in_value,
            count(case when type <> ? and quantity > 0 then 1 end) as in_count,
            coalesce(sum(case when type <> ? and quantity < 0 then -quantity * unit_cost end), 0) as out_value,
            count(case when type <> ? and quantity < 0 then 1 end) as out_count,
            coalesce(sum(case when type = ? then quantity * unit_cost end), 0) as adjust_value,
            count(case when type = ? and quantity <> 0 then 1 end) as adjust_count',
            [$adjust, $adjust, $adjust, $adjust, $adjust, $adjust],
        )->first();

        return [
            'movements' => (int) $totals->movements,
            'parts' => (int) $totals->parts,
            'inValue' => round((float) $totals->in_value, 0),
            'inCount' => (int) $totals->in_count,
            'outValue' => round((float) $totals->out_value, 0),
            'outCount' => (int) $totals->out_count,
            'adjustValue' => round((float) $totals->adjust_value, 0),
            'adjustCount' => (int) $totals->adjust_count,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InventoryTransaction::query()->with(['warehouse:id,name', 'performedBy:id,name', 'workOrder:id,work_order_number']))
            ->columns([
                TextColumn::make('performed_at')->label('Fecha')->dateTime('d/m/Y h:i a')->sortable(),
                TextColumn::make('transaction_number')->label('Número')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Movimiento')
                    ->badge()
                    ->formatStateUsing(fn (InventoryTransaction $record): string => $record->exit_reason?->label() ?? $record->type->label())
                    ->color(fn (InventoryTransactionType $state): string => $state->color()),
                TextColumn::make('spare_part_name_snapshot')
                    ->label('Repuesto')
                    ->description(fn (InventoryTransaction $record): string => $record->spare_part_code_snapshot)
                    ->searchable(['spare_part_name_snapshot', 'spare_part_code_snapshot'])
                    ->limitWithTooltip(36),
                TextColumn::make('warehouse.name')->label('Almacén')->toggleable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->color(fn ($state): string => (float) $state > 0 ? 'success' : ((float) $state < 0 ? 'danger' : 'gray')),
                TextColumn::make('unit_cost')->label('Costo unit.')->money('COP', decimalPlaces: 0)->alignEnd()->toggleable(),
                TextColumn::make('total_cost')->label('Valor')->money('COP', decimalPlaces: 0)->alignEnd(),
                TextColumn::make('new_stock')->label('Saldo')->numeric(decimalPlaces: 2)->alignEnd(),
                TextColumn::make('reference_number')->label('Referencia')->placeholder('—')->searchable(),
                TextColumn::make('recipient_name')->label('Recibió')->placeholder('—')->searchable(),
                TextColumn::make('performedBy.name')->label('Registró')->toggleable(isToggledHiddenByDefault: true),
            ])
            // El período a la vista, encima de la tabla, como en talento humano.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                // El día se corta a medianoche de Colombia, como cuenta el almacén.
                PeriodFilter::make('performed_at', 'Fecha del movimiento', timezone: 'America/Bogota'),
                SelectFilter::make('type')->label('Tipo')->options(InventoryTransactionType::options())->multiple(),
                SelectFilter::make('warehouse_id')->label('Almacén')->relationship('warehouse', 'name'),
                SelectFilter::make('spare_part_id')
                    ->label('Repuesto')
                    ->searchable()
                    ->options(fn (): array => SparePart::query()->orderBy('name')->get(['id', 'code', 'name'])
                        ->mapWithKeys(fn (SparePart $part): array => [$part->id => "{$part->code} · {$part->name}"])->all()),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(function (): ?StreamedResponse {
                        $query = $this->getFilteredSortedTableQuery();

                        return $query ? app(KardexExcel::class)->download($query->get()) : null;
                    }),
            ])
            ->defaultSort('performed_at', 'desc')
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('Aquí aparece cada entrada, salida, ajuste y despacho del almacén.');
    }
}
