<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        // Associate existing user by email instead of creating a duplicate.
        $user = User::withTrashed()->where('email', $data['email'])->first();

        if ($user) {
            $user->fill(collect($data)->except('password')->toArray())->save();
        } else {
            $user = User::create($data);
        }

        $tenant = Filament::getTenant();

        if ($tenant && ! $user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
        }

        if ($tenant && filled($roles)) {
            setPermissionsTeamId($tenant->id);
            $user->syncRoles($roles);
        }

        return $user;
    }
}
