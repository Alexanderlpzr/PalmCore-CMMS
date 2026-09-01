<?php

namespace App\Domain\Reports\Services;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use Carbon\CarbonInterface;

/**
 * El informe de productividad y eficiencia que se lleva a la reunión.
 *
 * Todo sale de {@see PlantKpiService::calculate()}, que ya calcula sobre cualquier
 * ventana: no hay una cuenta nueva aquí, y a propósito. Si el informe recalculara los
 * indicadores por su cuenta acabaría discrepando de la pantalla, y entonces habría dos
 * verdades sobre la eficiencia de agosto.
 *
 * El desglose de horas se imprime entero —pagadas, aseo, paro por mantenimiento, otras
 * pérdidas, prensado— porque el indicador solo se puede discutir con el denominador a la
 * vista: una eficiencia del 88% no dice lo mismo si el aseo se llevó veinte horas que si
 * se llevó doscientas.
 */
class ProductividadPdfService extends PeriodPdfReport
{
    public function __construct(
        ReportBrandingService $branding,
        private readonly PlantKpiService $kpis,
    ) {
        parent::__construct($branding);
    }

    protected function documentPrefix(): string
    {
        return 'PRD';
    }

    protected function view(): string
    {
        return 'reports.indicadores-productividad';
    }

    protected function data(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $k = $this->kpis->calculate($plant, $from, $to);

        // El denominador de eficiencia y productividad, dicho en voz alta. Sin él, los dos
        // porcentajes de arriba son afirmaciones que nadie puede contrastar en la reunión.
        $baseHours = $k['programmed_hours'] - $k['cleaning_hours'];

        return [
            'kpis' => $k,
            'baseHours' => round($baseHours, 2),
            'hasData' => $k['programmed_hours'] > 0 || $k['processed_tons'] > 0,
        ];
    }
}
