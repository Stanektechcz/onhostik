<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Console\Command;

/**
 * One-shot connection test for aaPanel.
 *
 * Stores credentials encrypted in the DB (integration_settings), then runs
 * a read-only GET to /system?action=GetSystemTotal and reports the result.
 * The write gate (AAPANEL_ALLOW_REAL_WRITES) is NOT required — this is a
 * read-only ping only.
 *
 * Security: api_key is accepted via --api-key option or STDIN prompt (never
 * echoed to output or logs; only 'api_key_set: true' is logged).
 *
 * Usage:
 *   php artisan aapanel:ping --base-url=https://IP:PORT/
 *   php artisan aapanel:ping --base-url=https://IP:PORT/ --api-key=SECRET
 */
class AapanelPingCommand extends Command
{
    protected $signature = 'aapanel:ping
        {--base-url= : AAPanel base URL (e.g. https://45.67.217.22:28133/)}
        {--api-key=  : AAPanel API key (omit to be prompted securely)}
        {--save      : Persist credentials to DB for future use (default: always saves)}';

    protected $description = 'Test live connection to aaPanel (read-only, no write gate required)';

    public function handle(): int
    {
        $baseUrl = $this->option('base-url') ?? $this->ask('AAPanel base URL (e.g. https://IP:PORT/)');
        $apiKey  = $this->option('api-key')  ?? $this->secret('AAPanel API key');

        if (!$baseUrl || !$apiKey) {
            $this->error('Both --base-url and --api-key are required.');
            return self::FAILURE;
        }

        $baseUrl = rtrim((string) $baseUrl, '/');

        $this->info('Saving credentials (encrypted) to integration_settings…');

        $setting = IntegrationSetting::updateOrCreate(
            ['provider' => 'aapanel'],
            [
                'label'       => 'aaPanel (webhosting)',
                'credentials' => ['base_url' => $baseUrl, 'api_key' => (string) $apiKey],
                'is_active'   => true,
                'mock_mode'   => false,
                'dry_run'     => false,
            ],
        );

        $this->line('  api_key_set: true | base_url: ' . $baseUrl);

        $this->info('Running connection test (GET /system?action=GetSystemTotal)…');

        try {
            $result = (new AapanelClient($setting))->connectionTest();
        } catch (\Throwable $e) {
            $this->error('Connection FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($result['ok'] ?? false) {
            $this->info('Connection OK (dry_run=false).');
            if (isset($result['system']) && is_array($result['system'])) {
                foreach ($result['system'] as $k => $v) {
                    if (!is_array($v)) {
                        $this->line("  {$k}: {$v}");
                    }
                }
            }
            return self::SUCCESS;
        }

        $this->error('Connection returned ok=false. Response: ' . json_encode($result));
        return self::FAILURE;
    }
}
