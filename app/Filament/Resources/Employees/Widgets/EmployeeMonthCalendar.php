<?php

namespace App\Filament\Resources\Employees\Widgets;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Filament\Pages\RevisarHoras;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Support\Hours;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * El mes del trabajador en un calendario: cada día con sus horas y el color de su estado,
 * las novedades (vacaciones, incapacidad, permiso) y los domingos y festivos marcados.
 * Reemplaza ir de pestaña en pestaña para saber qué hizo en el mes.
 */
class EmployeeMonthCalendar extends Widget
{
    protected string $view = 'filament.resources.employees.widgets.employee-month-calendar';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    /** El primer día del mes que se mira. */
    public string $mes = '';

    public function mount(): void
    {
        $this->mes = $this->today()->startOfMonth()->toDateString();
    }

    public function mesAnterior(): void
    {
        $this->mes = $this->month()->subMonthNoOverflow()->toDateString();
    }

    public function mesSiguiente(): void
    {
        $this->mes = $this->month()->addMonthNoOverflow()->toDateString();
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Employee $employee */
        $employee = $this->record;
        $month = $this->month();
        $first = $month->startOfWeek(CarbonInterface::MONDAY);
        $last = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $days = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->between($first->toDateString(), $last->toDateString())
            ->get()
            ->keyBy(fn (AttendanceDay $day): string => $day->work_date->toDateString());

        $novelties = EmployeeNovelty::query()
            ->where('employee_id', $employee->id)
            ->overlapping($first, $last)
            ->get();

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$first->toDateString(), $last->toDateString()])
            ->pluck('name', 'holiday_date')
            ->mapWithKeys(fn (string $name, $date): array => [CarbonImmutable::parse($date)->toDateString() => $name]);

        $cells = [];

        for ($date = $first; $date->lte($last); $date = $date->addDay()) {
            $key = $date->toDateString();
            $day = $days[$key] ?? null;
            $novelty = $novelties->first(fn (EmployeeNovelty $n): bool => $n->starts_on->toDateString() <= $key && $n->ends_on->toDateString() >= $key);

            $cells[] = [
                'date' => $date,
                'inMonth' => $date->month === $month->month,
                'isToday' => $date->isSameDay($this->today()),
                'surcharged' => $date->isSunday() || isset($holidays[$key]),
                'holiday' => $holidays[$key] ?? null,
                'hours' => $day ? (Hours::format($day->worked_hours) ?: '0 h') : null,
                'extra' => $day ? Hours::format($day->hours()->overtimeHours()) : '',
                'tone' => $day ? RevisarHoras::toneOf($day) : null,
                'novelty' => $novelty?->type->label(),
            ];
        }

        $inMonth = $days->filter(fn (AttendanceDay $d): bool => $d->work_date->month === $month->month && $d->work_date->year === $month->year);

        return [
            'cells' => $cells,
            'label' => ucfirst($month->locale('es')->translatedFormat('F \d\e Y')),
            'summary' => [
                'Días trabajados' => (string) $inMonth->count(),
                'Horas' => Hours::format($inMonth->sum(fn (AttendanceDay $d): float => (float) $d->worked_hours)) ?: '0 h',
                'Horas extras' => Hours::format($inMonth->sum(fn (AttendanceDay $d): float => $d->hours()->overtimeHours())) ?: '—',
                'Por confirmar' => (string) $inMonth->where('status', AttendanceDayStatus::Propuesta)->count(),
                'Días de novedad' => (string) $novelties->sum(fn (EmployeeNovelty $n): int => $n->daysWithin($month, $month->endOfMonth())),
            ],
        ];
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->mes ?: $this->today()->toDateString())->startOfMonth();
    }

    private function today(): CarbonImmutable
    {
        /** @var Tenant|null $tenant */
        $tenant = Filament::getTenant();

        return CarbonImmutable::now($tenant?->plantTimezone() ?? 'America/Bogota')->startOfDay();
    }
}
