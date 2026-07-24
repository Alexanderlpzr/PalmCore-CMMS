<?php

namespace App\Filament\Widgets\Budget;

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Plant;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Los gastos del mes, para revisarlos y corregirlos. El alta («Agregar gasto») vive
 * en el encabezado de la página; aquí se editan o borran los que ya están.
 */
class BudgetExpensesTableWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gastos del mes')
            ->query($this->expensesQuery())
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Concepto')
                    ->badge()
                    ->formatStateUsing(fn (?ExpenseCategory $state): string => $state?->label() ?? '—')
                    ->color(fn (?ExpenseCategory $state): string => $state?->color() ?? 'gray'),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('COP')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),
                TextColumn::make('createdBy.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(self::expenseFormSchema()),
                DeleteAction::make(),
            ])
            ->defaultSort('expense_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * El formulario de un gasto — compartido por «Agregar gasto» (página) y editar.
     *
     * @return array<int, mixed>
     */
    public static function expenseFormSchema(): array
    {
        return [
            DatePicker::make('expense_date')
                ->label('Fecha')
                ->native(false)
                ->default(now())
                ->maxDate(now())
                ->required(),
            Select::make('category')
                ->label('Concepto')
                ->options(ExpenseCategory::options())
                ->native(false)
                ->required(),
            TextInput::make('amount')
                ->label('Monto')
                ->numeric()
                ->minValue(0)
                ->prefix('COP')
                ->required(),
            Textarea::make('description')
                ->label('Descripción')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }

    /** @return Builder<MaintenanceBudgetExpense> */
    private function expensesQuery(): Builder
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;
        $plant = $plantId !== null ? Plant::find($plantId) : Plant::orderBy('name')->first();

        $from = Carbon::create(
            (int) ($this->pageFilters['year'] ?? now()->year),
            (int) ($this->pageFilters['month'] ?? now()->month),
            1,
        )->startOfMonth();

        return MaintenanceBudgetExpense::query()
            ->when(
                $plant !== null,
                fn (Builder $query): Builder => $query->where('plant_id', $plant->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->whereBetween('expense_date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()]);
    }
}
