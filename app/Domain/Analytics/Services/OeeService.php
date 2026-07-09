<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Models\EquipmentProductionLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aggregates OEE (Overall Equipment Effectiveness) across production logs. The
 * per-log math lives on EquipmentProductionLog; this service rolls it up for the
 * dashboard — averaging each factor over the logs that can define it.
 */
class OeeService
{
    /**
     * Plant-wide OEE summary over a window (defaults to the trailing 30 days).
     * Each factor is the simple average of the logs where it is defined.
     *
     * @return array{oee: ?float, availability: ?float, performance: ?float, quality: ?float, log_count: int}
     */
    public function plantSummary(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $logs = $this->logsInRange($tenantId, $from, $to);

        return [
            'oee' => $this->average($logs->map(fn (EquipmentProductionLog $l) => $l->oee())),
            'availability' => $this->average($logs->map(fn (EquipmentProductionLog $l) => $l->availability())),
            'performance' => $this->average($logs->map(fn (EquipmentProductionLog $l) => $l->performance())),
            'quality' => $this->average($logs->map(fn (EquipmentProductionLog $l) => $l->quality())),
            'log_count' => $logs->count(),
        ];
    }

    /**
     * Average OEE per equipment over the window, worst first (the equipment
     * dragging the plant down), limited to $limit rows.
     *
     * @return TrendPoint[]
     */
    public function oeeByEquipment(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null, int $limit = 10): array
    {
        return $this->logsInRange($tenantId, $from, $to)
            ->groupBy('equipment_id')
            ->map(function (Collection $logs) {
                $equipment = $logs->first()->equipment;
                $oee = $this->average($logs->map(fn (EquipmentProductionLog $l) => $l->oee()));

                return $oee === null ? null : new TrendPoint(
                    label: $equipment?->name ?? $equipment?->code ?? '—',
                    value: round($oee * 100, 1),
                );
            })
            ->filter()
            ->sortBy('value')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, EquipmentProductionLog>
     */
    private function logsInRange(string $tenantId, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $to = CarbonImmutable::parse($to ?? now())->endOfDay();
        $from = CarbonImmutable::parse($from ?? $to->subDays(30))->startOfDay();

        return EquipmentProductionLog::withoutGlobalScopes()
            ->with('equipment:id,code,name')
            ->where('tenant_id', $tenantId)
            ->whereBetween('log_date', [$from->toDateString(), $to->toDateString()])
            ->get();
    }

    /**
     * Average of the non-null values in the collection, or null when none apply.
     *
     * @param  Collection<int, ?float>  $values
     */
    private function average(Collection $values): ?float
    {
        $defined = $values->filter(fn (?float $v): bool => $v !== null);

        return $defined->isEmpty() ? null : round((float) $defined->avg(), 4);
    }
}
