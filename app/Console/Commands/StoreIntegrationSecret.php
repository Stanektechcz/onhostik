<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodePrerequisites;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Stores a provider credential from the terminal without it touching the shell history, a file or a log: the value
 * is asked for with a hidden prompt (or read from STDIN when piped), written to the encrypted `db://` secret of the
 * instance and, on request, verified with a connection test and the prerequisites check. A value that replaces a stored
 * one is tried against the panel first and refused when the panel does not accept it — the working access stays
 * (Brain card H314); `--force` stores it regardless and the audit record says so. Operators who prefer the
 * staff console use *Nastavení systému → Integrace* — the same store, the same audit record.
 */
final class StoreIntegrationSecret extends Command
{
    protected $signature = 'onhost:integrations:secret {instance : Instance key (e.g. pterodactyl-gamepanel)} {key? : Credential key (application_key, client_key, api_key, …); omitted = every key the provider knows} {--stdin : Read the value from standard input instead of the hidden prompt} {--check : Run the connection test and the prerequisites check afterwards} {--force : Store the value even though the panel did not confirm it (the stored access is compromised or the panel is down)}';

    protected $description = 'Store a provider credential in the encrypted secret store (hidden prompt, never an argument) and optionally verify the instance';

    public function handle(ProviderInstanceService $instances, NodePrerequisites $prerequisites): int
    {
        $instance = ProviderInstance::query()->where('key', (string) $this->argument('instance'))->first();
        if ($instance === null) {
            $this->error('No such instance. Register it first (staff console → Integrace, or POST /v1/staff/integrations).');

            return self::FAILURE;
        }
        $schema = ProviderInstanceService::CREDENTIALS[$instance->provider] ?? ['required' => [], 'optional' => [], 'hint' => ''];
        $keys = $this->argument('key') !== null ? [(string) $this->argument('key')] : array_merge($schema['required'], $schema['optional']);
        $known = array_merge($schema['required'], $schema['optional']);
        foreach ($keys as $key) {
            if (! in_array($key, $known, true)) {
                $this->error("Unknown credential key '{$key}' for {$instance->provider}; known: ".implode(', ', $known).'.');

                return self::FAILURE;
            }
        }
        if ($schema['hint'] !== '') {
            $this->line($schema['hint']);
        }
        $credentials = [];
        foreach ($keys as $key) {
            $value = $this->option('stdin') ? trim((string) stream_get_contents(STDIN)) : (string) $this->secret("Value for {$key} (blank keeps the stored value)");
            if ($value !== '') {
                $credentials[$key] = $value;
            }
            if ($this->option('stdin')) {
                break; // one value per pipe
            }
        }
        if ($credentials === []) {
            $this->warn('Nothing to store.');

            return self::SUCCESS;
        }
        $context = CommandContext::system('cli:integrations:secret');
        try {
            $instances->upsert(['key' => $instance->key, 'provider' => $instance->provider, 'base_url' => (string) $instance->base_url, 'credentials' => $credentials, 'force_credentials' => (bool) $this->option('force')], $context);
        } catch (DomainError $e) {
            $this->error($e->getMessage());
            if ($e->error === 'instance_credentials_unverified') {
                $this->line('Nothing was stored. Check the value and the rights of the key at the panel; use --force only when the stored access is compromised.');
            }

            return self::FAILURE;
        }
        $status = $instances->credentialStatus($instance->fresh());
        $this->info('Stored '.implode(', ', array_keys($credentials)).' in '.$status['secret_ref'].'; present: '.implode(', ', $status['present']).($status['missing'] !== [] ? '; still missing: '.implode(', ', $status['missing']) : ''));

        if ($this->option('check')) {
            $probe = $instances->probe($instance->fresh(), $context);
            $this->line('Connection: '.($probe['up'] ? 'up' : 'down'.(isset($probe['error']) ? ' — '.$probe['error'] : '')).(isset($probe['latency_ms']) ? " ({$probe['latency_ms']} ms)" : ''));
            $check = $prerequisites->check($instance->fresh(), $context);
            $this->line('API: '.$check['api'].($check['client_api'] !== null ? ' · client API: '.$check['client_api'] : ''));
            foreach ((array) ($check['game']['nodes'] ?? []) as $node) {
                $this->line("Node {$node['name']} (#{$node['id']}): {$node['servers']} servers · daemon {$node['daemon']}".($node['maintenance'] ? ' · maintenance' : ''));
            }
            foreach ((array) ($check['game']['eggs'] ?? []) as $key => $state) {
                $this->line("Template {$key}: {$state}");
            }
            foreach ($check['warnings'] as $warning) {
                $this->warn($warning);
            }
        }

        return self::SUCCESS;
    }
}
