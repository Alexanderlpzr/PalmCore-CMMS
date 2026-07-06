<?php

namespace App\Filament\Pages;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\MaintenanceSchedule;
use App\Models\Plant;
use App\Models\User;
use App\Models\WorkOrder;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Visual maintenance planner: a month calendar that places work orders on their
 * planned start date (coloured by status) and upcoming preventive maintenance on
 * its due date, alongside a per-technician workload panel and an "unscheduled"
 * tray. Navigation is server-side (Livewire) so no calendar JS library is needed.
 */
class MaintenanceCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $title = 'Calendario de Mantenimiento';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.maintenance-calendar';

    /** Visible month as 'Y-m'. */
    public string $month = '';

    public ?string $plantId = null;

    public ?string $statusFilter = null;

    public ?string $technicianId = null;

    public bool $showPreventive = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_super_admin || $user?->can('work-orders.view'));
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    // ── Navigation ──────────────────────────────────────────────────────────────

    public function previousMonth(): void
    {
        $this->month = $this->currentMonth()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->currentMonth()->addMonth()->format('Y-m');
    }

    public function goToToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    private function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m', $this->month ?: now()->format('Y-m'))->startOfMonth();
    }

    // ── View data ───────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monthStart = $this->currentMonth();
        $monthEnd = $monthStart->endOfMonth();

        // Grid spans whole weeks (Mon–Sun) covering the month.
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);

        $workOrders = $this->workOrdersInRange($gridStart, $gridEnd);
        $preventives = $this->showPreventive ? $this->preventivesInRange($gridStart, $gridEnd) : collect();

        $wosByDay = $workOrders->groupBy(fn (WorkOrder $wo) => $wo->planned_start_at->format('Y-m-d'));
        $pmByDay = $preventives->groupBy(fn (MaintenanceSchedule $s) => $s->next_due_at->format('Y-m-d'));

        $today = CarbonImmutable::today()->format('Y-m-d');
        $weeks = [];
        $cursor = $gridStart;

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->format('Y-m-d');

                $week[] = [
                    'day' => $cursor->day,
                    'inMonth' => $cursor->month === $monthStart->month,
                    'isToday' => $key === $today,
                    'workOrders' => ($wosByDay->get($key) ?? collect())->map(fn (WorkOrder $wo) => $this->workOrderCard($wo))->all(),
                    'preventives' => ($pmByDay->get($key) ?? collect())->map(fn (MaintenanceSchedule $s) => $this->preventiveCard($s, $today))->all(),
                ];

                $cursor = $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'monthLabel' => ucfirst($monthStart->translatedFormat('F Y')),
            'weeks' => $weeks,
            'weekdays' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            'workload' => $this->technicianWorkload($workOrders),
            'unscheduled' => $this->unscheduledWorkOrders(),
            'plantOptions' => Plant::orderBy('name')->pluck('name', 'id')->all(),
            'statusOptions' => WorkOrderStatus::options(),
            'technicianOptions' => User::query()->operationalStaff()->orderBy('name')->pluck('name', 'id')->all(),
            'totalScheduled' => $workOrders->count(),
        ];
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, WorkOrder>
     */
    private function workOrdersInRange(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return WorkOrder::query()
            ->with(['equipment:id,code,name', 'technicians.user:id,name'])
            ->whereNotNull('planned_start_at')
            ->whereBetween('planned_start_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($this->plantId, fn ($q) => $q->where('plant_id', $this->plantId))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->technicianId, fn ($q) => $q->whereHas(
                'technicians',
                fn ($t) => $t->where('user_id', $this->technicianId)
            ))
            ->orderBy('planned_start_at')
            ->get();
    }

    /**
     * @return Collection<int, MaintenanceSchedule>
     */
    private function preventivesInRange(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return MaintenanceSchedule::query()
            ->with(['plan.equipment:id,code,name'])
            ->whereNotNull('next_due_at')
            ->whereBetween('next_due_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereHas('plan', fn ($q) => $q->where('is_active', true)
                ->when($this->plantId, fn ($p) => $p->whereHas('equipment', fn ($e) => $e->where('plant_id', $this->plantId)))
            )
            ->orderBy('next_due_at')
            ->get();
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    private function unscheduledWorkOrders(): Collection
    {
        return WorkOrder::query()
            ->with('equipment:id,code,name')
            ->open()
            ->whereNull('planned_start_at')
            ->when($this->plantId, fn ($q) => $q->where('plant_id', $this->plantId))
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();
    }

    /**
     * Per-technician load across the work orders currently shown on the calendar.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return array<int, array{name: string, count: int, hours: float}>
     */
    private function technicianWorkload(Collection $workOrders): array
    {
        $load = [];

        foreach ($workOrders as $wo) {
            if ($wo->status->isTerminal()) {
                continue; // closed/cancelled no longer occupy capacity
            }

            $hours = (float) ($wo->plannedHours() ?? 0);

            foreach ($wo->technicians as $technician) {
                $name = $technician->user?->name ?? 'Sin nombre';
                $load[$name] ??= ['name' => $name, 'count' => 0, 'hours' => 0.0];
                $load[$name]['count']++;
                $load[$name]['hours'] += $hours;
            }
        }

        usort($load, fn ($a, $b) => $b['hours'] <=> $a['hours']);

        return array_values($load);
    }

    // ── Card presenters ─────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function workOrderCard(WorkOrder $wo): array
    {
        return [
            'number' => $wo->work_order_number,
            'title' => $wo->title,
            'equipment' => $wo->equipment?->code,
            'color' => $wo->status->color(),
            'statusLabel' => $wo->status->label(),
            'url' => WorkOrderResource::getUrl('view', ['record' => $wo->getKey()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preventiveCard(MaintenanceSchedule $schedule, string $today): array
    {
        return [
            'equipment' => $schedule->plan?->equipment?->code,
            'name' => $schedule->plan?->name,
            'overdue' => $schedule->next_due_at->format('Y-m-d') < $today,
        ];
    }
}
