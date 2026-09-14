<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Platform\Commands\CommandContext;

/**
 * Registers (or updates) a provider instance from the terminal — the same record the console's *Integrace* form
 * creates: key, provider, panel URL, region, options. Credentials come afterwards with `onhost:integrations:secret`.
 */
final class RegisterIntegration extends Command
{
    protected $signature = 'onhost:integrations:register {key : Instance key, e.g. pterodactyl-gamepanel} {provider : '.'proxmox|pbs|ispconfig|aapanel|pterodactyl|powerdns|wedos|wedos_zone|subreg|kubernetes'.'} {url : Base URL of the panel/API} {--name=} {--region=cz1} {--option=* : option as key=value (e.g. server_id=1)} {--state=active}';

    protected $description = 'Register a provider instance (panel URL, region, options); store its keys with onhost:integrations:secret';

    public function handle(ProviderInstanceService $instances): int
    {
        $options = [];
        foreach ((array) $this->option('option') as $pair) {
            [$k, $v] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $options[trim($k)] = is_numeric($v) ? $v + 0 : $v;
        }
        $instance = $instances->upsert(array_filter([
            'key' => (string) $this->argument('key'), 'provider' => (string) $this->argument('provider'), 'base_url' => (string) $this->argument('url'),
            'name' => $this->option('name') ?: null, 'region_code' => $this->option('region') ?: null, 'options' => $options ?: null, 'state' => (string) $this->option('state'),
        ], fn ($v) => $v !== null), CommandContext::system('cli:integrations:register'));
        $status = $instances->credentialStatus($instance);
        $this->info("Instance {$instance->key} ({$instance->provider}) → {$instance->base_url}; region ".($instance->region_code ?? '—').'; credentials '.($status['missing'] === [] ? 'complete' : 'missing: '.implode(', ', $status['missing'])." → php artisan onhost:integrations:secret {$instance->key} <key> --check"));

        return self::SUCCESS;
    }
}
