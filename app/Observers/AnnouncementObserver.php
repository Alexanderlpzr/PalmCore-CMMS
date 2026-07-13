<?php

namespace App\Observers;

use App\Models\Announcement;
use Illuminate\Support\Facades\Cache;

class AnnouncementObserver
{
    public function saved(Announcement $announcement): void
    {
        $this->flush($announcement);
    }

    public function deleted(Announcement $announcement): void
    {
        $this->flush($announcement);
    }

    public function restored(Announcement $announcement): void
    {
        $this->flush($announcement);
    }

    private function flush(Announcement $announcement): void
    {
        Cache::forget("home:{$announcement->tenant_id}:notices");
        Cache::forget("home:{$announcement->tenant_id}:news");
    }
}
