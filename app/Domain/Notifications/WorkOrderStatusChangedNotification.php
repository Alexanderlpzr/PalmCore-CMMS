<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class WorkOrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WorkOrder $workOrder,
        private readonly WorkOrderStatus $newStatus,
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
            'title' => $this->title(),
            'body' => $this->workOrder->work_order_number.': '.Str::limit($this->workOrder->title, 150),
            'work_order_id' => $this->workOrder->id,
            'work_order_number' => $this->workOrder->work_order_number,
            'status' => $this->newStatus->value,
            'url' => '/mobile/work-orders/'.$this->workOrder->id,
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->workOrder->tenant_id,
            'title' => $this->title(),
            'body' => $this->workOrder->work_order_number.': '.Str::limit($this->workOrder->title, 100),
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'url' => '/mobile/work-orders/'.$this->workOrder->id,
            'tag' => 'wo-status-'.$this->workOrder->id,
        ];
    }

    private function title(): string
    {
        return match ($this->newStatus) {
            WorkOrderStatus::Planned => 'OT planificada',
            WorkOrderStatus::InProgress => 'OT en ejecución',
            WorkOrderStatus::Completed => 'OT completada',
            default => 'Cambio de estado en OT',
        };
    }
}
