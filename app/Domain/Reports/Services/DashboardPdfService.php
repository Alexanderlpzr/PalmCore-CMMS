<?php

namespace App\Domain\Reports\Services;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Models\Plant;
use Carbon\CarbonInterface;

/**
 * El informe del Dashboard: paros, confiabilidad y costos.
 *
 * Aquí está la trampa de este informe, y por eso va escrita. **No todas sus cifras
 * responden al período**: cuatro de ellas salen de métodos de {@see AnalyticsService} que
 * ni siquiera aceptan fechas —el Pareto y el planificado-contra-correctivo miran doce
 * meses fijos, el cumplimiento del plan es una foto de hoy, y el costo por equipo es
 * histórico completo.
 *
 * En pantalla eso se tolera porque cada widget vive en su recuadro. En un PDF titulado
 * «Agosto de 2026» sería mentira: quien lo lee asume que todo lo de dentro es de agosto, y
 * acabaría discutiendo un Pareto de doce meses creyéndolo del mes.
 *
 * La solución no es maquillarlo sino decirlo: cada bloque viaja con la ventana que de
 * verdad cubre, y la vista la imprime junto al título. Darle fechas a esos cuatro métodos
 * es un cambio de analítica que afecta también a los widgets, y merece decidirse aparte.
 */
class DashboardPdfService extends PeriodPdfReport
{
    public function __construct(
        ReportBrandingService $branding,
        private readonly AnalyticsService $analytics,
        private readonly DowntimeService $downtime,
    ) {
        parent::__construct($branding);
    }

    protected function documentPrefix(): string
    {
        return 'IND';
    }

    protected function view(): string
    {
        return 'reports.indicadores-dashboard';
    }

    protected function data(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $tenantId = $plant->tenant_id;

        $porEquipo = $this->downtime->lostHoursByEquipment($plant->id, $from, $to);
        $porCategoria = $this->downtime->lostHoursByCategory($plant->id, $from, $to);

        return [
            // Estos cuatro sí siguen el período elegido.
            'porTipo' => $this->analytics->downtimeByReportedType($tenantId, $from, $to),
            'porCausa' => $this->analytics->downtimeByReason($tenantId, $from, $to),
            'porSeccion' => $this->analytics->downtimeBySection($tenantId, $from, $to),
            'porEquipo' => $porEquipo['equipment'],
            'horasPlanta' => $porEquipo['plant_wide_hours'],
            'horasTotales' => $porEquipo['total_hours'],
            'porCategoria' => collect($porCategoria)
                ->map(fn (float $horas, string $categoria): array => [
                    'label' => StoppageCategory::from($categoria)->label(),
                    'is_maintenance' => StoppageCategory::from($categoria)->isMaintenanceResponsibility(),
                    'hours' => $horas,
                ])
                ->sortByDesc('hours')
                ->values()
                ->all(),

            // Y estos cuatro no. Cada uno viaja con su ventana para que la vista la diga.
            'pareto' => $this->analytics->paretoFailures($tenantId),
            'paretoVentana' => 'últimos 12 meses',
            'cumplimiento' => $this->analytics->preventiveCompliance($tenantId),
            'cumplimientoVentana' => 'estado a hoy',
            'planificado' => $this->analytics->plannedVsCorrective($tenantId),
            'planificadoVentana' => 'últimos 12 meses',
            'costoPorEquipo' => $this->analytics->costByEquipment($tenantId),
            'costoVentana' => 'histórico completo',

            'hasData' => $porEquipo['total_hours'] > 0,
        ];
    }
}
