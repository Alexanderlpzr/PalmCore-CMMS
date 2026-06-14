<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use App\Models\SparePart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SparePart $sparePart) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Stock crítico',
            'body' => $this->sparePart->code.': '.$this->sparePart->name,
            'spare_part_id' => $this->sparePart->id,
            'url' => '/mobile/dashboard',
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->sparePart->tenant_id,
            'title' => 'Stock crítico',
            'body' => $this->sparePart->code.': '.$this->sparePart->name,
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'url' => '/mobile/dashboard',
            'tag' => 'low-stock-'.$this->sparePart->id,
        ];
    }
}
