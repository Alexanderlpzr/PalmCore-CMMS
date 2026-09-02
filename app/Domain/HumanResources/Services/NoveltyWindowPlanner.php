<?php

namespace App\Domain\HumanResources\Services;

/**
 * Dónde colocar un bloque de novedad cuando la fuente dice cuántos días pero no cuáles.
 *
 * Es el problema que aparece al importar el libro de Excel: allí las vacaciones son «6» y
 * las incapacidades «3», sin fechas. La liquidación solo necesita la cantidad, así que
 * cualquier colocación daría el mismo neto —salvo por un detalle que costó dos
 * discrepancias en el paralelo: la hoja diaria **sí** dice qué días tuvieron horas extras,
 * y poner una incapacidad encima de un día con seis horas extras registradas contradice el
 * único dato de fecha que la fuente sí trae, y borra esas horas del cálculo.
 *
 * Por eso la ventana se elige por el criterio de pisar lo menos posible. No es adivinar la
 * verdad: es no contradecir lo que sí se sabe.
 *
 * Solo se usa al importar. Las novedades que se capturan en el sistema llevan sus fechas
 * reales y nunca pasan por aquí.
 */
class NoveltyWindowPlanner
{
    /**
     * El primer día (base 0, relativo al inicio del período) donde cabe el bloque.
     *
     * @param  int  $days  duración del bloque
     * @param  int  $periodDays  días que tiene el período
     * @param  array<int, true>  $taken  posiciones ya ocupadas por otra novedad
     * @param  array<int, float>  $extraHoursByDay  horas de recargo y extra, por día del mes en base 1
     * @return int|null null cuando no queda hueco para un bloque de ese tamaño
     */
    public function place(int $days, int $periodDays, array $taken, array $extraHoursByDay): ?int
    {
        if ($days <= 0 || $days > $periodDays) {
            return null;
        }

        $best = null;
        $bestScore = null;

        for ($offset = 0; $offset + $days <= $periodDays; $offset++) {
            $score = $this->scoreWindow($offset, $days, $taken, $extraHoursByDay);

            if ($score === null) {
                continue;
            }

            // `<=` y no `<`: en un empate gana la ventana más tardía. En el libro las
            // novedades suelen ir al final del mes, y donde todo empata da igual, así que
            // se elige lo que más se parece a la fuente.
            if ($bestScore === null || $score <= $bestScore) {
                $bestScore = $score;
                $best = $offset;
            }
        }

        return $best;
    }

    /**
     * Cuántas horas registradas pisaría esta ventana. Null si choca con otra novedad.
     *
     * @param  array<int, true>  $taken
     * @param  array<int, float>  $extraHoursByDay
     */
    private function scoreWindow(int $offset, int $days, array $taken, array $extraHoursByDay): ?float
    {
        $score = 0.0;

        for ($i = 0; $i < $days; $i++) {
            if (isset($taken[$offset + $i])) {
                return null;
            }

            // El mapa de horas viene indexado por día del mes, que es base 1.
            $score += $extraHoursByDay[$offset + $i + 1] ?? 0.0;
        }

        return $score;
    }
}
