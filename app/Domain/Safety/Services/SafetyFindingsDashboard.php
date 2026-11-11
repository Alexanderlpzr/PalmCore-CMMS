<?php

namespace App\Domain\Safety\Services;

use App\Domain\Safety\Enums\FindingOrigin;
use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Models\SstFinding;
use App\Models\SstFindingTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Las cifras del Tablero SST: cuántos hallazgos siguen abiertos, cuáles se vencieron,
 * cómo va cada área responsable y en qué secciones se concentran.
 */
class SafetyFindingsDashboard
{
    /**
     * @return array{
     *     total: int, open: int, pendiente: int, enEjecucion: int, porVerificar: int, verificados: int, vencidos: int, altaAbiertos: int, corregidosPercent: float,
     *     byOrigin: list<array{origin: string, label: string, icon: string, total: int, open: int, overdue: int, porVerificar: int}>,
     *     byArea: list<array{label: string, total: int, done: int, open: int, overdue: int, percent: float}>,
     *     byPriority: list<array{label: string, color: string, days: int, open: int, overdue: int}>,
     *     bySection: list<array{label: string, open: int}>,
     *     overdueList: Collection<int, SstFinding>
     * }
     */
    public function summary(string $tenantId, ?CarbonImmutable $today = null): array
    {
        $today ??= SstFinding::today($tenantId);
        $findings = SstFinding::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['area', 'equipment', 'tasks'])
            ->get();

        $open = $findings->filter(fn (SstFinding $finding): bool => $finding->status->isOpen());
        $overdue = $open->filter(fn (SstFinding $finding): bool => $finding->isOverdue($today));
        $count = fn (FindingStatus $status): int => $findings->where('status', $status)->count();
        $tasks = $findings->flatMap(fn (SstFinding $finding): Collection => $finding->tasks->map(fn (SstFindingTask $task): array => [$task, $finding]));

        return [
            'total' => $findings->count(),
            'open' => $open->count(),
            'pendiente' => $count(FindingStatus::Pendiente),
            'enEjecucion' => $count(FindingStatus::EnEjecucion),
            'porVerificar' => $count(FindingStatus::Ejecutado),
            'verificados' => $count(FindingStatus::Verificado),
            'vencidos' => $overdue->count(),
            'altaAbiertos' => $open->where('priority', FindingPriority::Alta)->count(),
            'corregidosPercent' => $findings->isEmpty() ? 0.0 : round(($findings->count() - $open->count()) / $findings->count() * 100, 1),

            'byArea' => collect(ResponsibleArea::cases())
                ->map(function (ResponsibleArea $area) use ($tasks, $today): array {
                    $mine = $tasks->filter(fn (array $pair): bool => $pair[0]->area === $area);
                    $done = $mine->filter(fn (array $pair): bool => $pair[0]->status === FindingStatus::Ejecutado)->count();
                    $pending = $mine->filter(fn (array $pair): bool => $pair[0]->status !== FindingStatus::Ejecutado);

                    return [
                        'label' => $area->label(),
                        'total' => $mine->count(),
                        'done' => $done,
                        'open' => $pending->count(),
                        'overdue' => $pending->filter(fn (array $pair): bool => $pair[1]->isOverdue($today))->count(),
                        'percent' => $mine->isEmpty() ? 0.0 : round($done / $mine->count() * 100, 1),
                    ];
                })
                ->filter(fn (array $row): bool => $row['total'] > 0)
                ->values()
                ->all(),

            // SST y Ambiental por separado, para la trazabilidad de cada uno.
            'byOrigin' => collect(FindingOrigin::cases())
                ->map(fn (FindingOrigin $origin): array => [
                    'origin' => $origin->value,
                    'label' => $origin->label(),
                    'icon' => $origin->icon(),
                    'total' => $findings->where('origin', $origin)->count(),
                    'open' => $open->where('origin', $origin)->count(),
                    'overdue' => $overdue->where('origin', $origin)->count(),
                    'porVerificar' => $findings->where('origin', $origin)->where('status', FindingStatus::Ejecutado)->count(),
                ])
                ->all(),

            'byPriority' => collect(FindingPriority::cases())
                ->map(fn (FindingPriority $priority): array => [
                    'label' => $priority->label(),
                    'color' => $priority->color(),
                    'days' => $priority->days(),
                    'open' => $open->where('priority', $priority)->count(),
                    'overdue' => $overdue->where('priority', $priority)->count(),
                ])
                ->all(),

            'bySection' => $open
                ->groupBy(fn (SstFinding $finding): string => $finding->area?->name ?? 'Sin sección')
                ->map(fn (Collection $group, string $label): array => ['label' => $label, 'open' => $group->count()])
                ->sortByDesc('open')
                ->values()
                ->all(),

            'overdueList' => $overdue->sortBy(fn (SstFinding $finding): string => $finding->due_on->toDateString())->take(8)->values(),
        ];
    }
}
