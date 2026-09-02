<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El paso que convierte una propuesta del reloj en horas pagables.
 *
 * Es deliberadamente aburrido: marca el estado, deja el autor y la hora. Todo el valor
 * está en que **exista**, porque es el punto donde una persona se hace responsable de lo
 * que se va a pagar. El libro de Excel tiene esa revisión metida dentro de la captura, y
 * por eso no se puede saber quién decidió que esas cuatro horas eran nocturnas.
 */
class AttendanceDayConfirmer
{
    /**
     * Confirma un día. Confirmar lo ya confirmado no hace nada y no es un error: dos
     * supervisores pueden apretar el botón sobre la misma lista.
     */
    public function confirm(AttendanceDay $day, User $by, ?CarbonInterface $at = null): AttendanceDay
    {
        if ($day->status === AttendanceDayStatus::Confirmada) {
            return $day;
        }

        $day->update([
            'status' => AttendanceDayStatus::Confirmada,
            'confirmed_by' => $by->id,
            'confirmed_at' => $at ?? now(),
        ]);

        return $day->refresh();
    }

    /**
     * Confirma varios de una vez.
     *
     * @param  Collection<int, AttendanceDay>  $days
     */
    public function confirmMany(Collection $days, User $by): int
    {
        return DB::transaction(function () use ($days, $by): int {
            $confirmed = 0;

            foreach ($days as $day) {
                if ($day->status === AttendanceDayStatus::Propuesta) {
                    $this->confirm($day, $by);
                    $confirmed++;
                }
            }

            return $confirmed;
        });
    }

    /**
     * Devuelve un día confirmado al estado de propuesta.
     *
     * Existe porque un error confirmado sin darse cuenta es más probable que un error
     * detectado a tiempo, y la alternativa —editar los números a mano sobre la fila
     * confirmada— dejaría horas que no corresponden a ninguna marca del reloj.
     */
    public function reopen(AttendanceDay $day): AttendanceDay
    {
        $day->update([
            'status' => AttendanceDayStatus::Propuesta,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);

        return $day->refresh();
    }
}
