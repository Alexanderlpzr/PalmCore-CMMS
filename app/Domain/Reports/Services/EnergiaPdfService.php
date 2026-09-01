<?php

namespace App\Domain\Reports\Services;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El informe de consumo de energía.
 *
 * Las cifras salen de {@see PlantKpiService::energySummary()}, que ya resuelve la parte
 * delicada: los ratios de un rango se calculan sobre los totales y **no** se promedian.
 * El KWh/RFF de marzo a junio es el total de kWh partido por el total de fruta; promediar
 * cuatro ratios le daría el mismo peso a un mes flojo que a uno de plena cosecha.
 *
 * Cuando el período abarca varios meses se imprime además el detalle mes a mes, porque el
 * total solo dice a cuánto salió el promedio y la reunión suele preguntar cuál fue el mes
 * malo.
 *
 * Un mes sin lectura muestra «sin dato», no cero: cero kWh de turbina afirma que la planta
 * funcionó a diésel, y no saberlo dice que nadie pasó a leer el contador.
 */
class EnergiaPdfService extends PeriodPdfReport
{
    public function __construct(
        ReportBrandingService $branding,
        private readonly PlantKpiService $kpis,
    ) {
        parent::__construct($branding);
    }

    protected function documentPrefix(): string
    {
        return 'ENE';
    }

    protected function view(): string
    {
        return 'reports.indicadores-energia';
    }

    protected function data(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $resumen = $this->kpis->energySummary($plant, $from, $to);

        return [
            'resumen' => $resumen,
            'meses' => $this->porMes($plant, $from, $to),
            'hasData' => $resumen['kwh_total'] !== null,
        ];
    }

    /**
     * El período mes a mes, o vacío si solo abarca uno.
     *
     * Se pide el resumen de cada mes por separado en vez de repartir el total: cada mes
     * tiene su propio denominador de fruta, y un KWh/RFF mensual no se puede deducir del
     * acumulado.
     *
     * @return list<array<string, mixed>>
     */
    private function porMes(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($from->format('Y-m') === $to->format('Y-m')) {
            return [];
        }

        $meses = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $fin = Carbon::parse($to)->startOfMonth();

        while ($cursor->lte($fin)) {
            $meses[] = [
                'label' => ucfirst($cursor->translatedFormat('F Y')),
                ...$this->kpis->energySummary($plant, $cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth()),
            ];

            $cursor->addMonth();
        }

        return $meses;
    }
}
