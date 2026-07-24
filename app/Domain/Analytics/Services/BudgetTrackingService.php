<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Models\MaintenanceBudget;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Seguimiento del presupuesto de mantenimiento contra el gasto real, con el gasto
 * capturado a mano semana a semana (no deducido de las OT). Responde lo que el
 * ingeniero pregunta cada semana: cuánto se asignó, cuánto se lleva gastado, cuánto
 * falta, y en qué se fue.
 */
class BudgetTrackingService
{
    /**
     * @return array{
     *     budget: ?float,
     *     total: float,
     *     remaining: ?float,
     *     percent_used: ?float,
     *     is_over_budget: bool,
     *     by_category: array<string, float>,
     *     weekly: array{labels: list<string>, accumulated: list<float>, budget_line: list<?float>},
     *     expense_count: int,
     * }
     */
    public function monthlyReport(Plant $plant, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $budget = MaintenanceBudget::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $year)
            ->where('month', $month)
            ->value('amount');
        $budgetAmount = $budget !== null ? (float) $budget : null;

        /** @var Collection<int, MaintenanceBudgetExpense> $expenses */
        $expenses = MaintenanceBudgetExpense::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get(['expense_date', 'amount', 'category']);

        $total = round((float) $expenses->sum('amount'), 2);
        $remaining = $budgetAmount !== null ? round($budgetAmount - $total, 2) : null;
        $percentUsed = ($budgetAmount !== null && $budgetAmount > 0)
            ? round($total / $budgetAmount * 100, 1)
            : null;

        $byCategory = [];
        foreach (ExpenseCategory::cases() as $category) {
            $sum = round((float) $expenses->where('category', $category)->sum('amount'), 2);

            if ($sum > 0) {
                $byCategory[$category->value] = $sum;
            }
        }
        arsort($byCategory);

        return [
            'budget' => $budgetAmount,
            'total' => $total,
            'remaining' => $remaining,
            'percent_used' => $percentUsed,
            'is_over_budget' => $budgetAmount !== null && $total > $budgetAmount,
            'by_category' => $byCategory,
            'weekly' => $this->weeklyAccumulation($expenses, $from, $budgetAmount),
            'expense_count' => $expenses->count(),
        ];
    }

    /**
     * Gasto acumulado por semana del mes contra la línea (plana) del presupuesto,
     * para ver el avance y cuánto falta. Semana 1 = días 1–7, y así.
     *
     * @param  Collection<int, MaintenanceBudgetExpense>  $expenses
     * @return array{labels: list<string>, accumulated: list<float>, budget_line: list<?float>}
     */
    private function weeklyAccumulation(Collection $expenses, Carbon $monthStart, ?float $budget): array
    {
        $weeks = (int) ceil($monthStart->daysInMonth / 7);

        $perWeek = array_fill(1, $weeks, 0.0);

        foreach ($expenses as $expense) {
            $week = min($weeks, intdiv(((int) $expense->expense_date->format('j')) - 1, 7) + 1);
            $perWeek[$week] += (float) $expense->amount;
        }

        $labels = [];
        $accumulated = [];
        $budgetLine = [];
        $running = 0.0;

        for ($week = 1; $week <= $weeks; $week++) {
            $running += $perWeek[$week];
            $labels[] = "Sem {$week}";
            $accumulated[] = round($running, 2);
            $budgetLine[] = $budget;
        }

        return [
            'labels' => $labels,
            'accumulated' => $accumulated,
            'budget_line' => $budgetLine,
        ];
    }
}
