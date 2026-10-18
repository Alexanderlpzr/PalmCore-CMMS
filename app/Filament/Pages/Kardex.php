<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Reports\Excel\KardexExcel;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
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
            ->filters([
                Filter::make('fechas')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, string $from) => $q->where('performed_at', '>=', CarbonImmutable::parse($from, 'America/Bogota')->startOfDay()->utc()))
                        ->when($data['hasta'] ?? null, fn (Builder $q, string $to) => $q->where('performed_at', '<=', CarbonImmutable::parse($to, 'America/Bogota')->endOfDay()->utc())))
                    ->indicateUsing(fn (array $data): ?string => ($data['desde'] ?? null) || ($data['hasta'] ?? null)
                        ? 'Del '.($data['desde'] ? CarbonImmutable::parse($data['desde'])->format('d/m/Y') : 'inicio').' al '.($data['hasta'] ? CarbonImmutable::parse($data['hasta'])->format('d/m/Y') : 'hoy')
                        : null),
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
