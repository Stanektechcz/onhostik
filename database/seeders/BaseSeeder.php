<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Reference data every environment (and every test) needs: roles/permissions and notification templates. */
final class BaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AuthorizationSeeder::class, NotificationTemplateSeeder::class, SupportSeeder::class, StatusSeeder::class]);
    }
}
