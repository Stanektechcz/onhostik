<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\DatabaseCredentials;
use Onhost\Domain\Services\Web\StagingService;
use Onhost\Domain\Services\Web\WordPressService;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\Q;

/**
 * WordPress actions through WP-CLI: updates (direct, or staged: refresh staging → update there → health check →
 * update production), Redis object cache on/off, plugin operations.
 */
final class WordPressWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.wordpress';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance?->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        $action = (string) data_get($operation->desired, 'action');
        if ($action === 'wp.cache') {
            return [$this->cacheStep()];
        }
        if ($action === 'wp.install') {
            return [$this->installStep()];
        }
        if ($action === 'wp.plugin') {
            return [$this->pluginStep()];
        }
        $service = Service::query()->find($operation->service_id);
        $link = $service ? app(StagingService::class)->link($service) : null;
        $stagingReady = $link !== null && $link->service_id === $service?->id && Service::query()->whereKey($link->staging_service_id)->where('state', ServiceStateMachine::ACTIVE)->exists();
        if ((bool) data_get($operation->desired, 'staged', true) && $stagingReady) {
            return [$this->refreshStagingStep(), $this->updateStep('staging'), $this->verifyStep(), $this->updateStep('production')];
        }

        return [$this->updateStep('production')];
    }

    public function compensate(StepContext $context): void
    {
        $context->container->make(WordPressService::class)->forget($context->service);
    }

    private function refreshStagingStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Obnova staging kopie';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $staging = $context->container->make(StagingService::class);
                $link = $staging->link($service);
                $copy = Service::query()->findOrFail($link->staging_service_id);
                $result = $staging->sync($service, $copy, $link, true, $context->actor);
                $link->forceFill(['last_synced_at' => now(), 'databases' => $result['databases']])->save();

                return StepResult::done(['log' => "== staging refreshed ==\n".$result['log'], 'staging_service_id' => $copy->id, 'staging_domain' => $link->staging_domain]);
            }
        };
    }

    private function updateStep(string $target): ServiceStep
    {
        return new class($target) extends ServiceStep
        {
            public function __construct(private readonly string $target) {}

            public function label(): string
            {
                return $this->target === 'staging' ? 'Aktualizace na stagingu' : 'Aktualizace WordPressu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->target === 'staging' ? Service::query()->findOrFail((string) $context->get('staging_service_id')) : $this->service($context);
                $wp = $context->container->make(WordPressService::class);
                if (! $wp->installed($service)) {
                    return StepResult::fail('WordPress is not installed in this site (no wp-config.php)', false);
                }
                $what = (string) $context->desired('what', 'all');
                $log = (string) $context->get('log', '')."== {$this->target}: update {$what} ==\n";
                $commands = [];
                if (in_array($what, ['core', 'all'], true)) {
                    $commands[] = 'core update';
                    $commands[] = 'core update-db';
                }
                if (in_array($what, ['plugins', 'all'], true)) {
                    $commands[] = 'plugin update --all';
                }
                if (in_array($what, ['themes', 'all'], true)) {
                    $commands[] = 'theme update --all';
                }
                $commands[] = 'language core update';
                $commands[] = 'cache flush';
                foreach ($commands as $args) {
                    $run = $wp->run($service, $args, 900);
                    $log .= "$ wp {$args}\n".$run->output()."\n";
                    if (! $run->ok() && ! in_array($args, ['language core update', 'cache flush'], true)) {
                        $wp->forget($service);

                        return StepResult::fail("wp {$args} failed on {$this->target}: ".mb_substr(trim($run->output()), -300), false, ['log' => $log]);
                    }
                }
                $wp->forget($service);
                $version = trim($wp->run($service, 'core version', 60, true)->stdout);

                return StepResult::done(['log' => $log, $this->target.'_version' => $version]);
            }
        };
    }

    private function verifyStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Kontrola stagingu po aktualizaci';
            }

            public function run(StepContext $context): StepResult
            {
                $domain = (string) $context->get('staging_domain');
                $check = $context->container->make(WordPressService::class)->healthCheck('https://'.$domain.'/');
                $log = (string) $context->get('log', '')."== health check https://{$domain}/ → ".($check['ok'] ? 'OK' : 'FAILED').' ('.($check['status'] ?? 'no response').")\n";
                if (! $check['ok']) {
                    return StepResult::fail('the staging site does not answer correctly after the update ('.($check['error'] ?? 'unknown').'); production was left untouched', false, ['log' => $log, 'staging_domain' => $domain]);
                }

                return StepResult::done(['log' => $log]);
            }
        };
    }

    private function cacheStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Redis object cache';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $wp = $context->container->make(WordPressService::class);
                if (! $wp->installed($service)) {
                    return StepResult::fail('WordPress is not installed in this site (no wp-config.php)', false);
                }
                $enabled = (bool) $context->desired('enabled', true);
                $log = '';
                $step = function (string $args, bool $must = true, int $t = 300) use (&$log, $wp, $service): bool {
                    $run = $wp->run($service, $args, $t);
                    $log .= "$ wp {$args}\n".$run->output()."\n";

                    return $run->ok() || ! $must;
                };
                if (! $enabled) {
                    $step('redis disable', false);
                    $step('plugin deactivate redis-cache', false);
                    $step('config delete WP_REDIS_HOST', false, 60);
                    $step('cache flush', false, 60);
                    $wp->forget($service);

                    return StepResult::done(['log' => $log, 'enabled' => false]);
                }
                [$host, $port] = $wp->redisEndpoint($service);
                $ping = $wp->run($service, '--info >/dev/null 2>&1; timeout 3 bash -c '.Q::arg('echo > /dev/tcp/'.$host.'/'.$port).' 2>/dev/null && echo reachable || echo unreachable', 30, true);
                if (! str_contains($ping->stdout, 'reachable') || str_contains($ping->stdout, 'unreachable')) {
                    return StepResult::fail("Redis is not reachable at {$host}:{$port} on this server; ask support to enable it for the plan", false);
                }
                $has = $wp->run($service, 'plugin is-installed redis-cache', 60, true)->ok();
                if (! $has && ! $step('plugin install redis-cache')) {
                    return StepResult::fail('installing the Redis object cache plugin failed', false, ['log' => $log]);
                }
                $step('plugin activate redis-cache', false);
                $step('config set WP_REDIS_HOST '.Q::arg($host).' --type=constant', true, 60);
                $step('config set WP_REDIS_PORT '.(int) $port.' --type=constant --raw', true, 60);
                $step('config set WP_CACHE_KEY_SALT '.Q::arg((string) $service->spec('domain', $service->hostname).':').' --type=constant', true, 60);
                $step('config set WP_REDIS_PREFIX '.Q::arg(substr(md5($service->id), 0, 8).':').' --type=constant', false, 60);
                if (! $step('redis enable')) {
                    return StepResult::fail('enabling the object cache drop-in failed', false, ['log' => $log]);
                }
                $status = $wp->run($service, 'redis status', 60);
                $log .= "$ wp redis status\n".$status->output()."\n";
                $wp->forget($service);
                if (stripos($status->stdout, 'Connected') === false) {
                    return StepResult::fail('the object cache is enabled but not connected — check the Redis service', false, ['log' => $log]);
                }

                return StepResult::done(['log' => $log, 'enabled' => true]);
            }
        };
    }

    /** A fresh WordPress through WP-CLI: download, a database of the site, wp-config.php, the install itself. */
    private function installStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Instalace WordPressu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $wp = $context->container->make(WordPressService::class);
                if ($wp->installed($service)) {
                    return StepResult::fail('WordPress is already installed in this site', false);
                }
                $tools = $this->capability($context, WebToolsProvider::class);
                $web = $this->capability($context, WebHostingProvider::class);
                $ref = $this->ref($context);
                $credentials = $context->container->make(DatabaseCredentials::class);
                $log = '';
                $databaseId = (string) $context->desired('database_id', '');
                $db = $databaseId !== '' ? $credentials->read($service, $databaseId) : null;
                if ($databaseId !== '' && empty($db['password'])) { // a database made in the panel: the platform never saw its password, the customer supplies it
                    $chosen = collect($web->listDatabases($ref))->first(fn ($c) => (string) $c['remote_id'] === $databaseId);
                    if ($chosen === null) {
                        return StepResult::fail('database_id does not name a database of this site', false);
                    }
                    $password = (string) $context->desired('database_password', '');
                    if ($password === '') {
                        return StepResult::fail("the password of database {$chosen['name']} is not kept by the platform; pass database_password", false);
                    }
                    $db = ['name' => (string) $chosen['name'], 'user' => (string) ($chosen['user'] ?: $chosen['name']), 'password' => $password, 'host' => 'localhost'];
                    $credentials->remember($service, $databaseId, $db);
                    $log .= "database {$db['name']} selected\n";
                }
                if (empty($db['password'])) { // a database of its own within the plan, credentials remembered for the toolkit
                    $existing = $web->listDatabases($ref);
                    $limit = (int) ($context->container->make(ServiceFeatures::class)->features($service)['databases']['limit'] ?? 0);
                    if ($limit > 0 && count($existing) >= $limit) {
                        foreach ($existing as $candidate) { // reuse a database whose credentials the toolkit created
                            $known = $credentials->read($service, (string) $candidate['remote_id']);
                            if (! empty($known['password'])) {
                                $db = $known;
                                $log .= "database {$known['name']} reused\n";
                                break;
                            }
                        }
                        if (empty($db['password'])) {
                            return StepResult::fail("the plan allows {$limit} database(s) and they are all in use; delete one or pass database_id of a database created in the panel", false);
                        }
                    }
                }
                if (empty($db['password'])) {
                    $name = Naming::scoped($service->id, 'wp', 32);
                    $user = Naming::scoped($service->id, 'wp', 16);
                    $password = Str::password(24, symbols: false);
                    $result = $web->createDatabase($ref, ['name' => $name, 'user' => $user, 'password' => $password, 'charset' => 'utf8mb4']);
                    $remoteId = (string) ($result->ref?->remoteId ?? $name);
                    if (! $result->alreadyExisted) {
                        $credentials->remember($service, $remoteId, ['name' => $name, 'user' => $user, 'password' => $password]);
                        $db = ['name' => $name, 'user' => $user, 'password' => $password];
                        $log .= "database {$name} created\n";
                    } else {
                        $db = $credentials->read($service, $remoteId) ?: ['name' => $name, 'user' => $user, 'password' => $password];
                    }
                }
                $steps = [
                    'core download --locale='.Q::arg((string) $context->desired('locale', 'cs_CZ')).' --force',
                    'config create --dbname='.Q::arg((string) $db['name']).' --dbuser='.Q::arg((string) $db['user']).' --dbpass='.Q::arg((string) $db['password']).' --dbhost='.Q::arg((string) ($db['host'] ?? 'localhost')).' --locale='.Q::arg((string) $context->desired('locale', 'cs_CZ')).' --force',
                    'core install --url='.Q::arg((string) $context->desired('url')).' --title='.Q::arg((string) $context->desired('title')).' --admin_user='.Q::arg((string) $context->desired('admin_user', 'admin')).' --admin_password='.Q::arg((string) $context->desired('admin_password')).' --admin_email='.Q::arg((string) $context->desired('admin_email')).' --skip-email',
                ];
                foreach ($steps as $args) {
                    $run = $wp->run($service, $args, 600, true);
                    $log .= '$ wp '.preg_replace('/(--(?:dbpass|admin_password)=)\'[^\']*\'/', '$1***', $args)."\n".$run->output()."\n";
                    if (! $run->ok()) {
                        $wp->forget($service);

                        return StepResult::fail('wp '.explode(' ', $args)[0].' '.explode(' ', $args)[1].' failed: '.mb_substr(trim($run->output()), -300), false, ['log' => $log]);
                    }
                }
                $wp->forget($service);
                $context->container->make(ServiceFeatures::class)->forget($service);

                return StepResult::done(['log' => $log, 'installed' => true, 'admin_url' => rtrim((string) $context->desired('url'), '/').'/wp-admin/', 'admin_user' => (string) $context->desired('admin_user', 'admin'), 'admin_password' => (string) $context->desired('admin_password')]);
            }
        };
    }

    private function pluginStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Plugin WordPressu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $wp = $context->container->make(WordPressService::class);
                if (! $wp->installed($service)) {
                    return StepResult::fail('WordPress is not installed in this site (no wp-config.php)', false);
                }
                $op = (string) $context->desired('op');
                $slug = (string) $context->desired('slug');
                $run = $wp->run($service, 'plugin '.$op.' '.Q::arg($slug), 600);
                $log = "$ wp plugin {$op} {$slug}\n".$run->output()."\n";
                $wp->forget($service);
                if (! $run->ok()) {
                    return StepResult::fail("wp plugin {$op} {$slug} failed: ".mb_substr(trim($run->output()), -300), false, ['log' => $log]);
                }

                return StepResult::done(['log' => $log, 'plugin' => $slug, 'op' => $op]);
            }
        };
    }
}
