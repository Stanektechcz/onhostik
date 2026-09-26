<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\IdentityCommandAuthorizer;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Support\Assistant\AiProviderRegistry;
use Onhost\Platform\Clock\Clock;
use Onhost\Platform\Clock\SystemClock;
use Onhost\Platform\Commands\CommandAuthorizer;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\IdempotencyStore;
use Onhost\Platform\Observability\Tracer;
use Onhost\Platform\ProviderHttp\ProviderCallLogger;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\Secrets\DbSecretStore;
use Onhost\Platform\Secrets\EnvSecretStore;
use Onhost\Platform\Secrets\OpenBaoSecretStore;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\IpGeoProvider;
use Onhost\Providers\Contracts\VatNumberValidator;
use Onhost\Providers\IpGeo\HttpIpGeoProvider;
use Onhost\Providers\IpGeo\NullIpGeoProvider;
use Onhost\Providers\IspConfig\IspConfigWebProvider;
use Onhost\Providers\Kubernetes\KubernetesAppsProvider;
use Onhost\Providers\Pbs\PbsBackupProvider;
use Onhost\Providers\PowerDns\PowerDnsProvider;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;
use Onhost\Providers\Pterodactyl\PterodactylGameProvider;
use Onhost\Providers\Subreg\SubregRegistrarProvider;
use Onhost\Providers\Vies\DisabledVatNumberValidator;
use Onhost\Providers\Vies\ViesVatNumberValidator;
use Onhost\Providers\Wedos\WedosRegistrarProvider;
use Onhost\Providers\Wedos\WedosZoneDnsProvider;
use RuntimeException;

/** Kernel bindings: clock, secrets, command bus, provider HTTP client, provider adapter registry, Sanctum token model. */
final class PlatformServiceProvider extends ServiceProvider
{
    /** provider key (provider_instances.provider) => adapter class. The only place adapters are wired. */
    public const ADAPTERS = [
        'proxmox' => ProxmoxComputeProvider::class,
        'pbs' => PbsBackupProvider::class,
        'ispconfig' => IspConfigWebProvider::class,
        'aapanel' => AaPanelWebProvider::class,
        'pterodactyl' => PterodactylGameProvider::class,
        'powerdns' => PowerDnsProvider::class,
        'wedos' => WedosRegistrarProvider::class,
        'wedos_zone' => WedosZoneDnsProvider::class,
        'subreg' => SubregRegistrarProvider::class,
        'kubernetes' => KubernetesAppsProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, function ($app): ProviderRegistry {
            $registry = new ProviderRegistry($app, $app->make(SecretStore::class));
            foreach (self::ADAPTERS as $key => $class) {
                $registry->register($key, $class);
            }

            return $registry;
        });
        $this->app->singleton(Clock::class, SystemClock::class);
        // country of an order's IP (audit §5g-4): any JSON endpoint with {ip}; nothing configured = no signal
        $this->app->singleton(IpGeoProvider::class, function ($app): IpGeoProvider {
            $endpoint = trim((string) config('onhost.orders.risk.geo.endpoint', ''));

            return $endpoint === '' ? new NullIpGeoProvider : new HttpIpGeoProvider($app->make('cache.store'), $endpoint, (int) config('onhost.orders.risk.geo.timeout_seconds', 2));
        });
        // ── TASK-0031 ──
        // VAT numbers in VIES (D31.1): off until go-live (ONHOST_VIES_ENABLED); resolved per call, so the switch needs no restart
        $this->app->bind(VatNumberValidator::class, fn ($app): VatNumberValidator => (bool) config('onhost.vies.enabled', false) ? $app->make(ViesVatNumberValidator::class) : new DisabledVatNumberValidator);
        // ── end TASK-0031 ──
        $this->app->singleton(Authorizer::class); // every holder shares it, so a revoked binding is forgotten everywhere at once
        $this->app->singleton(CommandAuthorizer::class, IdentityCommandAuthorizer::class);
        $this->app->singleton(CommandBus::class);
        $this->app->singleton(Tracer::class); // one span buffer per process (audit §5q-2)
        $this->app->singleton(IdempotencyStore::class, fn () => new IdempotencyStore((int) config('onhost.api.idempotency_ttl_hours', 24)));

        $this->app->singleton(SecretStore::class, function ($app): SecretStore {
            $driver = (string) config('onhost.secrets.driver', 'env');
            if ($driver === 'openbao') {
                $cfg = config('onhost.secrets.openbao');

                $base = new OpenBaoSecretStore(
                    $app->make(HttpFactory::class),
                    (string) $cfg['address'],
                    $cfg['token'] ?: null,
                    $cfg['role_id'] ?: null,
                    $cfg['secret_id'] ?: null,
                    (string) ($cfg['namespace'] ?? ''),
                    60,
                    $cfg['ca_cert'] ?: null,
                );
            } elseif ($driver === 'db') {
                // operator-managed credentials encrypted with APP_KEY (no OpenBao); allowed in production only when explicitly chosen
                $base = new EnvSecretStore;
            } elseif ($app->environment('production')) {
                throw new RuntimeException('ONHOST_SECRETS_DRIVER=env is not permitted in production; configure OpenBao (blueprint §19) or ONHOST_SECRETS_DRIVER=db.');
            } else {
                $base = new EnvSecretStore;
            }

            // `db://` references (credentials registered in the admin console) are always available on top of the base driver.
            return new DbSecretStore($base, $app->make(\Illuminate\Encryption\Encrypter::class)); // the concrete encrypter carries encryptString()/decryptString()
        });

        $this->app->singleton(AiProviderRegistry::class);

        $this->app->singleton(ProviderHttpClient::class, function ($app): ProviderHttpClient {
            return new ProviderHttpClient(
                $app->make(HttpFactory::class),
                $app->make(CacheRepository::class),
                $app->make(ProviderCallLogger::class),
                diagnosticReserve: (float) config('onhost.provisioning.diagnostic_reserve', 0.05),
            );
        });
    }

    public function boot(): void
    {
        // The authorizer remembers a principal's bindings for as long as it lives. A web request is short; a queue worker, the
        // scheduler and a test process are not — a permission taken away would keep being answered with yesterday's yes.
        Event::listen(RequestHandled::class, fn () => $this->app->make(Authorizer::class)->flush());
        Queue::before(fn () => $this->app->make(Authorizer::class)->flush());
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
