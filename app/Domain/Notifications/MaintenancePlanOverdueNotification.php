<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use App\Models\MaintenancePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MaintenancePlanOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly MaintenancePlan $plan) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Mantenimiento vencido',
            'body' => $this->plan->plan_number.': '.Str::limit($this->plan->name, 150),
            'maintenance_plan_id' => $this->plan->id,
            'plan_number' => $this->plan->plan_number,
            'url' => '/mobile/dashboard',
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->plan->tenant_id,
            'title' => 'Mantenimiento vencido',
            'body' => $this->plan->plan_number.': '.Str::limit($this->plan->name, 100),
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'url' => '/mobile/dashboard',
            'tag' => 'maintenance-overdue-'.$this->plan->id,
        ];
    }
}
