<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Exceptions\PayrollRunException;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Liquida el período completo y lo cierra.
 *
 * Liquidar es idempotente a propósito: se puede correr cuantas veces haga falta mientras
 * la nómina esté en borrador, y cada corrida rehace los renglones desde cero. Es lo que
 * permite el ciclo real —se liquida, aparecen avisos, se corrigen novedades, se vuelve a
 * liquidar— sin que queden restos de la corrida anterior.
 *
 * Cerrar es lo contrario: a partir de ahí las cifras no se vuelven a tocar, porque ya se
 * pagaron y se aportaron. Reabrir existe y deja rastro, pero no borra: quien reabre
 * asume que va a volver a emitir los desprendibles.
 */
class PayrollRunService
{
    public function __construct(private readonly PayrollCalculator $calculator) {}

    /**
     * Rehace todos los renglones de la nómina.
     *
     * @throws PayrollRunException si la nómina ya está cerrada
     */
    public function calculate(PayrollRun $run): PayrollRun
    {
        if (! $run->isEditable()) {
            throw PayrollRunException::closed($run);
        }

        $employees = Employee::query()
            ->forTenant($run->tenant_id)
            ->active()
            ->orderBy('last_name')
            ->get();

        return DB::transaction(function () use ($run, $employees): PayrollRun {
            // Se borran y se vuelven a crear en vez de actualizarse: un trabajador que se
            // retiró a mitad de mes debe desaparecer de la nómina, y actualizar en sitio
            // lo dejaría ahí con las cifras de la corrida anterior.
            $run->entries()->delete();

            $totals = ['earned' => 0.0, 'deducted' => 0.0, 'net' => 0.0];

            foreach ($employees as $employee) {
                $entry = $this->calculator->calculate($employee, $run);
                $entry->save();

                $totals['earned'] += (float) $entry->total_earned;
                $totals['deducted'] += (float) $entry->total_deducted;
                $totals['net'] += (float) $entry->net_pay;
            }

            $run->update([
                'calculated_at' => now(),
                'total_earned' => round($totals['earned'], 2),
                'total_deducted' => round($totals['deducted'], 2),
                'total_net' => round($totals['net'], 2),
                'employee_count' => $employees->count(),
            ]);

            return $run->refresh();
        });
    }

    /**
     * Cierra la nómina.
     *
     * No se cierra con avisos pendientes. Un aviso es «los días no cuadran» o «hay horas
     * sin confirmar»: cerrar con eso encima es firmar una nómina que uno mismo marcó como
     * dudosa. Se puede forzar, pero hay que decirlo.
     *
     * @throws PayrollRunException
     */
    public function close(PayrollRun $run, User $by, bool $force = false): PayrollRun
    {
        if (! $run->isEditable()) {
            throw PayrollRunException::closed($run);
        }

        if ($run->calculated_at === null) {
            throw PayrollRunException::notCalculated($run);
        }

        $withWarnings = $run->entriesWithWarnings()->count();

        if ($withWarnings > 0 && ! $force) {
            throw PayrollRunException::hasWarnings($run, $withWarnings);
        }

        $run->update([
            'status' => PayrollRunStatus::Cerrada,
            'closed_at' => now(),
            'closed_by' => $by->id,
        ]);

        return $run->refresh();
    }

    /**
     * Devuelve la nómina a borrador.
     *
     * Los renglones se conservan hasta que alguien vuelva a liquidar: entre reabrir y
     * recalcular hay un rato en que el desprendible ya emitido y lo que muestra la
     * pantalla tienen que seguir coincidiendo.
     */
    public function reopen(PayrollRun $run): PayrollRun
    {
        $run->update([
            'status' => PayrollRunStatus::Borrador,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $run->refresh();
    }

    /** Los renglones que hay que mirar antes de cerrar. */
    public function warnings(PayrollRun $run): array
    {
        return $run->entries()
            ->whereNotNull('warnings')
            ->orderBy('employee_name')
            ->get()
            ->map(fn (PayrollEntry $entry): array => [
                'employee' => $entry->employee_name,
                'warnings' => $entry->warnings,
            ])
            ->all();
    }
}
