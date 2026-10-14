<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Support\DayTone;
use App\Filament\Resources\AttendanceDays\AttendanceDayActions;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Hours;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Revisar las horas de la semana: una cuadrícula con un trabajador por fila y un día por
 * columna, cada celda con sus horas y el color de su estado.
 *
 * Es la forma rápida de «Horas por confirmar»: se ve la semana entera de un vistazo, un
 * botón confirma de una vez todo lo que no tiene avisos, y al tocar un día se abre a la
 * derecha con sus marcas, sus horas desglosadas y los botones para confirmarlo,
 * ajustarlo o anularlo.
 */
class RevisarHoras extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Día a día';

    protected static ?string $navigationLabel = 'Revisar semana';

    protected static ?string $title = 'Revisar horas de la semana';

    /** Justo antes de «Horas por confirmar»: es su vista rápida. */
    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'revisar-horas';

    protected string $view = 'filament.pages.revisar-horas';

    /** El lunes de la semana que se mira. */
    public string $semana = '';

    /** Solo los trabajadores con algún día por confirmar. */
    public bool $soloPendientes = true;

    public string $buscar = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AttendanceDay::class) ?? false;
    }

    public function mount(): void
    {
        $this->semana = $this->today()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
    }

    public function semanaAnterior(): void
    {
        $this->semana = $this->monday()->subWeek()->toDateString();
    }

    public function semanaSiguiente(): void
    {
        $this->semana = $this->monday()->addWeek()->toDateString();
    }

    public function estaSemana(): void
    {
        $this->semana = $this->today()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmarBuenos')
                ->label('Confirmar lo que está bien')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->authorize(fn (): bool => auth()->user()?->can('correct', AttendanceScan::class) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Confirmar los días sin avisos de la semana')
                ->modalDescription(fn (): string => ($n = $this->cleanProposedDays()->count()) > 0
                    ? "Se confirman {$n} ".($n === 1 ? 'día' : 'días').' por confirmar sin ningún aviso, hasta hoy. Los que tienen avisos quedan para revisarlos uno por uno.'
                    : 'No hay días sin avisos por confirmar en esta semana.')
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $n = app(AttendanceDayConfirmer::class)->confirmMany($this->cleanProposedDays(), $user);

                    Notification::make()->title($n > 0 ? "Se confirmaron {$n} días" : 'No había días para confirmar')->success()->send();
                }),
        ];
    }

    /**
     * El día abierto a la derecha: marcas, horas, avisos y lo que se puede hacer con él.
     */
    public function diaAction(): Action
    {
        return Action::make('dia')
            ->slideOver()
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => ($day = $this->dayOf($arguments))
                ? ($day->employee?->fullName() ?? 'Día')
                : 'Día')
            ->modalDescription(fn (array $arguments): ?string => ($day = $this->dayOf($arguments))
                ? ucfirst($day->work_date->locale('es')->translatedFormat('l j \d\e F \d\e Y'))
                : null)
            ->modalContent(fn (array $arguments) => view('filament.pages.partials.revisar-dia', $this->dayDetail($this->dayOf($arguments))))
            ->modalSubmitActionLabel('Confirmar el día')
            ->modalSubmitAction(fn (Action $action, array $arguments): Action|false => $this->canConfirm($this->dayOf($arguments))
                ? $action->color('success')->icon(Heroicon::OutlinedCheckBadge)
                : false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(function (array $arguments): array {
                $day = $this->dayOf($arguments);

                if (! $day) {
                    return [];
                }

                return [
                    AttendanceDayActions::adjustHours()->record($day)->cancelParentActions(),
                    AttendanceDayActions::voidDay()->record($day)->cancelParentActions(),
                ];
            })
            ->action(function (array $arguments): void {
                $day = $this->dayOf($arguments);

                if (! $this->canConfirm($day)) {
                    return;
                }

                /** @var User $user */
                $user = auth()->user();
                app(AttendanceDayConfirmer::class)->confirm($day, $user);

                Notification::make()->title('Día confirmado')->success()->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monday = $this->monday();
        $dates = collect(range(0, 6))->map(fn (int $i): CarbonImmutable => $monday->addDays($i));
        $today = $this->today()->toDateString();

        $days = $this->weekDays()->groupBy('employee_id');

        $employees = Employee::query()
            ->whereIn('id', $days->keys())
            ->when(trim($this->buscar) !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('first_name', 'ilike', '%'.trim($this->buscar).'%')
                ->orWhere('last_name', 'ilike', '%'.trim($this->buscar).'%')
                ->orWhere('document_number', 'like', '%'.trim($this->buscar).'%')))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $rows = $employees
            ->map(function (Employee $employee) use ($days, $dates): array {
                $byDate = $days[$employee->id]->keyBy(fn (AttendanceDay $d): string => $d->work_date->toDateString());

                return [
                    'employee' => $employee,
                    'cells' => $dates->map(fn (CarbonImmutable $date): ?array => ($day = $byDate[$date->toDateString()] ?? null) ? [
                        'id' => $day->id,
                        'hours' => Hours::format($day->worked_hours) ?: '0 h',
                        'extra' => Hours::format($day->hours()->overtimeHours()),
                        'tone' => self::toneOf($day),
                    ] : null)->all(),
                    'pending' => $byDate->contains(fn (AttendanceDay $d): bool => $d->status === AttendanceDayStatus::Propuesta),
                    'total' => Hours::format($byDate->sum(fn (AttendanceDay $d): float => (float) $d->worked_hours)),
                ];
            })
            ->filter(fn (array $row): bool => ! $this->soloPendientes || $row['pending'])
            ->values();

        $all = $this->weekDays();

        return [
            'dates' => $dates,
            'today' => $today,
            'rows' => $rows,
            'counts' => [
                'pending' => $all->where('status', AttendanceDayStatus::Propuesta)->count(),
                'warnings' => $all->filter(fn (AttendanceDay $d): bool => $d->status === AttendanceDayStatus::Propuesta && $d->hasAnomalies())->count(),
                'confirmed' => $all->where('status', AttendanceDayStatus::Confirmada)->count(),
            ],
            'weekLabel' => 'Del '.$monday->format('d/m').' al '.$monday->addDays(6)->format('d/m/Y'),
            'isCurrentWeek' => $monday->isSameDay($this->today()->startOfWeek(CarbonInterface::MONDAY)),
        ];
    }

    /** Verde confirmado, azul ajustado a mano, rojo con avisos, amarillo por confirmar. */
    public static function toneOf(AttendanceDay $day): string
    {
        return DayTone::of($day);
    }

    /**
     * @return array<string, mixed>
     */
    private function dayDetail(?AttendanceDay $day): array
    {
        if (! $day) {
            return ['day' => null];
        }

        $timezone = $this->tenant()->plantTimezone();
        $labels = [
            'ordinary_hours' => 'Ordinarias',
            'night_surcharge_hours' => 'Recargo nocturno',
            'sunday_surcharge_hours' => 'Recargo dominical',
            'night_sunday_surcharge_hours' => 'Recargo nocturno dominical',
            'overtime_day_hours' => 'Extra diurna',
            'overtime_night_hours' => 'Extra nocturna',
            'overtime_sunday_day_hours' => 'Extra dominical diurna',
            'overtime_sunday_night_hours' => 'Extra dominical nocturna',
        ];

        return [
            'day' => $day,
            'tone' => self::toneOf($day),
            'marks' => app(AttendanceCorrectionService::class)->marksOfDay($day)->map(fn (AttendanceScan $mark): array => [
                'time' => $mark->scanned_at->copy()->setTimezone($timezone)->format('h:i a'),
                'date' => $mark->scanned_at->copy()->setTimezone($timezone)->format('d/m'),
                'entry' => $mark->direction === AttendanceDirection::Entrada,
                'manual' => $mark->source === 'manual',
            ])->all(),
            'adjustedTimes' => $day->adjusted_entry_at && $day->adjusted_exit_at
                ? $day->adjusted_entry_at->copy()->setTimezone($timezone)->format('h:i a').' a '.$day->adjusted_exit_at->copy()->setTimezone($timezone)->format('h:i a')
                : null,
            'hours' => collect($labels)
                ->map(fn (string $label, string $column): array => ['label' => $label, 'value' => Hours::format($day->{$column})])
                ->filter(fn (array $row): bool => $row['value'] !== '')
                ->values()
                ->all(),
            'worked' => Hours::format($day->worked_hours) ?: '0 h',
        ];
    }

    /** Los días de la semana que se mira, con su trabajador. @return Collection<int, AttendanceDay> */
    private function weekDays(): Collection
    {
        $monday = $this->monday();

        return AttendanceDay::query()
            ->between($monday->toDateString(), $monday->addDays(6)->toDateString())
            ->with('employee')
            ->get();
    }

    /** Los por confirmar de la semana, sin avisos y hasta hoy. @return Collection<int, AttendanceDay> */
    private function cleanProposedDays(): Collection
    {
        $today = $this->today()->toDateString();

        return $this->weekDays()
            ->filter(fn (AttendanceDay $day): bool => $day->status === AttendanceDayStatus::Propuesta
                && ! $day->hasAnomalies()
                && $day->work_date->toDateString() <= $today)
            ->values();
    }

    private function canConfirm(?AttendanceDay $day): bool
    {
        return $day !== null && (auth()->user()?->can('confirm', $day) ?? false);
    }

    /** @param  array<string, mixed>  $arguments */
    private function dayOf(array $arguments): ?AttendanceDay
    {
        $id = $arguments['day'] ?? null;

        return is_string($id) ? AttendanceDay::query()->with('employee')->find($id) : null;
    }

    private function monday(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->semana ?: $this->today()->toDateString())->startOfWeek(CarbonInterface::MONDAY);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tenant()->plantTimezone())->startOfDay();
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
