<?php

namespace App\Domain\Reports\Services;

use App\Domain\Analytics\Services\BudgetTrackingService;
use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Models\Plant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El informe de presupuesto contra gasto real.
 *
 * {@see BudgetTrackingService::monthlyReport()} calcula un mes, y no se cambia: para un
 * rango este servicio recorre los meses y los suma. Sale una consulta por mes, que para
 * doce meses es barato y evita tocar un servicio del que cuelga la pantalla.
 *
 * El presupuesto del rango se suma **solo sobre los meses que tienen uno asignado**. Si
 * ningún mes lo tiene, queda en `null` y el informe dice que no hay presupuesto cargado,
 * en vez de imprimir un cero que se leería como «no se asignó nada» — que es una
 * afirmación distinta de «no lo sabemos».
 */
class PresupuestoPdfService extends PeriodPdfReport
{
    public function __construct(
        ReportBrandingService $branding,
        private readonly BudgetTrackingService $budget,
    ) {
        parent::__construct($branding);
    }

    protected function documentPrefix(): string
    {
        return 'PRE';
    }

    protected function view(): string
    {
        return 'reports.indicadores-presupuesto';
    }

    protected function data(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $meses = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $fin = Carbon::parse($to)->startOfMonth();

        while ($cursor->lte($fin)) {
            $meses[] = [
                'label' => ucfirst($cursor->translatedFormat('F Y')),
                ...$this->budget->monthlyReport($plant, $cursor->year, $cursor->month),
            ];

            $cursor->addMonth();
        }

        $conPresupuesto = array_filter($meses, fn (array $m): bool => $m['budget'] !== null);
        $presupuesto = $conPresupuesto === [] ? null : round(array_sum(array_column($conPresupuesto, 'budget')), 2);
        $gastado = round(array_sum(array_column($meses, 'total')), 2);

        return [
            'meses' => count($meses) > 1 ? $meses : [],
            'presupuesto' => $presupuesto,
            'gastado' => $gastado,
            'restante' => $presupuesto === null ? null : round($presupuesto - $gastado, 2),
            'porcentaje' => ($presupuesto !== null && $presupuesto > 0)
                ? round($gastado / $presupuesto * 100, 1)
                : null,
            'excedido' => $presupuesto !== null && $gastado > $presupuesto,
            'porCategoria' => $this->porCategoria($meses),
            'gastos' => array_sum(array_column($meses, 'expense_count')),
        ];
    }

    /**
     * El gasto del rango por categoría, de mayor a menor.
     *
     * @param  list<array<string, mixed>>  $meses
     * @return array<string, float>
     */
    private function porCategoria(array $meses): array
    {
        $totales = [];

        foreach ($meses as $mes) {
            foreach ($mes['by_category'] as $categoria => $monto) {
                $totales[$categoria] = round(($totales[$categoria] ?? 0) + $monto, 2);
            }
        }

        arsort($totales);

        return $totales;
    }

    /** La etiqueta legible de una categoría de gasto, para la vista. */
    public static function categoryLabel(string $value): string
    {
        return ExpenseCategory::tryFrom($value)?->label() ?? $value;
    }
}
