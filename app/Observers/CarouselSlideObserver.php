<?php

namespace App\Observers;

use App\Models\CarouselSlide;
use Illuminate\Support\Facades\Cache;

class CarouselSlideObserver
{
    public function saved(CarouselSlide $slide): void
    {
        Cache::forget("home:carousel:{$slide->tenant_id}");
    }

    public function deleted(CarouselSlide $slide): void
    {
        Cache::forget("home:carousel:{$slide->tenant_id}");
    }

    public function restored(CarouselSlide $slide): void
    {
        Cache::forget("home:carousel:{$slide->tenant_id}");
    }
}
