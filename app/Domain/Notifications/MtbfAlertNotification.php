<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Equipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MtbfAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Equipment $equipment,
        private readonly float $mtbfHours,
        private readonly float $thresholdHours,
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
            'title' => 'MTBF crítico',
            'body' => sprintf('%s — MTBF: %.1f h (umbral: %.1f h)', $this->equipment->code, $this->mtbfHours, $this->thresholdHours),
            'equipment_id' => $this->equipment->id,
            'mtbf_hours' => $this->mtbfHours,
            'threshold' => $this->thresholdHours,
            'url' => '/reliability/equipment/'.$this->equipment->id,
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->equipment->tenant_id,
            'title' => 'MTBF crítico',
            'body' => sprintf('%s — %.1f h (umbral %.1f h)', $this->equipment->code, $this->mtbfHours, $this->thresholdHours),
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'url' => '/reliability/equipment/'.$this->equipment->id,
            'tag' => 'mtbf-alert-'.$this->equipment->id,
        ];
    }
}
