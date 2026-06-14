<?php

namespace App\Domain\Reliability\DTOs;

use Carbon\CarbonImmutable;

/**
 * Immutable result of a single KPI calculation run.
 * Passed from EquipmentKpiService::calculateForEquipment() into persist().
 */
readonly class EquipmentKpiData
{
    public function __construct(
        public int $periodMonths,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,

        /** null when failure_count = 0 or period < 1 hour */
        public ?float $mtbfHours,

        /** null when failure_count = 0 or period < 1 hour */
        public ?float $mttrHours,

        /** null when period < 1 hour; otherwise 0–100 */
        public ?float $availabilityPercentage,

        /** null when period < 1 hour; otherwise 0–100 */
        public ?float $unplannedAvailabilityPercentage,

        /** count of closed unplanned (was_planned = false) events in window */
        public int $failureCount,

        /** total hours of unplanned downtime in window */
        public float $downtimeHours,

        /** started_at of the most recent unplanned event in window */
        public ?CarbonImmutable $lastFailureAt,
    ) {}
}
