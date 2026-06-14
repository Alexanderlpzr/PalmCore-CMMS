<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use App\Models\MaintenancePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ScheduleUpcomingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MaintenancePlan $plan,
        private readonly int $daysAhead,
    ) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Mantenimiento próximo',
            'body' => $this->plan->plan_number.': '.Str::limit($this->plan->name, 150),
            'maintenance_plan_id' => $this->plan->id,
            'plan_number' => $this->plan->plan_number,
            'days_ahead' => $this->daysAhead,
            'url' => '/maintenance/plans/'.$this->plan->id,
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->plan->tenant_id,
            'title' => 'Mantenimiento próximo',
            'body' => $this->plan->plan_number.' vence en '.$this->daysAhead.' días',
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'url' => '/maintenance/plans/'.$this->plan->id,
            'tag' => 'schedule-upcoming-'.$this->plan->id,
        ];
    }
}
