<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@palmcore.app'],
            [
                'name' => 'PalmCore Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'change-me-in-production')),
                'is_active' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
