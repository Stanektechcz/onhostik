<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe entry point (`php artisan db:seed`): reference data only. Infrastructure
 * instances come from the environment (InfrastructureSeeder); the lab registry
 * (DevInfrastructureSeeder) is run explicitly and refuses production.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([BaseSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, CatalogSeeder::class, DnsTemplateSeeder::class, ContentSeeder::class, InfrastructureSeeder::class]);
    }
}
