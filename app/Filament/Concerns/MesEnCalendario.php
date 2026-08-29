<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Carbon;

/**
 * El mes en una rejilla de siete columnas, para saltar a cualquier día de un clic.
 *
 * Lo comparten las dos pantallas de captura —la ronda de contadores y la jornada de
 * producción— porque la pregunta que responden es la misma: qué días faltan por anotar.
 * Hasta ahora eso solo se sabía bajando a la tabla del mes y leyendo treinta filas en
 * busca de guiones.
 *
 * Va en calendario y no en una tira de números por una razón concreta: los domingos no
 * muelen. El día de la semana cambia cómo se lee un hueco —un domingo vacío es normal, un
 * martes vacío es un olvido— y una tira de números no lo dice.
 *
 * La página que lo usa aporta solo qué días tienen dato; el resto —el relleno hasta la
 * columna del día 1, qué días aún no han ocurrido, cuántos faltan— es idéntico para las
 * dos y vive aquí.
 */
trait MesEnCalendario
{
    /**
     * @param  array<string, bool>  $diasConDato  indexado por fecha «Y-m-d»
     * @return array{monthLabel: string, legend: string, offset: int, days: array<int, array{date: string, day: int, has_data: bool, is_future: bool, is_selected: bool}>}
     */
    protected function calendarioDelMes(Carbon $seleccionado, array $diasConDato): array
    {
        $inicio = $seleccionado->copy()->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $hoy = Carbon::today();
        $elegido = $seleccionado->toDateString();

        $days = [];
        $faltan = 0;

        for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
            $clave = $fecha->toDateString();

            // Ni un contador ni una jornada se anotan por adelantado: es la misma regla
            // que ya rige el botón «Día siguiente», y sin ella el mes en curso mostraría
            // huecos por días que todavía no han ocurrido.
            $futuro = $fecha->gt($hoy);
            $conDato = $diasConDato[$clave] ?? false;

            if (! $futuro && ! $conDato) {
                $faltan++;
            }

            $days[] = [
                'date' => $clave,
                'day' => $fecha->day,
                'has_data' => $conDato,
                'is_future' => $futuro,
                'is_selected' => $clave === $elegido,
            ];
        }

        return [
            'monthLabel' => ucfirst($inicio->translatedFormat('F \d\e Y')),
            'legend' => $faltan === 0
                ? 'Todos los días del mes están anotados.'
                : ($faltan === 1 ? 'Falta 1 día por anotar.' : "Faltan {$faltan} días por anotar."),
            // Cuántas casillas en blanco hasta que el día 1 cae bajo su día de la semana.
            // La rejilla empieza en lunes, así que el lunes es el hueco cero.
            'offset' => $inicio->dayOfWeekIso - 1,
            'days' => $days,
        ];
    }
}
