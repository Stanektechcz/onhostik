<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Str;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\Q;
use Throwable;

/**
 * Staging copy of a site: a second site (own service, same plan, same server, no invoice) under
 * `<site>-staging.<suffix>`, files and databases copied from production, WordPress URLs rewritten. Refresh copies
 * production → staging again; push copies staging → production after a safety backup. Both sites keep their own
 * wp-config.php after the first copy, so credentials never cross over.
 */
final class StagingService
{
    private const SYNC_EXCLUDES = ['.onhost', '.user.ini', '.well-known', '.onhost-sync.tar.gz', 'wp-content/cache', 'wp-content/uploads/cache'];

    public function __construct(private readonly ServiceFeatures $features, private readonly ServiceService $services, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /** @return array<string,mixed> */
    public function status(Service $service): array
    {
        $link = $this->link($service);
        $role = $link === null ? null : ($link->service_id === $service->id ? 'production' : 'staging');
        $staging = $link ? Service::query()->find($link->staging_service_id) : null;
        $production = $link ? Service::query()->find($link->service_id) : null;
        $features = $this->features->features($service);

        return [
            'available' => ! empty($features['staging']['enabled']),
            'role' => $role,
            'state' => $link === null ? 'none' : ($staging === null ? 'deleted' : ($staging->state === ServiceStateMachine::ACTIVE ? $link->state : ($staging->state === ServiceStateMachine::FAILED ? 'failed' : 'provisioning'))),
            'staging' => $staging === null ? null : ['service_id' => $staging->id, 'domain' => $link->staging_domain, 'url' => 'https://'.$link->staging_domain.'/', 'service_state' => $staging->state],
            'production' => $production === null ? null : ['service_id' => $production->id, 'domain' => (string) $production->spec('domain', $production->hostname)],
            'databases' => array_values((array) ($link?->databases ?? [])),
            'last_synced_at' => $link?->last_synced_at?->toIso8601String(),
            'last_pushed_at' => $link?->last_pushed_at?->toIso8601String(),
            'suffix' => (string) config('onhost.web_tools.staging_suffix', 'web.onhost.cz'),
            'proposed_domain' => $link === null ? $this->stagingDomain($service) : null,
        ];
    }

    public function link(Service $service): ?StagingLink
    {
        return StagingLink::query()->where('service_id', $service->id)->orWhere('staging_service_id', $service->id)->first();
    }

    public function production(Service $service): Service
    {
        $link = $this->link($service);
        if ($link !== null && $link->staging_service_id === $service->id) {
            return Service::query()->findOrFail($link->service_id);
        }

        return $service;
    }

    public function stagingDomain(Service $production): string
    {
        $suffix = (string) config('onhost.web_tools.staging_suffix', 'web.onhost.cz');
        $base = substr(Str::slug(str_replace('.', '-', (string) $production->spec('domain', $production->hostname))), 0, 40) ?: 'site';
        $domain = $base.'-staging.'.$suffix;
        if (Service::query()->where('hostname', $domain)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->exists()) {
            $domain = $base.'-'.strtolower(substr($production->id, -4)).'-staging.'.$suffix;
        }

        return $domain;
    }

    /** Create the staging site as a service of its own on the production server and start provisioning it. */
    public function createStaging(Service $production, CommandContext $context): StagingLink
    {
        if (empty($this->features->features($production)['staging']['enabled'])) {
            throw new DomainError('feature_unavailable', 'Staging is not part of this plan.', 422);
        }
        $existing = $this->link($production);
        if ($existing !== null) {
            if ($existing->staging_service_id === $production->id) {
                throw new DomainError('staging_of_staging', 'A staging site cannot have its own staging.', 422);
            }
            if (Service::query()->whereKey($existing->staging_service_id)->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])->exists()) {
                return $existing;
            }
            $existing->delete();
        }
        $domain = $this->stagingDomain($production);
        $desired = array_merge((array) $production->desired_spec, [
            'domain' => $domain, 'aliases' => [], 'staging_of' => $production->id, 'placement' => ['instance_id' => $production->provider_instance_id, 'node_id' => $production->node_id],
        ]);
        unset($desired['service_id']);
        $staging = Service::query()->create([
            'organization_id' => $production->organization_id, 'project_id' => $production->project_id, 'product_key' => $production->product_key, 'plan_version_id' => $production->plan_version_id, 'family' => $production->family,
            'name' => 'Staging · '.$production->name, 'label' => 'staging', 'state' => ServiceStateMachine::PAID, 'region_code' => $production->region_code, 'entitlements' => array_replace((array) $production->entitlements, ['staging' => false, 'backups' => false]),
            'sla_class' => $production->sla_class, 'tags' => ['parent_service_id' => $production->id, 'staging_of' => $production->id, 'billing' => 'included'], 'desired_spec' => $desired, 'hostname' => $domain,
            'node_id' => $production->node_id, 'provider_instance_id' => $production->provider_instance_id,
        ]);
        $link = StagingLink::query()->create(['service_id' => $production->id, 'staging_service_id' => $staging->id, 'organization_id' => $production->organization_id, 'staging_domain' => $domain, 'state' => 'provisioning', 'databases' => [], 'meta' => []]);
        $this->services->startProvisioning($staging, $context);
        $this->audit->record($context->withScope($production->organization_id), 'service.staging.create', 'succeeded', ['staging_service_id' => $staging->id, 'domain' => $domain], 'service', $production->id);
        $this->outbox->publish(GenericEvent::of('staging.created', 'service', $production->id, ['staging_service_id' => $staging->id, 'domain' => $domain], $production->organization_id));

        return $link;
    }

    /**
     * Copy files (and databases) from one side to the other and rewrite WordPress for the target domain.
     *
     * @return array{log:string, databases:array<string,array<string,string>>}
     */
    public function sync(Service $from, Service $to, StagingLink $link, bool $databases, CommandContext $context): array
    {
        [$fromTools, $fromRef] = $this->features->toolsFor($from);
        [$toTools, $toRef] = $this->features->toolsFor($to);
        foreach ([[$fromTools, $fromRef], [$toTools, $toRef]] as [$tools, $ref]) { // jailed panels: the copy's shell account arrives through the job queue
            if (! $tools->shellAvailable($ref) && $tools->ensureAgent($ref)->isAsync()) {
                throw new ProviderException('staging', ProviderErrorCode::TRANSIENT, 'The shell account of the site is still being prepared on the node; the copy continues in a minute', null, [], 60);
            }
        }
        $log = $this->syncFiles($from, $fromTools, $fromRef, $to, $toTools, $toRef);
        $map = (array) ($link->databases ?? []);
        if ($databases) {
            [$dbLog, $map] = $this->syncDatabases($from, $fromTools, $fromRef, $to, $toTools, $toRef, $link, $context);
            $log .= $dbLog;
        }
        $log .= $this->rewriteWordPress($from, $to, $toTools, $toRef, $map, $link);

        return ['log' => $log, 'databases' => $map];
    }

    /** Terminate the staging service and forget the link. */
    public function deleteStaging(Service $production, CommandContext $context): void
    {
        $link = $this->link($production);
        if ($link === null) {
            return;
        }
        $staging = Service::query()->find($link->staging_service_id);
        if ($staging !== null && $staging->state === ServiceStateMachine::FAILED && empty($staging->binding['remote_id'])) {
            $staging->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save(); // provisioning never reached the node: nothing to remove there
        } elseif ($staging !== null && ! in_array($staging->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true)) {
            $this->services->requestAction($staging, 'terminate', CommandContext::system('staging removed by '.$context->actorType.':'.$context->actorId), 'staging:terminate:'.$staging->id.':'.now()->format('YmdHis'), ['reason' => 'staging deleted']);
        }
        $link->forceFill(['state' => 'deleted'])->save();
        $link->delete();
        $this->audit->record($context->withScope($production->organization_id), 'service.staging.delete', 'succeeded', ['staging_service_id' => $link->staging_service_id], 'service', $production->id);
        $this->outbox->publish(GenericEvent::of('staging.deleted', 'service', $production->id, ['staging_service_id' => $link->staging_service_id], $production->organization_id));
    }

    // ── files ────────────────────────────────────────────────────────────────────────────────────────────

    private function syncFiles(Service $from, WebToolsProvider $fromTools, ResourceRef $fromRef, Service $to, WebToolsProvider $toTools, ResourceRef $toRef): string
    {
        $fromRoot = rtrim($fromTools->transport($fromRef)->root(), '/');
        $toRoot = rtrim($toTools->transport($toRef)->root(), '/');
        $keepConfig = $toTools->transport($toRef)->exists('wp-config.php');
        $excludes = self::SYNC_EXCLUDES;
        if ($keepConfig) {
            $excludes[] = 'wp-config.php';
        }
        $sameNode = $from->provider_instance_id !== null && $from->provider_instance_id === $to->provider_instance_id && (string) data_get($from->desired_spec, 'executor') === 'aapanel';
        if ($sameNode) {
            $cmd = 'rsync -a --delete '.implode(' ', array_map(fn ($e) => '--exclude='.Q::arg($e), $excludes)).' '.Q::arg($fromRoot.'/').' '.Q::arg($toRoot.'/').' && find '.Q::arg($toRoot).' -name .user.ini -prune -o -exec chown '.Q::arg($toTools->siteUser($toRef).':'.$toTools->siteUser($toRef)).' {} +'; // the panel keeps .user.ini immutable
            $run = $fromTools->shell($fromRef)->run($cmd, ['timeout' => 900]);
            if (! $run->ok()) {
                throw new DomainError('staging_sync_failed', 'File copy failed: '.mb_substr($run->output(), 0, 300), 502);
            }

            return "files: rsync {$fromRoot} → {$toRoot}".($keepConfig ? ' (wp-config.php kept)' : '')."\n";
        }
        // Different servers or jailed accounts: pack on the source, relay through the control plane, unpack on the target.
        $archive = '.onhost-sync.tar.gz';
        $pack = $fromTools->shell($fromRef)->run('cd '.Q::arg($fromRoot).' && tar czf '.Q::arg($fromRoot.'/'.$archive).' '.implode(' ', array_map(fn ($e) => '--exclude=./'.Q::arg($e), $excludes)).' .', ['timeout' => 900, 'user' => $fromTools->siteUser($fromRef)]);
        if (! $pack->ok() && $pack->exitCode !== 1) { // exit 1 = "file changed as we read it" on a live site: the archive is still complete
            throw new DomainError('staging_sync_failed', 'Packing the site failed: '.mb_substr($pack->output(), 0, 300), 502);
        }
        $local = tempnam(sys_get_temp_dir(), 'ohsync');
        $size = 0;
        try {
            $fromTools->transport($fromRef)->download($archive, $local);
            $fromTools->transport($fromRef)->delete($archive, false);
            $size = (int) @filesize($local);
            $toTools->transport($toRef)->upload($archive, $local);
            $unpack = $toTools->shell($toRef)->run('cd '.Q::arg($toRoot).' && tar xzf '.Q::arg($archive).' && rm -f '.Q::arg($archive), ['timeout' => 900, 'user' => $toTools->siteUser($toRef)]);
            if (! $unpack->ok()) {
                throw new DomainError('staging_sync_failed', 'Unpacking on the target failed: '.mb_substr($unpack->output(), 0, 300), 502);
            }
        } finally {
            @unlink($local);
        }

        return "files: archived and copied {$fromRoot} → {$toRoot} (".number_format($size / 1048576, 1).' MB)'.($keepConfig ? ' (wp-config.php kept)' : '')."\n";
    }

    // ── databases ────────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:array<string,array<string,string>>} */
    private function syncDatabases(Service $from, WebToolsProvider $fromTools, ResourceRef $fromRef, Service $to, WebToolsProvider $toTools, ResourceRef $toRef, StagingLink $link, CommandContext $context): array
    {
        $log = '';
        $map = (array) ($link->databases ?? []);
        $fromIsProduction = $link->service_id === $from->id;
        $fromAdapter = $this->features->adapterFor($from);
        $toAdapter = $this->features->adapterFor($to);
        if (! $fromAdapter instanceof WebHostingProvider || ! $toAdapter instanceof WebHostingProvider) {
            return ["databases: not available on this panel\n", $map];
        }
        $sources = $fromAdapter->listDatabases($fromRef);
        foreach ($sources as $db) {
            $sourceId = (string) $db['remote_id'];
            $entry = $fromIsProduction ? ($map[$sourceId] ?? null) : collect($map)->first(fn ($m) => ($m['target_remote_id'] ?? null) === $sourceId);
            if ($entry === null) {
                if (! $fromIsProduction) {
                    $log .= "database {$db['name']}: only on staging, skipped\n";

                    continue;
                }
                $entry = $this->createCounterpart($to, $toAdapter, $toRef, (string) $db['name'], $context);
                $entry['source_remote_id'] = $sourceId;
                $entry['source_name'] = (string) $db['name'];
                $map[$sourceId] = $entry;
                $log .= "database {$db['name']}: created {$entry['target_name']} on staging\n";
            }
            $targetId = $fromIsProduction ? (string) $entry['target_remote_id'] : (string) $entry['source_remote_id'];
            $targetName = $fromIsProduction ? (string) $entry['target_name'] : (string) $entry['source_name'];
            $local = tempnam(sys_get_temp_dir(), 'ohsql');
            try {
                $fromTools->exportDatabase($fromRef, $sourceId, $local, $this->credentials($from, $fromTools, $fromRef, $sourceId, (string) $db['name']));
                $size = (int) @filesize($local);
                $toTools->importDatabase($toRef, $targetId, $local, $this->credentials($to, $toTools, $toRef, $targetId, $targetName));
                $log .= "database {$db['name']} → {$targetName}: ".number_format($size / 1048576, 1)." MB copied\n";
            } catch (ProviderException $e) {
                if ($e->errorCode !== ProviderErrorCode::VALIDATION) {
                    throw $e;
                }
                // a database made in the panel whose password the platform never saw: files still copy, the customer sets a password and refreshes
                $log .= "database {$db['name']}: skipped — ".$e->getMessage()."\n";
            } finally {
                @unlink($local);
            }
        }
        $link->forceFill(['databases' => $map])->save();

        return [$log, $map];
    }

    /** @return array{target_remote_id:string,target_name:string,target_user:string} */
    private function createCounterpart(Service $to, WebHostingProvider $adapter, ResourceRef $toRef, string $sourceName, CommandContext $context): array
    {
        $suffix = preg_replace('/^oh[a-z0-9]{6}_/', '', $sourceName) ?: 'db';
        $name = Naming::scoped($to->id, (string) $suffix, 32);
        $user = Naming::scoped($to->id, substr((string) $suffix, 0, 8), 16);
        $password = Str::password(24, symbols: false);
        $result = $adapter->createDatabase($toRef, ['name' => $name, 'user' => $user, 'password' => $password, 'charset' => 'utf8mb4']);
        $remoteId = (string) ($result->ref?->remoteId ?? $name);
        app(DatabaseCredentials::class)->remember($to, $remoteId, ['name' => $name, 'user' => $user, 'password' => $password]);

        return ['target_remote_id' => $remoteId, 'target_name' => $name, 'target_user' => $user];
    }

    /** @return array<string,string> name/user/password when we know them (needed on ISPConfig; aaPanel exports through its API) */
    private function credentials(Service $service, WebToolsProvider $tools, ResourceRef $ref, string $remoteId, string $name): array
    {
        $stored = app(DatabaseCredentials::class)->read($service, $remoteId);
        if (! empty($stored['password'])) {
            return $stored;
        }
        try {
            $config = $tools->transport($ref)->read('wp-config.php', 65536);
        } catch (Throwable) {
            return [];
        }
        $get = fn (string $key) => preg_match("/define\(\s*['\"]{$key}['\"]\s*,\s*['\"]([^'\"]*)['\"]/", $config, $m) ? $m[1] : null;
        if ($get('DB_NAME') === $name && $get('DB_PASSWORD') !== null) {
            return ['name' => $name, 'user' => (string) $get('DB_USER'), 'password' => (string) $get('DB_PASSWORD'), 'host' => (string) ($get('DB_HOST') ?? 'localhost')];
        }

        return [];
    }

    // ── WordPress ────────────────────────────────────────────────────────────────────────────────────────

    private function rewriteWordPress(Service $from, Service $to, WebToolsProvider $toTools, ResourceRef $toRef, array $map, StagingLink $link): string
    {
        $transport = $toTools->transport($toRef);
        if (! $transport->exists('wp-config.php')) {
            return '';
        }
        $toIsStaging = $link->staging_service_id === $to->id;
        $fromDomain = (string) $from->spec('domain', $from->hostname);
        $toDomain = (string) $to->spec('domain', $to->hostname);
        $wp = $toTools->wpCommand($toRef).' --path='.Q::arg($transport->root()).' --skip-plugins --skip-themes';
        $run = fn (string $args, int $t = 300) => $toTools->shell($toRef)->run($wp.' '.$args, ['timeout' => $t, 'user' => $toTools->siteUser($toRef)]);
        $log = '';
        // the copied wp-config.php (first sync only) still names the other side's database
        $config = $transport->read('wp-config.php', 65536);
        $currentDb = preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"]/", $config, $m) ? $m[1] : '';
        $target = collect($map)->first(fn ($e) => $toIsStaging ? ($e['source_name'] ?? null) === $currentDb : ($e['target_name'] ?? null) === $currentDb);
        if ($target !== null) {
            $remoteId = $toIsStaging ? (string) $target['target_remote_id'] : (string) $target['source_remote_id'];
            $creds = app(DatabaseCredentials::class)->read($to, $remoteId);
            if (! empty($creds['password'])) {
                foreach (['DB_NAME' => $creds['name'], 'DB_USER' => $creds['user'], 'DB_PASSWORD' => $creds['password']] as $k => $v) {
                    $run('config set '.$k.' '.Q::arg((string) $v).' --type=constant --quiet');
                }
                $log .= "wordpress: wp-config.php now points at {$creds['name']}\n";
            }
        }
        foreach (['https://', 'http://'] as $scheme) {
            $r = $run('search-replace '.Q::arg($scheme.$fromDomain).' '.Q::arg($scheme.$toDomain).' --all-tables --skip-columns=guid --report-changed-only --format=count', 600);
            $log .= 'wordpress: '.$scheme.$fromDomain.' → '.$scheme.$toDomain.': '.trim($r->stdout ?: '0').' replacements'."\n";
        }
        $run('option update home '.Q::arg('https://'.$toDomain).' --quiet');
        $run('option update siteurl '.Q::arg('https://'.$toDomain).' --quiet');
        if ($toIsStaging) {
            $run('option update blog_public 0 --quiet'); // keep search engines out of the staging copy
        }
        $run('cache flush --quiet');

        return $log;
    }
}
