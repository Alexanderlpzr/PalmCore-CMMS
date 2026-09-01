<?php

namespace App\Domain\Reports\Contracts;

use App\Models\Plant;
use Carbon\CarbonInterface;

/**
 * Un informe de una planta durante una ventana de tiempo.
 *
 * Existe aparte de {@see PdfReport} porque aquel está tallado para un informe de **un
 * registro** —una OT, una ficha de equipo— y su firma no tiene dónde poner un período.
 * `LostHoursPdfService` ya se salió de ese contrato por esta misma razón y se quedó sin
 * ninguno; los cuatro informes de Indicadores comparten forma, así que aquí sí vale la
 * pena escribirla.
 *
 * La planta es un parámetro y no un id de tenant: estos informes se leen por planta, y
 * quien los pide ya la resolvió dentro de su tenant.
 */
interface PeriodReport
{
    /** Genera el PDF y devuelve los bytes. */
    public function generate(Plant $plant, CarbonInterface $from, CarbonInterface $to): string;

    /** El nombre con el que se descarga, con el período dentro. */
    public function filename(Plant $plant, CarbonInterface $from, CarbonInterface $to): string;
}
