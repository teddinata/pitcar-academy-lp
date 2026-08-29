<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first admin so someone can get into the panel. Credentials come
 * from env — never hard-code a password that ends up in git history.
 */
class DashboardUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->warn('Set ADMIN_EMAIL and ADMIN_PASSWORD to seed the first dashboard admin.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        $this->command?->info('Admin ready: '.$email);
    }
}
