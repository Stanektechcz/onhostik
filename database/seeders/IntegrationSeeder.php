<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds one vault row per catalog provider — WITHOUT credentials.
 * Everything starts inactive + mock + dry-run; the only exceptions are the
 * purely internal mock providers, which are safe to activate by default.
 */
class IntegrationSeeder extends Seeder
{
    private const ACTIVE_MOCKS = ['internal_monitoring', 'ai_mock'];

    public function run(): void
    {
        /** @var array<string, array{label: string, category: string, fields: list<string>}> $providers */
        $providers = (array) config('integrations.providers', []);

        foreach ($providers as $key => $definition) {
            IntegrationSetting::query()->firstOrCreate(
                ['provider' => $key],
                [
                    'label'     => $definition['label'],
                    'is_active' => in_array($key, self::ACTIVE_MOCKS, true),
                    'mock_mode' => true,
                    'dry_run'   => true,
                    'meta'      => ['category' => $definition['category']],
                ],
            );
        }
    }
}
