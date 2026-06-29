<?php

namespace App\Policies;

use App\Models\CarouselSlide;
use App\Models\User;

class CarouselSlidePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('carousel-slides.view');
    }

    public function view(User $user, CarouselSlide $carouselSlide): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('carousel-slides.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('carousel-slides.create');
    }

    public function update(User $user, CarouselSlide $carouselSlide): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('carousel-slides.update');
    }

    public function delete(User $user, CarouselSlide $carouselSlide): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('carousel-slides.delete');
    }

    public function restore(User $user, CarouselSlide $carouselSlide): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('carousel-slides.delete');
    }

    public function forceDelete(User $user, CarouselSlide $carouselSlide): bool
    {
        return $user->is_super_admin;
    }
}
