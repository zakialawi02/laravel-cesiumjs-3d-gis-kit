<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Create the initial admin and engineering accounts.
     *
     * Does nothing once any account exists (including soft-deleted ones), so re-running
     * `db:seed` for the category data never resets changed passwords or re-creates accounts.
     * Change these default passwords after the first login on any shared or deployed instance.
     */
    public function run(): void
    {
        if (User::query()->withTrashed()->exists()) {
            $this->command?->info('Users already exist; skipping initial account creation.');

            return;
        }

        $accounts = [
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@mail.com',
                'password' => 'admin',
                'role' => UserRole::Admin,
                'profile_photo_path' => '/assets/img/profile/admin.png',
            ],
            [
                'name' => 'Engineering User',
                'username' => 'engineering',
                'email' => 'engineering@mail.com',
                'password' => 'engineering',
                'role' => UserRole::Engineering,
                'profile_photo_path' => '/assets/img/profile/user.png',
            ],
        ];

        foreach ($accounts as $account) {
            User::query()->create($account + ['email_verified_at' => now()]);
        }
    }
}
