<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\ProxmoxClient;
use App\Domains\Integrations\Clients\PterodactylClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use Throwable;

/**
 * Safe connection tests for the admin Integrations screen.
 *
 * Mock/dry-run providers always test locally (no HTTP). Real tests only
 * happen for providers whose refusal gates are fully open — which in this
 * phase is never, by design.
 */
final class ConnectionTester
{
    /** @return array{ok: bool, message: string, dry_run: bool} */
    public function test(IntegrationSetting $setting): array
    {
        try {
            $result = $this->run($setting);

            $setting->update([
                'last_success_at'    => now(),
                'last_error_message' => null,
            ]);

            activity('integration')
                ->performedOn($setting)
                ->withProperties(['provider' => $setting->provider, 'dry_run' => $result['dry_run']])
                ->log('integration.connection_test_ok');

            return $result;
        } catch (Throwable $e) {
            $setting->update([
                'last_error_at'      => now(),
                'last_error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            activity('integration')
                ->performedOn($setting)
                ->withProperties(['provider' => $setting->provider, 'error' => mb_substr($e->getMessage(), 0, 200)])
                ->log('integration.connection_test_failed');

            return ['ok' => false, 'message' => $e->getMessage(), 'dry_run' => true];
        }
    }

    /** @return array{ok: bool, message: string, dry_run: bool} */
    private function run(IntegrationSetting $setting): array
    {
        $result = match ($setting->provider) {
            'aapanel'     => (new AapanelClient($setting))->connectionTest(),
            'wedos'       => (new WedosWapiClient($setting))->connectionTest(),
            'pterodactyl' => (new PterodactylClient($setting))->connectionTest(),
            'proxmox'     => (new ProxmoxClient($setting))->connectionTest(),
            default       => null,
        };

        if ($result !== null) {
            return [
                'ok'      => (bool) ($result['ok'] ?? false),
                'message' => is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Connection test passed (dry-run).',
                'dry_run' => (bool) ($result['dry_run'] ?? true),
            ];
        }

        // Generic providers: mock/dry-run providers pass a local self-test;
        // real connectivity tests arrive with their live implementations.
        if ($setting->mock_mode || $setting->dry_run || !$setting->is_active) {
            return ['ok' => true, 'message' => 'Mock/dry-run self-test OK (no HTTP sent).', 'dry_run' => true];
        }

        return [
            'ok'      => false,
            'message' => "Real connection test for [{$setting->provider}] is not implemented yet.",
            'dry_run' => false,
        ];
    }
}
