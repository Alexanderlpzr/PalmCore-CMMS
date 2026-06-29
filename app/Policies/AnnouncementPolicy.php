<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('announcements.view');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('announcements.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('announcements.create');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('announcements.update');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('announcements.delete');
    }

    public function restore(User $user, Announcement $announcement): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('announcements.delete');
    }

    public function forceDelete(User $user, Announcement $announcement): bool
    {
        return $user->is_super_admin;
    }
}
