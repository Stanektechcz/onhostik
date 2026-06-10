<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the local development admin account.
 *
 * Credentials come from .env (ADMIN_EMAIL / ADMIN_PASSWORD) with safe
 * local defaults. NEVER seed this in production with default values.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@onhost.local')],
            [
                'name'              => 'OnHost Admin',
                'password'          => env('ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
                'locale'            => 'cs',
            ],
        );

        $user->assignRole('admin');
    }
}
