<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Widgets\Budget\BudgetByCategoryChartWidget;
use App\Filament\Widgets\Budget\BudgetExpensesTableWidget;
use App\Filament\Widgets\Budget\BudgetProgressChartWidget;
use App\Filament\Widgets\Budget\BudgetStatsWidget;
use App\Models\MaintenanceBudget;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Plant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Seguimiento del presupuesto de mantenimiento del mes: cuánto se asignó, cuánto se
 * lleva gastado, cuánto falta y en qué se invirtió. El gasto se ingresa a mano,
 * semana a semana, con «Agregar gasto».
 */
class Presupuesto extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/presupuesto';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Presupuesto';

    protected static ?string $title = 'Presupuesto';

    protected static ?int $navigationSort = 3;

    public function getColumns(): array|int
    {
        return ['default' => 1, 'md' => 2, 'xl' => 3];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            BudgetStatsWidget::class,
            BudgetProgressChartWidget::class,
            BudgetByCategoryChartWidget::class,
            BudgetExpensesTableWidget::class,
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->addExpenseAction(),
            $this->assignBudgetAction(),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('plant_id')
                ->label('Planta')
                ->options(fn (): array => Plant::where('tenant_id', Filament::getTenant()->id)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->default(fn (): ?string => Plant::where('tenant_id', Filament::getTenant()->id)
                    ->orderBy('name')
                    ->value('id'))
                ->live()
                ->selectablePlaceholder(false),
            Select::make('year')
                ->label('Año')
                ->options(DashboardPeriod::yearOptions())
                ->default(now()->year)
                ->live()
                ->selectablePlaceholder(false),
            Select::make('month')
                ->label('Mes')
                ->options(DashboardPeriod::monthOptions())
                ->default(now()->month)
                ->live()
                ->selectablePlaceholder(false),
        ]);
    }

    private function addExpenseAction(): Action
    {
        return Action::make('addExpense')
            ->label('Agregar gasto')
            ->icon(Heroicon::OutlinedPlus)
            ->color('primary')
            ->modalHeading('Registrar gasto')
            ->schema(BudgetExpensesTableWidget::expenseFormSchema())
            ->action(function (array $data): void {
                $plantId = $this->filters['plant_id'] ?? null;

                if ($plantId === null) {
                    Notification::make()->title('Elige una planta primero')->danger()->send();

                    return;
                }

                MaintenanceBudgetExpense::create([
                    'tenant_id' => Filament::getTenant()->id,
                    'plant_id' => $plantId,
                    'expense_date' => $data['expense_date'],
                    'amount' => (float) $data['amount'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                Notification::make()->title('Gasto registrado')->success()->send();
            });
    }

    private function assignBudgetAction(): Action
    {
        return Action::make('assignBudget')
            ->label('Asignar presupuesto')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->modalHeading('Presupuesto del mes')
            ->fillForm(fn (): array => [
                'amount' => MaintenanceBudget::where('plant_id', $this->filters['plant_id'] ?? null)
                    ->where('year', (int) ($this->filters['year'] ?? now()->year))
                    ->where('month', (int) ($this->filters['month'] ?? now()->month))
                    ->value('amount'),
            ])
            ->schema([
                TextInput::make('amount')
                    ->label('Monto asignado')
                    ->helperText('El techo de gasto de este mes para esta planta.')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('COP')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $plantId = $this->filters['plant_id'] ?? null;

                if ($plantId === null) {
                    Notification::make()->title('Elige una planta primero')->danger()->send();

                    return;
                }

                MaintenanceBudget::updateOrCreate(
                    [
                        'plant_id' => $plantId,
                        'year' => (int) ($this->filters['year'] ?? now()->year),
                        'month' => (int) ($this->filters['month'] ?? now()->month),
                    ],
                    [
                        'tenant_id' => Filament::getTenant()->id,
                        'amount' => (float) $data['amount'],
                        'created_by' => auth()->id(),
                    ],
                );

                Notification::make()->title('Presupuesto guardado')->success()->send();
            });
    }
}
