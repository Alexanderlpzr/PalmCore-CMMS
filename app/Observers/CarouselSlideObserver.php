<?php

namespace App\Observers;

use App\Models\CarouselSlide;
use Illuminate\Support\Facades\Cache;

class CarouselSlideObserver
{
    public function saved(CarouselSlide $slide): void
    {
        Cache::forget("home:{$slide->tenant_id}:carousel");
    }

    public function deleted(CarouselSlide $slide): void
    {
        Cache::forget("home:{$slide->tenant_id}:carousel");
    }

    public function restored(CarouselSlide $slide): void
    {
        Cache::forget("home:{$slide->tenant_id}:carousel");
    }
}
