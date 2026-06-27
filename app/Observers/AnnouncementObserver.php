<?php

namespace App\Observers;

use App\Models\Announcement;
use Illuminate\Support\Facades\Cache;

class AnnouncementObserver
{
    public function saved(Announcement $announcement): void
    {
        Cache::forget("home:announcements:{$announcement->tenant_id}");
    }

    public function deleted(Announcement $announcement): void
    {
        Cache::forget("home:announcements:{$announcement->tenant_id}");
    }

    public function restored(Announcement $announcement): void
    {
        Cache::forget("home:announcements:{$announcement->tenant_id}");
    }
}
