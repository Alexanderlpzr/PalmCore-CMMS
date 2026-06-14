<?php

namespace App\Listeners;

use App\Domain\Notifications\AlertNotification;
use App\Events\AlertCreated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAlertNotificationListener implements ShouldQueue
{
    public function handle(AlertCreated $event): void
    {
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
