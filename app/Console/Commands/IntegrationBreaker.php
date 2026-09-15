<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\Resilience\CircuitBreaker;

/**
 * The circuit breakers in front of a provider instance (audit §5z): after repeated failures calls stop for a cooldown
 * so a wrong password does not lock the account at the vendor (WEDOS WAPI blocks after invalid logins). Shows the state
 * and, with --reset, closes them — only after the cause (the stored credential, the allowed IP) was fixed.
 */
final class IntegrationBreaker extends Command
{
    protected $signature = 'onhost:integrations:breaker {instance : provider instance key, e.g. wedos-main} {--reset : close the breakers after the cause was fixed}';

    protected $description = 'Show or reset the circuit breakers of a provider instance';

    public function handle(CacheRepository $cache, AuditRecorder $audit): int
    {
        $instance = ProviderInstance::query()->where('key', (string) $this->argument('instance'))->first();
        if ($instance === null) {
            $this->error('Unknown instance. Available: '.ProviderInstance::query()->orderBy('key')->pluck('key')->implode(', '));

            return self::FAILURE;
        }
        $breakers = [$instance->key => app(ProviderHttpClient::class)->breaker($instance->key)]; // the HTTP client's breaker of the instance
        if (in_array($instance->provider, ['wedos', 'wedos_zone'], true)) {
            $breakers['wapi:invalid'] = new CircuitBreaker($cache, 'wapi:invalid', 10, 900, 3600); // WapiGateway: invalid requests and auth failures
        }
        $rows = [];
        foreach ($breakers as $key => $breaker) {
            $before = $breaker->state();
            if ($this->option('reset')) {
                $breaker->reset();
            }
            $rows[] = [$key, $before, $breaker->failures(), $this->option('reset') ? $breaker->state() : '—'];
        }
        $this->table(['breaker', 'state', 'failures', 'after reset'], $rows);
        if ($this->option('reset')) {
            $audit->record(CommandContext::system('cli:integrations:breaker'), 'provider.instance.breaker_reset', 'succeeded', ['key' => $instance->key, 'breakers' => array_keys($breakers)], 'provider_instance', $instance->id);
            $this->info('Breakers closed. The next call goes through; if the cause remains they open again.');
        }

        return self::SUCCESS;
    }
}
