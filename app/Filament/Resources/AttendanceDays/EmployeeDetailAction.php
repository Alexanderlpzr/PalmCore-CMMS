<?php

namespace App\Filament\Resources\AttendanceDays;

use App\Domain\HumanResources\Services\EmployeePeriodSummary;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

/**
 * La ventana lateral con todo lo de una persona en el corte que se está mirando: sus días
 * con entrada, salida y horas, las comidas, lo que costó, sus novedades y la comparación
 * con su corte anterior. La misma desde «Horas confirmadas», «Horas extras» y
 * «Alimentación», para no tener que saltar entre pantallas.
 *
 * Los pesos solo salen para quien ve salarios.
 */
final class EmployeeDetailAction
{
    /**
     * @param  Closure(mixed): ?Employee  $employeeOf  el trabajador de la fila
     * @param  Closure(): array{0: CarbonImmutable, 1: CarbonImmutable}  $windowOf  el corte elegido en la pantalla
     */
    public static function make(Closure $employeeOf, Closure $windowOf): Action
    {
        return Action::make('detalleTrabajador')
            ->label('Detalle')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('primary')
            ->slideOver()
            ->modalWidth('4xl')
            ->modalHeading(fn (mixed $record): string => $employeeOf($record)?->fullName() ?? 'Trabajador')
            ->modalDescription(function (mixed $record) use ($employeeOf, $windowOf): string {
                $employee = $employeeOf($record);
                [$from, $to] = $windowOf();

                return collect([
                    $employee?->employee_code,
                    $employee?->position,
                    'Corte del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y'),
                ])->filter()->join(' · ');
            })
            ->modalContent(function (mixed $record) use ($employeeOf, $windowOf): ?View {
                $employee = $employeeOf($record);

                if (! $employee) {
                    return null;
                }

                [$from, $to] = $windowOf();
                $withMoney = auth()->user()?->can('viewAnySalary', Employee::class) ?? false;

                return view('filament.pages.partials.detalle-del-trabajador', [
                    'resumen' => app(EmployeePeriodSummary::class)->for($employee, $from, $to, $withMoney),
                    'conPesos' => $withMoney,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }
}
