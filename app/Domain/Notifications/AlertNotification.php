<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Alert $alert) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->alert->title,
            'body' => Str::limit($this->alert->message ?? $this->alert->title, 200),
            'alert_id' => $this->alert->id,
            'severity' => $this->alert->severity->value,
            'category' => $this->alert->category->value,
            'entity_type' => $this->alert->entity_type,
            'entity_id' => $this->alert->entity_id,
            'url' => '/mobile/alerts',
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->alert->tenant_id,
            'title' => $this->prefixedTitle(),
            'body' => Str::limit($this->alert->message ?? $this->alert->title, 120),
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'url' => '/mobile/alerts',
            'tag' => 'alert-'.$this->alert->id,
        ];
    }

    private function prefixedTitle(): string
    {
        return match ($this->alert->severity) {
            AlertSeverity::Critical => '🔴 '.$this->alert->title,
            AlertSeverity::Warning => '🟡 '.$this->alert->title,
            AlertSeverity::Info => 'ℹ️ '.$this->alert->title,
        };
    }
}
