<?php

namespace App\Listeners;

use App\Domain\Notifications\AlertNotification;
use App\Events\AlertCreated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAlertNotificationListener implements ShouldQueue
{
    public function handle(AlertCreated $event): void
    {
        Log::withContext([
            'alert_id' => $event->alert->id ?? null,
            'alert_severity' => $event->alert->severity?->value ?? null,
            'tenant_id' => $event->alert->tenant_id ?? null,
        ]);
        Log::info('alert.notification_dispatched');

        if (empty($event->notifiableUserIds)) {
            return;
        }

        $notification = new AlertNotification($event->alert);

        User::withoutGlobalScopes()
            ->whereIn('id', $event->notifiableUserIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->each(fn (User $user) => $user->notify($notification));
    }
}
