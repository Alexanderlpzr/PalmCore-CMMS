<?php

namespace App\Observers;

use App\Domain\Reliability\Services\EquipmentKpiService;
use App\Jobs\RecalculateEquipmentKpisJob;
use App\Models\EquipmentDowntimeEvent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class EquipmentDowntimeEventObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly EquipmentKpiService $service) {}

    public function updated(EquipmentDowntimeEvent $event): void
    {
        if (! $event->wasChanged('ended_at')) {
            return;
        }

        if ($event->getOriginal('ended_at') !== null) {
            return;
        }

        if ($event->ended_at === null) {
            return;
        }

        $this->service->markStale($event->equipment_id);

        RecalculateEquipmentKpisJob::dispatch($event->equipment_id)->afterCommit();
    }
}
