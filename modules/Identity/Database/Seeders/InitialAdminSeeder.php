<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;

/**
 * First administrator for a fresh install, from INITIAL_ADMIN_* config.
 * Does nothing without email and password, or when that user already exists.
 */
class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.initial_admin.email');
        $password = config('app.initial_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $name = config('app.initial_admin.name');

        $user = User::query()->create([
            'name' => is_string($name) && $name !== '' ? $name : 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRolesBySlug(['admin']);
    }
}
