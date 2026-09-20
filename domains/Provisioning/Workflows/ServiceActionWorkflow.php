<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\AvailabilityWatch;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\RestoreJob;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Domain\Services\Web\CommandRunner;
use Onhost\Domain\Services\Web\DatabaseCredentials;
use Onhost\Domain\Services\Web\WebFileStore;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Files\FileStore;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\MailToolsProvider;
use Onhost\Providers\Contracts\PowerCapable;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\Q;
use Throwable;

/**
 * Day-2 operations on an existing service (blueprint §5.3): power, suspend/resume,
 * resize, terminate, backup, restore, snapshot. `desired.action` selects the path;
 * every path is idempotent (read-before-act) and ends by reading the actual state back.
 */
final class ServiceActionWorkflow implements Workflow
{
    public const CORE_ACTIONS = ['power', 'suspend', 'resume', 'resize', 'terminate', 'purge', 'backup', 'restore', 'archive.restore', 'snapshot', 'rollback_snapshot'];

    /** Feature actions: one provider call each, validated by ServiceService::featureParams, no service state change. */
    public const FEATURE_ACTIONS = [
        'php.set', 'database.create', 'database.delete', 'ftp.create', 'ftp.delete', 'ftp.password', 'cron.create', 'cron.delete', 'subdomain.add', 'subdomain.remove',
        'redirect.set', 'ssl.issue', 'https.force', 'snapshot.delete', 'firewall.apply', 'command.send', 'schedule.create', 'mailbox.create', 'mailbox.update', 'mailbox.delete',
        'alias.create', 'alias.delete', 'sending.set',
        'errpages.set', 'directives.set', 'folder.protect', 'folder.unprotect', 'dbuser.create', 'dbuser.password', 'dbuser.delete', 'shell.create', 'shell.key', 'shell.delete', 'stats.set', 'ssl.upload',
        'file.mkdir', 'file.delete', 'file.save', 'app.install',
        // tools on top of the panel (WebToolsProvider) and mail tools (MailToolsProvider): still one provider call each
        'command.run', 'php.settings', 'security.set', 'http3.set', 'cron.update', 'cron.run', 'database.export', 'database.import', 'database.access', 'backup.delete',
        'file.rename', 'file.copy', 'file.chmod', 'file.archive', 'file.extract', 'node.create', 'node.action', 'proxy.create', 'proxy.delete', 'proxies.set', 'index.set',
        'forward.create', 'forward.delete', 'catchall.set', 'autoresponder.set', 'spam.policy', 'spam.list.add', 'spam.list.delete', 'filter.create', 'filter.delete', 'list.create', 'list.delete',
        'fetchmail.create', 'fetchmail.delete', 'mailbox.backup', 'mailbox.restore',
        // game tools (GameToolsProvider): startup, settings, schedules, databases, collaborators, files, ports, backups, panel account
        ...ServiceFeatures::GAME_ACTIONS,
    ];

    /** Actions that run as sagas of their own (ServiceService::actionWorkflowFor); listed here so the API validates them alike. */
    public const PLATFORM_ACTIONS = ['staging.create', 'staging.refresh', 'staging.push', 'staging.delete', 'deploy.run', 'deploy.rollback', 'wp.install', 'wp.update', 'wp.cache', 'wp.plugin', 'import.run', 'cdn.enable', 'cdn.disable', 'cdn.purge', 'ssl.wildcard'];

    public const ACTIONS = [...self::CORE_ACTIONS, ...self::FEATURE_ACTIONS, ...self::PLATFORM_ACTIONS];

    public static function kind(): string
    {
        return 'service.action';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance?->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        $action = (string) data_get($operation->desired, 'action');

        return match ($action) {
            'power' => [$this->powerStep(), $this->verifyPowerStep()],
            'suspend' => [$this->suspendStep(), $this->finishStateStep(ServiceStateMachine::SUSPENDED)],
            'resume' => [$this->resumeStep(), $this->finishStateStep(ServiceStateMachine::ACTIVE)],
            'resize' => [$this->resizeStep(), $this->finishResizeStep()],
            'terminate' => [$this->identityStep(), $this->finalArchiveStep(), $this->deactivateStep(), $this->revokeDelegationsStep(), $this->scheduleRemovalStep()],
            'purge' => [$this->identityStep(), $this->finalArchiveStep(anyOperation: true), $this->terminateStep(), $this->platformDnsStep(), $this->releaseStep()],
            'backup' => [$this->backupStep()],
            'restore' => [$this->restoreStep()],
            'archive.restore' => [$this->archiveRestoreStep()],
            'snapshot' => [$this->snapshotStep()],
            'rollback_snapshot' => [$this->rollbackSnapshotStep()],
            default => in_array($action, self::FEATURE_ACTIONS, true) ? [$this->featureStep($action)] : throw new \InvalidArgumentException("Unknown service action {$action}"),
        };
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null) {
            return;
        }
        $action = (string) $context->desired('action');
        $services = $context->container->make(ServiceService::class);
        $reason = (string) ($context->operation->error['message'] ?? "{$action} failed");
        match ($action) {
            'resize' => $services->settleTransient($service, ServiceStateMachine::ACTIVE, $context->actor, "resize failed: {$reason}", $context->operation),
            'suspend' => $services->settleTransient($service, ServiceStateMachine::ACTIVE, $context->actor, "suspend failed: {$reason}", $context->operation),
            'resume' => $services->settleTransient($service, ServiceStateMachine::SUSPENDED, $context->actor, "resume failed: {$reason}", $context->operation),
            'terminate' => $services->settleTransient($service, $service->state === ServiceStateMachine::SUSPENDING ? ServiceStateMachine::SUSPENDED : ServiceStateMachine::FAILED, $context->actor, "terminate failed: {$reason}", $context->operation),
            'purge' => $services->settleTransient($service, ServiceStateMachine::FAILED, $context->actor, "purge failed: {$reason}", $context->operation),
            'restore' => RestoreJob::query()->where('operation_id', $context->operation->id)->update(['state' => 'failed', 'finished_at' => now(), 'result' => ['error' => $reason]]),
            'backup' => Backup::query()->where('operation_id', $context->operation->id)->update(['state' => 'failed', 'finished_at' => now()]),
            default => null,
        };
    }

    private function powerStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Napájení';
            }

            public function run(StepContext $context): StepResult
            {
                $action = (string) $context->desired('power_action');
                $infra = $this->capability($context, InfrastructureProvider::class);
                $ref = $this->ref($context);
                $state = $infra->getActualState($ref);
                $target = in_array($action, ['start', 'reboot', 'reset'], true) ? 'running' : 'stopped';
                if (in_array($action, ['start', 'stop', 'shutdown', 'kill'], true) && $state->status === $target) {
                    return StepResult::done(['noop' => true, 'status' => $state->status]);
                }

                return $this->settle($this->capability($context, PowerCapable::class)->power($ref, $action), ['target' => $target]);
            }
        };
    }

    /**
     * One feature action = one provider call on the service's primary resource (site, VM, game server, mail domain).
     * Asynchronous executors (ISPConfig job queue, Proxmox tasks) are awaited through the handle; resource listings
     * cached for the customer panel are dropped so the next read shows the change.
     */
    private function featureStep(string $action): ServiceStep
    {
        return new class($action) extends ServiceStep
        {
            public function __construct(private readonly string $action) {}

            public function label(): string
            {
                return match (true) {
                    str_starts_with($this->action, 'database') => 'Databáze', str_starts_with($this->action, 'ftp') => 'FTP účet', str_starts_with($this->action, 'cron') => 'Cron',
                    str_starts_with($this->action, 'subdomain') => 'Doména webu', str_starts_with($this->action, 'mailbox') || str_starts_with($this->action, 'alias') || $this->action === 'sending.set' => 'E-mail',
                    $this->action === 'php.set' => 'Verze PHP', $this->action === 'ssl.issue' => 'Certifikát', $this->action === 'https.force' => 'HTTPS', $this->action === 'redirect.set' => 'Přesměrování',
                    $this->action === 'snapshot.delete' => 'Snapshot', $this->action === 'firewall.apply' => 'Firewall', $this->action === 'command.send' => 'Příkaz konzole', $this->action === 'schedule.create' => 'Plánovaná úloha',
                    $this->action === 'errpages.set' => 'Chybové stránky', $this->action === 'directives.set' => 'Direktivy webserveru', str_starts_with($this->action, 'folder.') => 'Chráněná složka', str_starts_with($this->action, 'dbuser.') => 'Uživatel databáze',
                    str_starts_with($this->action, 'shell.') => 'Shell přístup', $this->action === 'stats.set' => 'Statistiky', $this->action === 'ssl.upload' => 'Vlastní certifikát', str_starts_with($this->action, 'file.') => 'Soubory', $this->action === 'app.install' => 'Instalace aplikace',
                    $this->action === 'command.run' => 'Příkaz v terminálu', $this->action === 'php.settings' => 'Nastavení PHP', $this->action === 'security.set' => 'Bezpečnostní pravidla', $this->action === 'http3.set' => 'HTTP/3',
                    str_starts_with($this->action, 'node.') => 'Node projekt', str_starts_with($this->action, 'proxy.'), $this->action === 'proxies.set' => 'Reverzní proxy', $this->action === 'index.set' => 'Výchozí soubory', $this->action === 'backup.delete' => 'Záloha',
                    $this->action === 'variable.set', $this->action === 'image.set' => 'Startup serveru', $this->action === 'rename' => 'Název serveru', $this->action === 'reinstall' => 'Reinstalace serveru', str_starts_with($this->action, 'schedule.') => 'Plánovaná úloha',
                    str_starts_with($this->action, 'gamedb.') => 'Databáze serveru', str_starts_with($this->action, 'subuser.') => 'Spolupracovník', str_starts_with($this->action, 'gfile.') => 'Soubory serveru', str_starts_with($this->action, 'allocation.') => 'Porty serveru',
                    str_starts_with($this->action, 'gbackup.') => 'Záloha serveru', $this->action === 'panel.password' => 'Přístup do herního panelu',
                    str_starts_with($this->action, 'forward.') || str_starts_with($this->action, 'catchall.') || str_starts_with($this->action, 'autoresponder.') || str_starts_with($this->action, 'spam.') || str_starts_with($this->action, 'filter.') || str_starts_with($this->action, 'list.') || str_starts_with($this->action, 'fetchmail.') => 'E-mail',
                    default => 'Nastavení služby',
                };
            }

            public function run(StepContext $context): StepResult
            {
                $ref = $this->ref($context);
                $p = fn (string $k, mixed $d = null) => $context->desired($k, $d);
                $owed = (array) $p('_limit', []); // the plan's limit could not be counted when the request came in: the panel was away (H02)
                if (isset($owed['kind'], $owed['limit']) && count($context->container->make(ServiceFeatures::class)->resources($this->service($context), (string) $owed['kind'], true)) >= (int) $owed['limit']) {
                    return StepResult::fail("{$this->action}: the plan allows {$owed['limit']} of these.", false, ['feature_limit_reached' => true, 'limit' => (int) $owed['limit']]);
                }
                $result = match ($this->action) {
                    'php.set' => $this->capability($context, WebHostingProvider::class)->setPhpVersion($ref, (string) $p('version')),
                    'database.create' => $this->capability($context, WebHostingProvider::class)->createDatabase($ref, ['name' => $p('name'), 'user' => $p('user'), 'password' => $p('password'), 'charset' => $p('charset', 'utf8mb4')]),
                    'database.delete' => $this->capability($context, WebHostingProvider::class)->deleteDatabase($ref, (string) $p('remote_id')),
                    'ftp.create' => $this->capability($context, WebHostingProvider::class)->createFtpAccount($ref, array_filter(['user' => $p('user'), 'password' => $p('password'), 'path' => $p('path')], fn ($v) => $v !== null && $v !== '')),
                    'ftp.delete' => $this->capability($context, WebHostingProvider::class)->deleteFtpAccount($ref, (string) $p('remote_id')),
                    'ftp.password' => $this->capability($context, WebHostingProvider::class)->setFtpPassword($ref, (string) $p('remote_id'), (string) $p('password')),
                    'cron.create' => $this->capability($context, WebHostingProvider::class)->createCron($ref, array_filter(['schedule' => $p('schedule'), 'command' => $p('command'), 'label' => $p('label')], fn ($v) => $v !== null)),
                    'cron.delete' => $this->capability($context, WebHostingProvider::class)->deleteCron($ref, (string) $p('remote_id')),
                    'subdomain.add' => $this->capability($context, WebHostingProvider::class)->addSubdomain($ref, array_filter(['domain' => $p('domain'), 'path' => $p('path')], fn ($v) => $v !== null && $v !== '')),
                    'subdomain.remove' => $this->capability($context, WebHostingProvider::class)->removeSubdomain($ref, (string) $p('remote_id')),
                    'redirect.set' => $this->capability($context, WebHostingProvider::class)->setRedirect($ref, ['target' => (string) $p('target', ''), 'type' => (string) $p('type', '301')]),
                    'ssl.issue' => (function () use ($context, $ref, $p) {
                        $result = $this->capability($context, WebHostingProvider::class)->issueCertificate($ref, (array) $p('domains', []) ?: array_values(array_filter([$ref->meta['name'] ?? null, $this->service($context)->spec('domain')])));
                        $service = $this->service($context);
                        $service->forceFill(['tags' => array_replace_recursive((array) $service->tags, ['access' => ['certificate' => $result->isAsync() ? 'requested' : 'issued']])])->save(); // the panel and the auto-issuer read this
                        $context->container->make(ServiceFeatures::class)->forget($service);

                        return $result;
                    })(),
                    'https.force' => $this->capability($context, WebHostingProvider::class)->forceHttps($ref, (bool) $p('enabled', true)),
                    'snapshot.delete' => $this->capability($context, ComputeProvider::class)->deleteSnapshot($ref, (string) $p('name')),
                    'firewall.apply' => (function () use ($context, $ref, $p) {
                        $result = $this->capability($context, ComputeProvider::class)->applyFirewall($ref, (array) $p('rules', []), (bool) $p('enabled', true));
                        $service = $this->service($context);
                        $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['firewall' => ['rules' => (array) $p('rules', []), 'enabled' => (bool) $p('enabled', true)]])])->save();

                        return $result;
                    })(),
                    'command.send' => $this->capability($context, GameProvider::class)->sendCommand($ref, (string) $p('command')),
                    'schedule.create' => $this->capability($context, GameProvider::class)->createSchedule($ref, ['name' => $p('name'), 'cron' => $p('cron'), 'actions' => (array) $p('actions', [])]),
                    'mailbox.create' => $this->capability($context, MailProvider::class)->createMailbox($ref, ['address' => $p('address'), 'password' => $p('password'), 'name' => $p('name'), 'quota_mb' => $p('quota_mb', 2048)]),
                    'mailbox.update' => $this->capability($context, MailProvider::class)->updateMailbox(new ResourceRef('mailbox', (string) $p('remote_id'), $ref->node, ['client_id' => $ref->meta['client_id'] ?? null], $ref->serviceId), (array) $p('changes', [])),
                    'mailbox.delete' => $this->capability($context, MailProvider::class)->deleteMailbox(new ResourceRef('mailbox', (string) $p('remote_id'), $ref->node, [], $ref->serviceId)),
                    'alias.create' => $this->capability($context, MailProvider::class)->createAlias($ref, ['source' => $p('source'), 'destination' => $p('destination')]),
                    'alias.delete' => $this->capability($context, MailProvider::class)->deleteAlias(new ResourceRef('mail_alias', (string) $p('remote_id'), $ref->node, [], $ref->serviceId)),
                    'sending.set' => $this->capability($context, MailProvider::class)->setSendingEnabled($ref, (bool) $p('enabled', true)),
                    'errpages.set' => $this->capability($context, WebHostingProvider::class)->setErrorDocs($ref, (bool) $p('enabled', true)),
                    'directives.set' => $this->capability($context, WebHostingProvider::class)->setDirectives($ref, (string) $p('kind'), (string) $p('content', '')),
                    'folder.protect' => $this->capability($context, WebHostingProvider::class)->protectFolder($ref, ['path' => (string) $p('path', '/'), 'user' => (string) $p('user'), 'password' => (string) $p('password')]),
                    'folder.unprotect' => $this->capability($context, WebHostingProvider::class)->unprotectFolder($ref, (string) $p('remote_id')),
                    'dbuser.create' => $this->capability($context, WebHostingProvider::class)->createDbUser($ref, ['user' => (string) $p('user'), 'password' => (string) $p('password')]),
                    'dbuser.password' => $this->capability($context, WebHostingProvider::class)->setDbUserPassword($ref, (string) $p('remote_id'), (string) $p('password')),
                    'dbuser.delete' => $this->capability($context, WebHostingProvider::class)->deleteDbUser($ref, (string) $p('remote_id')),
                    'shell.create' => $this->capability($context, WebHostingProvider::class)->createShellUser($ref, ['user' => (string) $p('user'), 'password' => (string) $p('password'), 'ssh_key' => $p('ssh_key')]),
                    'shell.key' => $this->capability($context, WebHostingProvider::class)->setShellKey($ref, (string) $p('remote_id'), (string) $p('ssh_key', '')),
                    'shell.delete' => $this->capability($context, WebHostingProvider::class)->deleteShellUser($ref, (string) $p('remote_id')),
                    'stats.set' => $this->capability($context, WebHostingProvider::class)->setStats($ref, ['type' => (string) $p('type', 'awstats'), 'password' => $p('password')]),
                    'ssl.upload' => $this->capability($context, WebHostingProvider::class)->uploadCertificate($ref, ['cert' => (string) $p('cert'), 'key' => (string) $p('key'), 'chain' => $p('chain')]),
                    'file.mkdir' => $this->capability($context, WebHostingProvider::class)->createDirectory($ref, (string) $p('path')),
                    'file.delete' => $this->capability($context, WebHostingProvider::class)->deleteFile($ref, (string) $p('path'), (bool) $p('directory', false)),
                    'file.save' => $this->capability($context, WebHostingProvider::class)->writeFile($ref, (string) $p('path'), (string) $p('content', '')),
                    'app.install' => $this->capability($context, WebHostingProvider::class)->installApp($ref, ['name' => (string) $p('name'), 'php_version' => $p('php_version')]),
                    // ── tools on top of the panel (WebToolsProvider) ──────────────────────────────────────────
                    'command.run' => (function () use ($context, $ref, $p) {
                        $tools = $this->capability($context, WebToolsProvider::class);
                        if (! $tools->shellAvailable($ref)) { // first use on a jailed panel: create the agent user, the customer re-runs the command
                            $agent = $tools->ensureAgent($ref);
                            if ($agent->isAsync()) {
                                return $agent;
                            }
                        }
                        $cwd = rtrim($tools->documentRoot($ref).'/'.trim((string) $p('cwd', ''), '/'), '/');
                        $command = CommandRunner::guard((string) $p('command'));
                        $phpDir = dirname((string) ($tools->toolPaths($ref)['php'] ?? ''));
                        if ($phpDir !== '' && $phpDir !== '.' && $phpDir !== '/') { // the site's PHP first on PATH (aaPanel keeps every version under /www/server/php)
                            $command = 'export PATH='.Q::arg($phpDir).':"$PATH"; '.$command;
                        }
                        $run = $tools->shell($ref)->run($command, ['cwd' => $cwd, 'user' => $tools->siteUser($ref), 'timeout' => (int) $p('timeout', 120)]);

                        return ProviderResult::completed($ref, ['exit_code' => $run->exitCode, 'output' => mb_substr($run->output(), 0, 65536), 'duration_ms' => $run->durationMs, 'timed_out' => $run->timedOut]);
                    })(),
                    'php.settings' => $this->capability($context, WebToolsProvider::class)->setPhpSettings($ref, (array) $p('settings', [])),
                    'security.set' => (function () use ($context, $ref, $p) {
                        $result = $this->capability($context, WebToolsProvider::class)->setSecurityRules($ref, (array) $p('rules', []));
                        $service = $this->service($context);
                        $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['security' => (array) $p('rules', [])])])->save();

                        return $result;
                    })(),
                    'http3.set' => $this->capability($context, WebToolsProvider::class)->setHttp3($ref, (bool) $p('enabled', true)),
                    'cron.update' => $this->capability($context, WebToolsProvider::class)->updateCron($ref, (string) $p('remote_id'), array_filter(['schedule' => $p('schedule'), 'command' => $p('command'), 'label' => $p('label'), 'active' => $p('active')], fn ($v) => $v !== null)),
                    'cron.run' => $this->capability($context, WebToolsProvider::class)->runCron($ref, (string) $p('remote_id')),
                    'database.export' => (function () use ($context, $ref, $p) {
                        $service = $this->service($context);
                        $credentials = $context->container->make(DatabaseCredentials::class)->read($service, (string) $p('remote_id')) ?? [];
                        [$token, $path] = $context->container->make(WebFileStore::class)->newDownload($service, ($credentials['name'] ?? 'database').'-'.now()->format('Ymd-His').'.sql.gz');
                        $result = $this->capability($context, WebToolsProvider::class)->exportDatabase($ref, (string) $p('remote_id'), $path, $credentials);

                        return ProviderResult::completed($result->ref, $result->data + ['download_token' => $token, 'expires_in_minutes' => (int) config('onhost.web_tools.download_ttl_minutes', 30)]);
                    })(),
                    'database.import' => (function () use ($context, $ref, $p) {
                        $service = $this->service($context);
                        $store = $context->container->make(WebFileStore::class);
                        $file = $store->uploadPath($service, (string) $p('upload_id'));
                        $credentials = $context->container->make(DatabaseCredentials::class)->read($service, (string) $p('remote_id')) ?? [];
                        try {
                            return $this->capability($context, WebToolsProvider::class)->importDatabase($ref, (string) $p('remote_id'), $file, $credentials);
                        } finally {
                            @unlink($file);
                            @unlink($file.'.meta');
                        }
                    })(),
                    'database.access' => $this->capability($context, WebToolsProvider::class)->setDatabaseAccess($ref, (string) $p('remote_id'), (bool) $p('remote', false), (array) $p('hosts', [])),
                    'backup.delete' => (function () use ($context, $ref, $p) {
                        $result = $this->capability($context, WebToolsProvider::class)->deleteBackup($ref, (string) $p('remote_id'));
                        Backup::query()->where('service_id', $this->service($context)->id)->where('remote_id', (string) $p('remote_id'))->update(['state' => 'deleted']);

                        return $result;
                    })(),
                    'file.rename' => (function () use ($context, $ref, $p) {
                        $this->capability($context, WebToolsProvider::class)->transport($ref)->rename((string) $p('from'), (string) $p('to'));

                        return ProviderResult::completed(new ResourceRef('file', (string) $p('to'), $ref->node, [], $ref->serviceId), ['renamed' => true]);
                    })(),
                    'file.copy' => (function () use ($context, $ref, $p) {
                        $this->capability($context, WebToolsProvider::class)->transport($ref)->copy((string) $p('from'), (string) $p('to'));

                        return ProviderResult::completed(new ResourceRef('file', (string) $p('to'), $ref->node, [], $ref->serviceId), ['copied' => true]);
                    })(),
                    'file.chmod' => (function () use ($context, $ref, $p) {
                        $this->capability($context, WebToolsProvider::class)->transport($ref)->chmod((string) $p('path'), (int) $p('mode'));

                        return ProviderResult::completed(new ResourceRef('file', (string) $p('path'), $ref->node, [], $ref->serviceId), ['mode' => sprintf('%o', (int) $p('mode'))]);
                    })(),
                    'file.archive' => (function () use ($context, $ref, $p) {
                        $this->capability($context, WebToolsProvider::class)->transport($ref)->archive((array) $p('paths', []), (string) $p('target'));

                        return ProviderResult::completed(new ResourceRef('file', (string) $p('target'), $ref->node, [], $ref->serviceId), ['archived' => count((array) $p('paths', []))]);
                    })(),
                    'file.extract' => (function () use ($context, $ref, $p) {
                        $this->capability($context, WebToolsProvider::class)->transport($ref)->extract((string) $p('path'), (string) $p('target', ''));

                        return ProviderResult::completed(new ResourceRef('directory', (string) $p('target', ''), $ref->node, [], $ref->serviceId), ['extracted' => true]);
                    })(),
                    'node.create' => $this->capability($context, WebToolsProvider::class)->createNodeProject($ref, ['name' => (string) $p('name'), 'path' => (string) $p('path', ''), 'script' => (string) $p('script'), 'port' => (int) $p('port'), 'version' => $p('version'), 'domains' => (array) $p('domains', []), 'env' => (array) $p('env', [])]),
                    'node.action' => $this->capability($context, WebToolsProvider::class)->nodeProjectAction($ref, (string) $p('remote_id'), (string) $p('op')),
                    'proxy.create' => $this->capability($context, WebToolsProvider::class)->createProxy($ref, ['name' => (string) $p('name'), 'target' => (string) $p('target'), 'path' => (string) $p('path', '/'), 'cache' => (bool) $p('cache', false), 'host' => $p('host')]),
                    'proxy.delete' => $this->capability($context, WebToolsProvider::class)->deleteProxy($ref, (string) $p('remote_id')),
                    'proxies.set' => $this->capability($context, WebToolsProvider::class)->setProxies($ref, array_values((array) $p('items', []))),
                    'index.set' => $this->capability($context, WebToolsProvider::class)->setDefaultDocuments($ref, array_values((array) $p('names', []))),
                    // ── mail tools (MailToolsProvider) ───────────────────────────────────────────────────────
                    'forward.create' => $this->capability($context, MailToolsProvider::class)->createForward($ref, ['source' => (string) $p('source'), 'destination' => (string) $p('destination')]),
                    'forward.delete' => $this->capability($context, MailToolsProvider::class)->deleteForward($ref, (string) $p('remote_id')),
                    'catchall.set' => $this->capability($context, MailToolsProvider::class)->setCatchAll($ref, (string) $p('destination', '')),
                    'autoresponder.set' => $this->capability($context, MailToolsProvider::class)->setAutoresponder(ServiceFeatures::mailboxRef($ref, (string) $p('remote_id')), ['enabled' => (bool) $p('enabled', true), 'subject' => (string) $p('subject', ''), 'text' => (string) $p('text', ''), 'start' => $p('start'), 'end' => $p('end')]),
                    'spam.policy' => $this->capability($context, MailToolsProvider::class)->setMailboxSpamPolicy(ServiceFeatures::mailboxRef($ref, (string) $p('remote_id')), (string) $p('policy_id')),
                    'spam.list.add' => $this->capability($context, MailToolsProvider::class)->addSpamListEntry($ref, (string) $p('kind'), (string) $p('address')),
                    'spam.list.delete' => $this->capability($context, MailToolsProvider::class)->deleteSpamListEntry($ref, (string) $p('kind'), (string) $p('remote_id')),
                    'filter.create' => $this->capability($context, MailToolsProvider::class)->createFilter(ServiceFeatures::mailboxRef($ref, (string) $p('remote_id')), ['name' => (string) $p('name'), 'source' => (string) $p('source'), 'op' => (string) $p('op'), 'term' => (string) $p('term'), 'action' => (string) $p('action'), 'target' => (string) $p('target', '')]),
                    'filter.delete' => $this->capability($context, MailToolsProvider::class)->deleteFilter(ServiceFeatures::mailboxRef($ref, (string) $p('mailbox_id')), (string) $p('remote_id')),
                    'list.create' => $this->capability($context, MailToolsProvider::class)->createMailingList($ref, ['name' => (string) $p('name'), 'email' => (string) $p('email'), 'password' => (string) $p('password')]),
                    'list.delete' => $this->capability($context, MailToolsProvider::class)->deleteMailingList($ref, (string) $p('remote_id')),
                    'fetchmail.create' => $this->capability($context, MailToolsProvider::class)->createFetchmail($ref, ['type' => (string) $p('type'), 'host' => (string) $p('host'), 'user' => (string) $p('user'), 'password' => (string) $p('password'), 'destination' => (string) $p('destination'), 'delete' => (bool) $p('delete', false)]),
                    'fetchmail.delete' => $this->capability($context, MailToolsProvider::class)->deleteFetchmail($ref, (string) $p('remote_id')),
                    'mailbox.backup' => $this->capability($context, MailToolsProvider::class)->backupMailbox(ServiceFeatures::mailboxRef($ref, (string) $p('remote_id'))),
                    'mailbox.restore' => $this->capability($context, MailToolsProvider::class)->restoreMailbox(ServiceFeatures::mailboxRef($ref, (string) $p('remote_id')), (string) $p('backup_id')),
                    // ── game tools (GameToolsProvider) ───────────────────────────────────────────────────────
                    'variable.set' => $this->capability($context, GameToolsProvider::class)->setVariable($ref, (string) $p('key'), (string) $p('value', '')),
                    'image.set' => $this->capability($context, GameToolsProvider::class)->setDockerImage($ref, (string) $p('image')),
                    'rename' => (function () use ($context, $ref, $p) {
                        $result = $this->capability($context, GameToolsProvider::class)->rename($ref, (string) $p('name'));
                        $this->service($context)->forceFill(['label' => (string) $p('name')])->save(); // the panel lists the server under the name the customer chose

                        return $result;
                    })(),
                    'reinstall' => $this->capability($context, GameToolsProvider::class)->reinstall($ref),
                    'schedule.delete' => $this->capability($context, GameToolsProvider::class)->deleteSchedule($ref, (string) $p('remote_id')),
                    'schedule.toggle' => $this->capability($context, GameToolsProvider::class)->setScheduleActive($ref, (string) $p('remote_id'), (bool) $p('active', true)),
                    'schedule.run' => $this->capability($context, GameToolsProvider::class)->runSchedule($ref, (string) $p('remote_id')),
                    'gamedb.create' => $this->capability($context, GameToolsProvider::class)->createDatabase($ref, (string) $p('name'), (string) $p('remote', '%')),
                    'gamedb.rotate' => $this->capability($context, GameToolsProvider::class)->rotateDatabasePassword($ref, (string) $p('remote_id')),
                    'gamedb.delete' => $this->capability($context, GameToolsProvider::class)->deleteDatabase($ref, (string) $p('remote_id')),
                    'subuser.create' => $this->capability($context, GameToolsProvider::class)->createSubuser($ref, (string) $p('email'), (array) $p('permissions', [])),
                    'subuser.delete' => $this->capability($context, GameToolsProvider::class)->deleteSubuser($ref, (string) $p('remote_id')),
                    'gfile.save' => $this->capability($context, GameToolsProvider::class)->writeFile($ref, (string) $p('path'), (string) $p('content', '')),
                    'gfile.upload' => (function () use ($context, $ref, $p) { // §5r-3: a binary staged on the file store goes to the daemon, then the staging copy is gone
                        $disk = app(FileStore::class)->disk();
                        $tmp = (string) $p('tmp_path');
                        if (! $disk->exists($tmp)) {
                            throw new DomainError('upload_missing', 'The staged upload is gone; upload the file again.', 422);
                        }
                        $stream = $disk->readStream($tmp);
                        try {
                            $result = $this->capability($context, GameToolsProvider::class)->uploadFile($ref, (string) $p('directory', '/'), (string) $p('name'), $stream);
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }
                        $disk->delete($tmp);

                        return $result;
                    })(),
                    'gfile.delete' => $this->capability($context, GameToolsProvider::class)->deleteFiles($ref, (string) $p('root', '/'), [(string) $p('name')]),
                    'gfile.mkdir' => $this->capability($context, GameToolsProvider::class)->createDirectory($ref, (string) $p('root', '/'), (string) $p('name')),
                    'gfile.rename' => $this->capability($context, GameToolsProvider::class)->renameFile($ref, (string) $p('root', '/'), (string) $p('from'), (string) $p('to')),
                    'allocation.add' => $this->capability($context, GameToolsProvider::class)->addAllocation($ref),
                    'allocation.primary' => $this->capability($context, GameToolsProvider::class)->setPrimaryAllocation($ref, (string) $p('remote_id')),
                    'allocation.remove' => $this->capability($context, GameToolsProvider::class)->removeAllocation($ref, (string) $p('remote_id')),
                    'gbackup.delete' => (function () use ($context, $ref, $p) {
                        $result = $this->capability($context, GameToolsProvider::class)->deleteBackup($ref, (string) $p('remote_id'));
                        Backup::query()->where('service_id', $this->service($context)->id)->where('remote_id', (string) $p('remote_id'))->update(['state' => 'deleted']);

                        return $result;
                    })(),
                    'gbackup.lock' => $this->capability($context, GameToolsProvider::class)->lockBackup($ref, (string) $p('remote_id'), (bool) $p('locked', true)),
                    'panel.password' => $this->capability($context, GameToolsProvider::class)->setPanelPassword($ref, (string) $p('password')),
                    default => throw new \InvalidArgumentException("Unknown feature action {$this->action}"),
                };
                $context->container->make(ServiceFeatures::class)->forget($this->service($context));
                if ($this->action === 'database.create' && $result->ref !== null) { // the platform keeps the credentials it created (dumps, imports, staging copies)
                    $context->container->make(DatabaseCredentials::class)->remember($this->service($context), (string) $result->ref->remoteId, ['name' => (string) $p('name'), 'user' => (string) ($p('user') ?: ($result->data['user'] ?? '')), 'password' => (string) $p('password'), 'host' => (string) ($result->data['host'] ?? 'localhost')]);
                }
                if ($this->action === 'dbuser.password' || $this->action === 'dbuser.create') {
                    $context->container->make(DatabaseCredentials::class)->rememberForUser($this->service($context), (string) ($p('user') ?: $p('remote_id')), (string) $p('password'));
                }
                if (in_array($this->action, ['shell.create', 'shell.key', 'shell.delete'], true)) { // whose key sits on which account (H185)
                    $keys = $context->container->make(SshKeyLedger::class);
                    $site = $this->service($context);
                    $account = (string) ($this->action === 'shell.create' ? ($result->ref->remoteId ?? '') : $p('remote_id'));
                    $publicKey = trim((string) $p('ssh_key', ''));
                    if ($this->action === 'shell.delete' || ($this->action === 'shell.key' && $publicKey === '')) {
                        $result->isAsync() ? $keys->removalRequested($site, $account, $context->operation->id) : $keys->removed($site, $account);
                    } elseif ($publicKey !== '' && ! $result->alreadyExisted) { // an account that already existed kept its own key: nothing was installed
                        $keys->installed($site, $account, $this->action === 'shell.create' ? (string) $p('user') : null, $publicKey, SshKeyLedger::ownerFor($site, $p('owner_user_id') ? (string) $p('owner_user_id') : null, $context->actor->actorType, $context->actor->actorId), $context->actor->actorType, $context->actor->actorId);
                    }
                }
                if ($this->action === 'php.set') {
                    $service = $this->service($context);
                    $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['php_version' => (string) $p('version')])])->save();
                }
                if ($this->action === 'folder.protect' && ($result->ref?->remoteType ?? '') === 'site_password') { // site-wide password (aaPanel): the platform remembers the user
                    $service = $this->service($context);
                    $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['site_password' => ['user' => (string) $p('user'), 'set_at' => now()->toIso8601String()]])])->save();
                }
                if ($this->action === 'folder.unprotect' && $p('remote_id') === 'site') {
                    $service = $this->service($context);
                    $service->forceFill(['desired_spec' => array_diff_key((array) $service->desired_spec, ['site_password' => 1])])->save();
                }

                return $this->settle($result, ['action' => $this->action, 'ref' => $result->ref?->toArray()]);
            }
        };
    }

    private function verifyPowerStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Ověření stavu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $state = $this->capability($context, InfrastructureProvider::class)->getActualState($this->ref($context));
                $target = (string) $context->get('target', $state->status);
                $inTransit = ($target === 'running' && $state->status === 'starting') || ($target === 'stopped' && $state->status === 'stopping'); // the daemon accepted the command; a first start may build for minutes (Spigot), the console shows the live state
                if ($state->status !== $target && ! $inTransit && $context->get('noop') !== true) {
                    return StepResult::fail("Expected {$target}, provider reports {$state->status}", true, $state->attributes, 5);
                }
                $context->container->make(ServiceService::class)->recordActual($service, $state, $context->actor, 'service.power', ['action' => $context->desired('power_action')]);
                $context->container->make(AvailabilityWatch::class)->intend($service, $target); // a stop ordered here is not an outage (H14)

                return StepResult::done(['status' => $state->status]);
            }
        };
    }

    private function suspendStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Pozastavení';
            }

            public function run(StepContext $context): StepResult
            {
                return $this->settle($this->capability($context, InfrastructureProvider::class)->suspend($this->ref($context)));
            }
        };
    }

    private function resumeStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Obnovení';
            }

            public function run(StepContext $context): StepResult
            {
                return $this->settle($this->capability($context, InfrastructureProvider::class)->resume($this->ref($context)));
            }
        };
    }

    private function finishStateStep(string $state): ServiceStep
    {
        return new class($state) extends ServiceStep
        {
            public function __construct(private readonly string $state) {}

            public function label(): string
            {
                return 'Zápis stavu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $actual = $this->capability($context, InfrastructureProvider::class)->getActualState($this->ref($context));
                $context->container->make(ServiceService::class)->settleTransient($service, $this->state, $context->actor, (string) $context->desired('reason', ''), $context->operation, $actual);

                return StepResult::done(['status' => $actual->status]);
            }
        };
    }

    private function resizeStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Změna zdrojů';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $target = (array) $context->desired('entitlements', $service->entitlements);
                $infra = $this->capability($context, InfrastructureProvider::class);

                return $this->settle($infra->resize($this->ref($context), $context->spec($this->kindFor($service), [
                    'entitlements' => $target, 'vcpu' => (int) ($target['vcpu'] ?? 0) ?: null, 'ram_mb' => (int) ($target['ram_mb'] ?? 0) ?: null, 'nvme_gb' => (int) ($target['nvme_gb'] ?? 0) ?: null, 'cpu_limit' => ($target['cpu_class'] ?? 'shared') === 'dedicated' ? null : ((int) ($target['vcpu'] ?? 0) ?: null),
                    'limits' => (array) $context->desired('limits', []), 'php_version' => $context->desired('php_version'),
                ])), ['target_entitlements' => $target]);
            }

            private function kindFor(Service $service): string
            {
                return match ($service->family) {
                    'cloud', 'data' => 'vm', 'web', 'managed' => 'website', 'game' => 'game_server', 'apps' => 'namespace', 'mail' => 'mail_domain', default => 'resource'
                };
            }
        };
    }

    private function finishResizeStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Ověření nových zdrojů';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $actual = $this->capability($context, InfrastructureProvider::class)->getActualState($this->ref($context));
                $target = (array) $context->get('target_entitlements', []);
                $service->forceFill(['entitlements' => array_replace((array) $service->entitlements, $target), 'desired_spec' => array_replace((array) $service->desired_spec, ['entitlements' => array_replace((array) $service->entitlements, $target)])])->save();
                $context->container->make(ServiceService::class)->settleTransient($service, ServiceStateMachine::ACTIVE, $context->actor, 'resized', $context->operation, $actual, 'service.resized');

                return StepResult::done(['status' => $actual->status]);
            }
        };
    }

    /**
     * Everything the provider can hand over lands on the backup disk before anything is deleted (audit §5aa): site
     * files and database dumps, the game server's archive, the mail domain with its mailboxes, always the service
     * metadata. Kept for 60 days as a protected backup. A failure stops the termination — nothing is deleted without it.
     */
    /**
     * At least five independent identifiers must point at the same resource before anything is archived or deleted
     * (audit §5ab). The report is carried in the operation context, written into the archive and into the audit.
     */
    private function identityStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Ověření identity služby';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $report = $context->container->make(ServiceIdentityCheck::class)->verify($service, $context->adapter(), $context->binding(null)?->ref());
                if (! $report['ok'] && ($report['missing'] ?? false)) { // the panel says the resource is already gone: nothing to delete, the archive keeps the metadata
                    return StepResult::done(['identity' => $report, 'identity_matched' => $report['matched'], 'identity_missing' => true]);
                }
                if (! $report['ok']) {
                    return StepResult::fail('identitu služby se nepodařilo ověřit ('.$report['matched'].'/'.$report['required'].' bodů, neshody: '.(implode(', ', $report['failed']) ?: 'žádné').')', false, ['identity' => $report]);
                }

                return StepResult::done(['identity' => $report, 'identity_matched' => $report['matched']]);
            }
        };
    }

    /** The cancellation only switches the service off; the data stays at the provider for the whole grace window. */
    /**
     * Delegated access dies with the service (H346): the FTP and shell accounts of a web site, the collaborator
     * accounts of a game server. They are removed right after the deactivation — the archive already lists them —
     * and recorded on the service, so a panel resource that is reused later never keeps a stranger's login and the
     * customer knows what to create again after a restore. Database users stay: they belong to the application,
     * not to a person, and a restore inside the window must find them.
     */
    private function revokeDelegationsStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Odvolání delegovaných přístupů';
            }

            public function run(StepContext $context): StepResult
            {
                if ($context->get('identity_missing') === true) {
                    return StepResult::done(['revoked_access' => [], 'already_gone' => true]);
                }
                $service = $this->service($context);
                $adapter = $context->adapter();
                $ref = $this->ref($context);
                $revoked = [];
                $failed = [];
                $attempt = function (string $kind, string $remoteId, string $label, callable $call) use (&$revoked, &$failed): void {
                    try {
                        $call();
                        $revoked[] = ['kind' => $kind, 'remote_id' => $remoteId, 'label' => $label];
                    } catch (ProviderException $e) {
                        if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                            $revoked[] = ['kind' => $kind, 'remote_id' => $remoteId, 'label' => $label, 'already_gone' => true];

                            return;
                        }
                        $failed[] = "{$kind} {$label}: ".mb_substr($e->getMessage(), 0, 120);
                    } catch (Throwable $e) {
                        $failed[] = "{$kind} {$label}: ".mb_substr($e->getMessage(), 0, 120);
                    }
                };
                try {
                    // The ISPConfig adapter is a WebHostingProvider for EVERY service it runs, a mail domain included — and a mail
                    // domain's id looked up among web sites is somebody else's site: cancelling a mail service listed and deleted
                    // the FTP and shell accounts of the stranger whose web domain happened to carry the same number.
                    if ($adapter instanceof WebHostingProvider && in_array($ref->remoteType, ['site', 'web_domain'], true)) {
                        foreach ($adapter->listFtpAccounts($ref) as $account) {
                            $attempt('ftp', (string) $account['remote_id'], (string) ($account['user'] ?? $account['remote_id']), fn () => $adapter->deleteFtpAccount($ref, (string) $account['remote_id']));
                        }
                        foreach ($adapter->listShellUsers($ref) as $user) {
                            $attempt('shell', (string) $user['remote_id'], (string) ($user['user'] ?? $user['remote_id']), fn () => $adapter->deleteShellUser($ref, (string) $user['remote_id']));
                        }
                    }
                    if ($adapter instanceof GameToolsProvider) {
                        foreach ($adapter->listSubusers($ref) as $subuser) {
                            $attempt('subuser', (string) $subuser['remote_id'], (string) ($subuser['email'] ?? $subuser['remote_id']), fn () => $adapter->deleteSubuser($ref, (string) $subuser['remote_id']));
                        }
                    }
                } catch (Throwable $e) { // the listing itself failed: nothing was removed, the step is retried
                    return StepResult::fail('delegované přístupy nelze načíst: '.mb_substr($e->getMessage(), 0, 160), true, ['revoked' => $revoked], 120);
                }
                if ($failed !== []) {
                    return StepResult::fail('některé delegované přístupy se nepodařilo odvolat: '.implode('; ', $failed), true, ['revoked' => $revoked, 'failed' => $failed], 120);
                }
                if ($revoked !== []) {
                    $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.delegations.revoked', 'service', $service->id, [
                        'product_key' => $service->product_key, 'count' => count($revoked), 'kinds' => array_values(array_unique(array_column($revoked, 'kind'))),
                    ], $service->organization_id));
                }

                return StepResult::done(['revoked_access' => $revoked]);
            }
        };
    }

    private function deactivateStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Deaktivace služby';
            }

            public function run(StepContext $context): StepResult
            {
                if ($context->get('final_archive_id') === null && $context->get('final_archive_skipped') === null) { // audit §5aa: nothing is switched off before the archive is complete
                    return StepResult::fail('záloha před zrušením chybí; služba nebude deaktivována', true, [], 60);
                }
                if ($context->get('identity_missing') === true) {
                    return StepResult::done(['deactivated' => true, 'already_gone' => true]);
                }

                return $this->settle($this->capability($context, InfrastructureProvider::class)->suspend($this->ref($context)), ['deactivated' => true]);
            }
        };
    }

    /**
     * The service is now deactivated and waits: the customer may bring it back for `DeletionPolicy::graceDays()`
     * days (a plain resume does that), afterwards onhost:services:purge removes it for good.
     */
    private function scheduleRemovalStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Naplánování odstranění';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $policy = $context->container->make(DeletionPolicy::class);
                $graceUntil = now()->addDays($policy->graceDays());
                $actual = null;
                try {
                    $actual = $this->capability($context, InfrastructureProvider::class)->getActualState($this->ref($context));
                } catch (Throwable) {
                    // the state read is a nicety here; the deactivation itself already succeeded
                }
                $reason = (string) $context->desired('reason', 'zrušení služby');
                $context->container->make(ServiceService::class)->settleTransient($service, ServiceStateMachine::SUSPENDED, $context->actor, $reason, $context->operation, $actual, 'service.deactivated');
                $fresh = Service::query()->findOrFail($service->id);
                $tags = (array) $fresh->tags;
                $tags['deletion'] = [
                    'requested_at' => now()->toIso8601String(), 'grace_until' => $graceUntil->toIso8601String(), 'grace_days' => $policy->graceDays(),
                    'retention_days' => $policy->retentionDays(), 'archive_backup_id' => $context->get('final_archive_id'), 'archive_set' => $context->get('final_archive_set'),
                    'reason' => mb_substr($reason, 0, 200), 'operation_id' => $context->operation->id, 'identity_matched' => $context->get('identity_matched'),
                    'revoked' => (array) $context->get('revoked_access', []), // H346: the delegated logins removed with the deactivation, apart from the runtime itself
                ];
                $fresh->forceFill(['terminate_at' => $graceUntil, 'tags' => $tags])->save();
                Subscription::query()->where('service_id', $fresh->id)->whereNotIn('state', [Subscription::CANCELLED])->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.deletion.scheduled', 'service', $fresh->id, [
                    'grace_until' => $graceUntil->toIso8601String(), 'grace_days' => $policy->graceDays(), 'retention_days' => $policy->retentionDays(),
                    'archive_backup_id' => $context->get('final_archive_id'), 'product_key' => $fresh->product_key, 'reason' => mb_substr($reason, 0, 200),
                ], $fresh->organization_id));

                return StepResult::done(['deactivated' => true, 'grace_until' => $graceUntil->toIso8601String()]);
            }
        };
    }

    private function finalArchiveStep(bool $anyOperation = false): ServiceStep
    {
        return new class($anyOperation) extends ServiceStep
        {
            public function __construct(private readonly bool $anyOperation = false) {}

            public function label(): string
            {
                return 'Záloha před zrušením';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $archives = $context->container->make(FinalArchive::class);
                $existing = $archives->existing($service, $this->anyOperation ? null : $context->operation->id);
                if ($existing !== null) {
                    return StepResult::done(['final_archive_id' => $existing->id, 'final_archive_set' => data_get($existing->meta, 'set')]);
                }
                if ($context->desired('archive_before_delete', true) === false) { // staff override with a reason (service.terminate params), recorded in the audit
                    return StepResult::done(['final_archive_id' => null, 'final_archive_skipped' => (string) $context->desired('archive_skip_reason', 'operator override')]);
                }
                $ref = $context->binding(null)?->ref();
                try {
                    $result = $archives->create($service, $context->adapter(), $ref, $context->actor, $context->operation->id, is_array($context->get('identity')) ? $context->get('identity') : null);
                } catch (DomainError $e) {
                    return StepResult::fail('final archive failed: '.$e->getMessage(), $e->status >= 500, ['error' => $e->error], 120);
                } catch (Throwable $e) {
                    return StepResult::fail('final archive failed: '.$e->getMessage(), true, [], 120);
                }

                return StepResult::done(['final_archive_id' => $result['backup']->id, 'final_archive_set' => $result['set'], 'final_archive_gaps' => $result['gaps']]);
            }
        };
    }

    private function terminateStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Odstranění u providera';
            }

            public function run(StepContext $context): StepResult
            {
                if ($context->get('final_archive_id') === null && $context->get('final_archive_skipped') === null) { // audit §5aa: never delete without the archive
                    return StepResult::fail('the final archive is missing; refusing to delete the service', true, [], 60);
                }
                if ($context->get('identity_missing') === true) { // the panel already has no such resource; the rest of the cleanup still runs
                    return StepResult::done(['terminated' => true, 'already_gone' => true]);
                }

                return $this->settle($this->capability($context, InfrastructureProvider::class)->terminate($this->ref($context)), ['terminated' => true]);
            }
        };
    }

    /** Hosting subdomains in a platform zone (<label>.web.onhost.cz) lose their A/AAAA rows when the service goes. */
    private function platformDnsStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Úklid DNS';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $dns = $context->container->make(DnsService::class);
                $platform = $dns->platformZoneFor((string) $service->hostname);
                if ($platform === null) {
                    return StepResult::skip();
                }
                [$zone, $relative] = $platform;
                try {
                    $version = $dns->syncHostname($zone, $relative, null, null, $context->actor, "service:{$service->id}", "service terminated {$service->id}");
                } catch (Throwable $e) {
                    return StepResult::done(['dns_cleanup' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 200)]); // the reconciler retries; termination must not hang on DNS
                }

                return StepResult::done(['dns_cleanup' => $version === null ? 'nothing' : 'removed', 'dns_zone' => $zone->name]);
            }
        };
    }

    private function releaseStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Uvolnění zdrojů';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $ipam = $context->container->make(IpamService::class);
                foreach (IpAddress::query()->where('service_id', $service->id)->where('state', 'allocated')->get() as $address) {
                    $ipam->release($address);
                }
                $context->container->make(ServiceService::class)->settleTransient($service, ServiceStateMachine::TERMINATED, $context->actor, (string) $context->desired('reason', 'terminated'), $context->operation, null, 'service.terminated');
                $archive = Backup::query()->where('service_id', $service->id)->where('kind', 'final')->where('state', 'completed')->orderByDesc('finished_at')->first();
                if ($archive !== null) { // audit §5ab: the retention of the archive is counted from the removal, not from the cancellation
                    $context->container->make(FinalArchive::class)->startRetention($archive);
                }

                return StepResult::done(['released' => true, 'archive_backup_id' => $archive?->id, 'archive_retention_until' => $archive?->fresh()?->retention_until?->toIso8601String()]);
            }
        };
    }

    private function backupStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Záloha';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $adapter = $this->capability($context, BackupCapable::class);
                $backup = Backup::query()->firstOrCreate(['operation_id' => $context->operation->id], ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => (string) $context->desired('kind', 'manual'), 'state' => 'running', 'started_at' => now(), 'retention_until' => now()->addDays((int) $context->desired('retention_days', 30)), 'protected' => (bool) $context->desired('protected', false)]);

                $result = $adapter->backup($this->ref($context), (array) $context->desired('policy', []));
                if (! $result->isAsync()) { // panels that archive synchronously: the row is complete right away
                    $latest = collect($adapter->listBackups($this->ref($context)))->sortByDesc('created_at')->first();
                    $backup->forceFill(['state' => 'completed', 'finished_at' => now(), 'remote_id' => $latest['remote_id'] ?? null, 'size_bytes' => $latest['size_bytes'] ?? null])->save();
                }

                return $this->settle($result, ['backup_id' => $backup->id, 'backup_remote_id' => $backup->remote_id]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                $adapter = $this->capability($context, BackupCapable::class);
                $latest = collect($adapter->listBackups($this->ref($context)))->sortByDesc('created_at')->first();
                Backup::query()->whereKey($context->get('backup_id'))->update(['state' => 'completed', 'finished_at' => now(), 'remote_id' => $latest['remote_id'] ?? null, 'size_bytes' => $latest['size_bytes'] ?? null, 'verify_status' => isset($latest['verified']) ? ($latest['verified'] ? 'ok' : 'pending') : null, 'remote_datastore' => $context->instance()->option('backup_storage')]);

                return StepResult::done(['backup_remote_id' => $latest['remote_id'] ?? null]);
            }
        };
    }

    private function restoreStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Obnova ze zálohy';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $backup = Backup::query()->findOrFail((string) $context->desired('backup_id'));
                if ($backup->service_id !== $service->id || $backup->remote_id === null) {
                    return StepResult::fail('Backup does not belong to this service or has no remote id', false);
                }
                $job = RestoreJob::query()->firstOrCreate(['operation_id' => $context->operation->id], ['backup_id' => $backup->id, 'service_id' => $service->id, 'organization_id' => $service->organization_id, 'target' => (string) $context->desired('target', 'in_place'), 'state' => 'running', 'requested_by' => $context->actor->actorId, 'started_at' => now()]);
                $adapter = $this->capability($context, BackupCapable::class);

                return $this->settle($adapter->restore($this->ref($context), (string) $backup->remote_id, (array) $context->desired('options', [])), ['restore_job_id' => $job->id]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                $job = RestoreJob::query()->findOrFail((string) $context->get('restore_job_id'));
                $job->forceFill(['state' => 'completed', 'finished_at' => now(), 'duration_seconds' => $job->started_at ? (int) now()->diffInSeconds($job->started_at, true) : null, 'result' => $status->detail])->save();

                return StepResult::done(['restored' => true]);
            }
        };
    }

    /**
     * Free restore of a final archive onto a new paid service (audit §5ab): the site files go back through the
     * panel's file transport, every database dump into a database of the new service. The archive itself is never
     * touched — it stays on the backup disk until its retention runs out.
     */
    private function archiveRestoreStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Obnova z archivu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $backup = Backup::query()->find((string) $context->desired('backup_id'));
                if ($backup === null || $backup->organization_id !== $service->organization_id || $backup->kind !== 'final' || $backup->state !== 'completed') {
                    return StepResult::fail('archiv nepatří této organizaci nebo není dokončený', false);
                }
                $archives = $context->container->make(FinalArchive::class);
                $disk = $archives->disk();
                $set = (string) data_get($backup->meta, 'set', '');
                if ($set === '' || $disk->files($set) === []) {
                    return StepResult::fail('archivní sada už na disku není', false);
                }
                $job = RestoreJob::query()->firstOrCreate(['operation_id' => $context->operation->id], ['backup_id' => $backup->id, 'service_id' => $service->id, 'organization_id' => $service->organization_id,
                    'target' => 'new_service', 'state' => 'running', 'requested_by' => $context->actor->actorId, 'started_at' => now()]);
                $adapter = $this->capability($context, WebToolsProvider::class);
                $ref = $this->ref($context);
                $work = storage_path('app/onhost-restore-'.Str::random(8));
                if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work)) {
                    return StepResult::fail('pracovní adresář pro obnovu nelze vytvořit', true, [], 60);
                }
                $result = ['files' => null, 'databases' => []];
                try {
                    $databases = $adapter instanceof WebHostingProvider ? $adapter->listDatabases($ref) : [];
                    foreach ($disk->files($set) as $file) {
                        $name = basename($file);
                        if (! str_starts_with($name, 'site-files') && ! (str_starts_with($name, 'database-') && str_ends_with($name, '.sql'))) {
                            continue; // service.json and the manifest describe the archive, they are not restored onto the node
                        }
                        $local = $work.'/'.$name;
                        $stream = $disk->readStream($file);
                        $out = is_resource($stream) ? fopen($local, 'wb') : false;
                        if (! is_resource($stream) || ! is_resource($out)) {
                            return StepResult::fail("část archivu {$name} nelze načíst", true, [], 120);
                        }
                        stream_copy_to_stream($stream, $out);
                        fclose($out);
                        fclose($stream);
                        if (str_starts_with($name, 'site-files')) {
                            $transport = $adapter->transport($ref);
                            $transport->upload($name, $local);
                            $transport->extract($name, '.');
                            try {
                                $transport->delete($name);
                            } catch (Throwable) {
                                // the uploaded archive stays in the site root; the customer sees it and may delete it
                            }
                            $result['files'] = $name;
                        } else {
                            $target = array_shift($databases);
                            if ($target === null && $adapter instanceof WebHostingProvider) {
                                $created = $adapter->createDatabase($ref, ['name' => Str::slug(str_replace(['database-', '.sql'], '', $name), '_'), 'password' => Str::password(20)]);
                                $target = ['remote_id' => $created->ref === null ? '' : $created->ref->remoteId, 'name' => $created->ref === null ? null : ($created->ref->meta['name'] ?? null)];
                            }
                            if ($target === null || (string) ($target['remote_id'] ?? '') === '') {
                                return StepResult::fail("pro dump {$name} není kam importovat databázi", false);
                            }
                            $adapter->importDatabase($ref, (string) $target['remote_id'], $local);
                            $result['databases'][] = ['dump' => $name, 'database' => $target['name'] ?? $target['remote_id']];
                        }
                        @unlink($local);
                    }
                } finally {
                    foreach (glob($work.'/*') ?: [] as $left) {
                        @unlink($left);
                    }
                    @rmdir($work);
                }
                $job->forceFill(['state' => 'completed', 'finished_at' => now(), 'duration_seconds' => $job->started_at ? (int) now()->diffInSeconds($job->started_at, true) : null, 'result' => $result])->save();
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.archive.restored', 'service', $service->id, [
                    'backup_id' => $backup->id, 'files' => $result['files'], 'databases' => count($result['databases']),
                ], $service->organization_id));

                return StepResult::done(['restored_from_archive' => $backup->id] + $result);
            }
        };
    }

    private function snapshotStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Snapshot';
            }

            public function run(StepContext $context): StepResult
            {
                $compute = $this->capability($context, ComputeProvider::class);
                $name = (string) $context->desired('name', 'onhost-'.now()->format('Ymd-His'));

                return $this->settle($compute->snapshot($this->ref($context), $name, (string) $context->desired('description', '')), ['snapshot' => $name]);
            }
        };
    }

    private function rollbackSnapshotStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Návrat na snapshot';
            }

            public function run(StepContext $context): StepResult
            {
                return $this->settle($this->capability($context, ComputeProvider::class)->rollback($this->ref($context), (string) $context->desired('name')), ['rolled_back' => true]);
            }
        };
    }
}
