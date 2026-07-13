<?php

namespace App\Domain\Assets\Services;

use App\Models\EquipmentDowntimeEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * How many hours did the plant actually lose?
 *
 * Not the sum of the paros' durations. A plant has one clock: if the prensa and
 * the caldera are both down from 08:00 to 10:00, the plant lost two hours, not
 * four. Summing durations — what this system used to do — inflates the loss with
 * every simultaneous paro and can even push «horas perdidas» above the hours the
 * plant was scheduled to run.
 *
 * So plant-level loss is the **union** of the stoppage intervals, and every
 * interval is **clipped to the window** before it counts: a paro that runs from
 * el 31 a las 22:00 hasta el 1 a las 03:00 costs June two hours and July three,
 * never five to June.
 *
 * The timestamps are the source of truth here, not `duration_minutes` — the
 * number has to be auditable back to the moment the line stopped.
 */
class LostHoursCalculator
{
    /**
     * Union of the (clipped) stoppage intervals matched by the query, in hours.
     *
     * @param  Builder<EquipmentDowntimeEvent>  $query  already scoped to plant, tenant, category…
     */
    public function unionHours(Builder $query, CarbonInterface $from, CarbonInterface $to): float
    {
        $intervals = $this->clippedIntervals($query, $from, $to);

        $seconds = 0;

        foreach ($this->merge($intervals) as [$start, $end]) {
            $seconds += $end->getTimestamp() - $start->getTimestamp();
        }

        return round($seconds / 3600, 2);
    }

    /**
     * Sum of the individual durations of the events that *started* in the window.
     *
     * This is the per-event basis, and it is the right one for MTTR: the mean time
     * to repair one failure does not get shorter because a second machine happened
     * to be broken at the same time.
     *
     * @param  Builder<EquipmentDowntimeEvent>  $query
     */
    public function sumHours(Builder $query, CarbonInterface $from, CarbonInterface $to): float
    {
        $minutes = (float) $query
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw(
                'COALESCE(SUM(COALESCE(duration_minutes,
                    EXTRACT(EPOCH FROM (ended_at - started_at)) / 60)), 0) AS minutes'
            )
            ->value('minutes');

        return round($minutes / 60, 2);
    }

    /**
     * Closed stoppages that touch the window at all, cut down to the part that
     * falls inside it. An open paro has not cost a measurable number of hours yet.
     *
     * @param  Builder<EquipmentDowntimeEvent>  $query
     * @return array<int, array{0: CarbonInterface, 1: CarbonInterface}>
     */
    private function clippedIntervals(Builder $query, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::parse($from);
        $to = Carbon::parse($to);

        $rows = $query
            ->whereNotNull('ended_at')
            ->where('started_at', '<', $to)
            ->where('ended_at', '>', $from)
            ->orderBy('started_at')
            ->get(['started_at', 'ended_at']);

        $intervals = [];

        foreach ($rows as $row) {
            $start = $row->started_at->max($from);
            $end = $row->ended_at->min($to);

            if ($end->greaterThan($start)) {
                $intervals[] = [$start, $end];
            }
        }

        return $intervals;
    }

    /**
     * Collapse overlapping and touching intervals into their union.
     *
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $intervals  sorted by start
     * @return array<int, array{0: CarbonInterface, 1: CarbonInterface}>
     */
    private function merge(array $intervals): array
    {
        $merged = [];

        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last !== null && $start->lessThanOrEqualTo($merged[$last][1])) {
                if ($end->greaterThan($merged[$last][1])) {
                    $merged[$last][1] = $end;
                }

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }
}
