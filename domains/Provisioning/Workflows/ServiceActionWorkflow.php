<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\AvailabilityWatch;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\LegalHold;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\RestoreJob;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\RescueMode;
use Onhost\Domain\Services\ServiceBackups;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Domain\Services\SuspensionDepth;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\Services\Web\CommandRunner;
use Onhost\Domain\Services\Web\DatabaseCredentials;
use Onhost\Domain\Services\Web\DatabaseImport;
use Onhost\Domain\Services\Web\PlanAllowance;
use Onhost\Domain\Services\Web\RestoreTest;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Domain\Services\Web\StagingService;
use Onhost\Domain\Services\Web\WebFileStore;
use Onhost\Platform\Commands\CommandContext;
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
    public const CORE_ACTIONS = ['power', 'suspend', 'resume', 'resize', 'terminate', 'purge', 'backup', 'restore', 'restore.test', 'archive.restore', 'snapshot', 'rollback_snapshot'];

    /** Feature actions: one provider call each, validated by ServiceService::featureParams, no service state change. */
    public const FEATURE_ACTIONS = [
        'php.set', 'database.create', 'database.delete', 'ftp.create', 'ftp.delete', 'ftp.password', 'cron.create', 'cron.delete', 'subdomain.add', 'subdomain.remove',
        'redirect.set', 'ssl.issue', 'https.force', 'snapshot.delete', 'firewall.apply', 'access.reset', 'rdns.set', 'rescue.start', 'rescue.stop', 'command.send', 'schedule.create', 'mailbox.create', 'mailbox.update', 'mailbox.delete',
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

    /**
     * Everything that happens inside a mail domain. On a web service that domain is a resource of its own — possibly
     * on a mail server of its own — so these actions are made with its binding and not with the site's.
     */
    public const MAIL_ACTIONS = [
        'mailbox.create', 'mailbox.update', 'mailbox.delete', 'alias.create', 'alias.delete', 'sending.set',
        'forward.create', 'forward.delete', 'catchall.set', 'autoresponder.set', 'spam.policy', 'spam.list.add', 'spam.list.delete',
        'filter.create', 'filter.delete', 'list.create', 'list.delete', 'fetchmail.create', 'fetchmail.delete', 'mailbox.backup', 'mailbox.restore',
    ];

    /** Actions that run as sagas of their own (ServiceService::actionWorkflowFor); listed here so the API validates them alike. */
    public const PLATFORM_ACTIONS = ['staging.create', 'staging.refresh', 'staging.push', 'staging.delete', 'deploy.run', 'deploy.rollback', 'wp.install', 'wp.update', 'wp.cache', 'wp.plugin', 'import.run', 'cdn.enable', 'cdn.disable', 'cdn.purge', 'ssl.wildcard', 'site.create', 'site.delete'];

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
        // an add-on has no resource of its own: it changed the service it was bought for, and cancelling it gives that back.
        // Sent down the ordinary chain it failed the identity check ("the service has no provider binding") — so a paid
        // add-on could be neither delivered nor cancelled (audit §5ac).
        if (in_array($action, ['terminate', 'purge'], true) && Service::query()->whereKey($operation->service_id)->value('family') === 'addon') {
            return $action === 'purge'
                ? [$this->detachAddonStep(), $this->releaseStep()]
                : [$this->detachAddonStep(), $this->scheduleRemovalStep()];
        }

        return match ($action) {
            'power' => [$this->powerStep(), $this->verifyPowerStep()],
            'suspend' => [$this->suspendStep(), $this->pauseExtrasStep(), $this->holdIncludedServicesStep(true), $this->finishStateStep(ServiceStateMachine::SUSPENDED)],
            'resume' => [$this->resumeStep(), $this->resumeExtrasStep(), $this->holdIncludedServicesStep(false), $this->finishStateStep(ServiceStateMachine::ACTIVE)],
            'resize' => [$this->resizeStep(), $this->finishResizeStep()],
            'terminate' => [$this->identityStep(), $this->finalArchiveStep(), $this->deactivateStep(), $this->pauseExtrasStep(), $this->cancelAddonsStep(), $this->endIncludedServicesStep(), $this->revokeDelegationsStep(), $this->scheduleRemovalStep()],
            'purge' => [$this->identityStep(), $this->finalArchiveStep(anyOperation: true), $this->endIncludedServicesStep(), $this->removeMailDomainStep(), $this->terminateStep(), $this->platformDnsStep(), $this->releaseStep()],
            'backup' => [$this->backupStep()],
            'restore' => [$this->safetyCopyStep('pre_restore'), $this->restoreStep()],
            'restore.test' => [$this->restoreTestStep()], // restores into databases of its own: nothing live is touched, so no copy is needed
            'archive.restore' => [$this->archiveRestoreStep()],
            'snapshot' => [$this->snapshotStep()],
            'rollback_snapshot' => [$this->safetyCopyStep('pre_rollback'), $this->rollbackSnapshotStep()],
            'reinstall' => [$this->safetyCopyStep('pre_reinstall'), $this->featureStep('reinstall')], // rewrites the server files
            // a dump is written OVER a live database: room first, then a copy of exactly that database, then the import
            'database.import' => [$this->importRoomStep(), $this->safetyCopyStep('pre_import', onlyTargetDatabase: true), $this->featureStep('database.import')],
            // a web hosting plan sells mailboxes; the panel puts one inside a mail domain, and the site saga never made one
            'mailbox.create', 'alias.create' => [$this->ensureMailDomainStep(), $this->featureStep($action)],
            // `database.delete` deliberately keeps NO copy of its own: the customer asked for that data to go, they saw
            // the preview and confirmed the target, and a protected archive they cannot remove would be the platform
            // keeping deleted data for sixty days. What protects them there is the preview and the scheduled backups.
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
            'database.import' => $this->afterFailedImport($context, $service, $reason),
            default => null,
        };
    }

    /**
     * An import that did not finish must not leave half a database behind (H467).
     *
     * MySQL applies a dump statement by statement: a file that breaks half way leaves the database half old and half
     * new, and the site goes on serving from it. The copy taken before the import is the way back — but only when we
     * know the import is really over. A failure the platform may retry (a timeout, a panel that stopped answering)
     * may still be running on the node, and a restore would race it; those are left exactly as they are and named.
     *
     * When the panel refused for good, putting the copy back is right either way: if nothing was applied it writes
     * the same rows again, and if part of it was, this is the only way back.
     */
    private function afterFailedImport(StepContext $context, Service $service, string $reason): void
    {
        $copy = Backup::query()->where('operation_id', $context->operation->id)->where('kind', 'pre_import')->where('state', 'completed')->first();
        // the PANEL's own code, not the operation's `retryable`: once the retries are spent the operation says it will not
        // try again, which is true of a timeout as well — and a timeout is exactly the case that may still be running
        $code = ProviderErrorCode::tryFrom((string) data_get($context->operation->error, 'detail.code', ''));
        $refused = $code !== null && ! $code->isRetryable();
        $restored = false;
        try {
            $adapter = $context->adapter();
            if ($copy !== null && $refused && $adapter instanceof WebToolsProvider) {
                $binding = $context->binding() ?? ProviderBinding::query()->where('service_id', $service->id)->orderBy('created_at')->orderBy('id')->first();
                if ($binding !== null) {
                    $context->container->make(ServiceBackups::class)->restoreInPlace($service, $adapter, $binding->ref(), $copy);
                    $restored = true;
                }
            }
        } catch (Throwable $e) {
            $reason .= ' · kopii se nepodařilo nahrát zpět: '.mb_substr($e->getMessage(), 0, 120);
        }
        $tags = (array) $service->tags;
        $tags['db_import'] = ['database' => (string) $context->desired('remote_id'), 'phase' => $restored ? 'rolled_back' : ($copy === null ? 'no_copy' : 'left_as_is'),
            'copy_id' => $copy?->id, 'at' => now()->toIso8601String(), 'reason' => mb_substr($reason, 0, 160)];
        $service->forceFill(['tags' => $tags])->save();
        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.database.import.failed', 'service', $service->id, [
            'database' => (string) $context->desired('remote_id'), 'restored' => $restored, 'copy_id' => $copy?->id,
            'reason' => mb_substr($reason, 0, 160), 'label' => $service->label ?: ($service->hostname ?: $service->name),
        ], $service->organization_id));
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
                    $this->action === 'snapshot.delete' => 'Snapshot', $this->action === 'firewall.apply' => 'Firewall', $this->action === 'access.reset' => 'Přístup k serveru', $this->action === 'rdns.set' => 'Reverzní záznam', str_starts_with($this->action, 'rescue.') => 'Záchranný režim', $this->action === 'command.send' => 'Příkaz konzole', $this->action === 'schedule.create' => 'Plánovaná úloha',
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
                // mail on a web service happens in its own mail domain, which may live on a mail server of its own:
                // the web binding's number and node are the site's, and a mail call made with them lands on the wrong one
                $mailAction = in_array($this->action, ServiceActionWorkflow::MAIL_ACTIONS, true);
                $mailRef = null;
                if ($mailAction) { // and to the domain of ITS OWN address: a service can have mail in several of its domains
                    $mailService = $this->service($context);
                    $address = (string) ($context->desired('address') ?? $context->desired('source') ?? '');
                    $mailRef = MailDomains::forDomain($mailService, MailDomains::domainOf($address))?->ref() ?? MailDomains::firstRefOf($mailService);
                }
                $ref = $mailRef ?? $this->ref($context);
                $p = fn (string $k, mixed $d = null) => $context->desired($k, $d);
                $owed = (array) $p('_limit', []); // the plan's limit could not be counted when the request came in: the panel was away (H02)
                if (isset($owed['kind'], $owed['limit'])) {
                    $counted = empty($owed['group']) // a number of the plan is counted over every site of the plan, exactly as the request would have counted it
                        ? count($context->container->make(ServiceFeatures::class)->resources($this->service($context), (string) $owed['kind'], true))
                        : $context->container->make(PlanAllowance::class)->count($this->service($context), (string) $owed['kind']);
                    if ($counted >= (int) $owed['limit']) {
                        return StepResult::fail(empty($owed['group'])
                            ? "{$this->action}: the plan allows {$owed['limit']} of these."
                            : PlanAllowance::message((string) ($owed['feature'] ?? ''), (int) $owed['limit'], $counted), false, ['feature_limit_reached' => true, 'limit' => (int) $owed['limit']]);
                    }
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
                    'access.reset' => (function () use ($context, $ref, $p) {
                        // only what is given is sent: the network and the user of the server's cloud-init stay as they are. The panel makes
                        // the cloud-init drive again; the server reads it at its next start.
                        $result = $this->capability($context, ComputeProvider::class)->applyCloudInit($ref, array_filter(['password' => $p('password'), 'sshkeys' => (array) $p('ssh_keys', [])], fn ($v) => $v !== null && $v !== '' && $v !== []));
                        if ((array) $p('ssh_keys', []) !== []) {
                            $service = $this->service($context);
                            $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['ssh_keys' => array_values((array) $p('ssh_keys'))])])->save();
                        }

                        return $result;
                    })(),
                    'rdns.set' => (function () use ($context, $ref, $p) {
                        // the PTR belongs to the address the platform allocated to THIS service; the customer names a host, never an address
                        $service = $this->service($context);
                        $address = IpAddress::query()->where('service_id', $service->id)->where('state', 'allocated')->where('family', (int) $p('family', 4))->first();
                        if ($address === null) {
                            throw new DomainError('rdns_no_address', 'Tato služba nemá přidělenou adresu této rodiny.', 409, ['family' => (int) $p('family', 4)]);
                        }
                        $hostname = (string) $p('hostname', '');
                        $ipam = $context->container->make(IpamService::class);
                        if ($hostname !== '' && ! $ipam->reverseZoneExists($address)) {
                            throw new DomainError('rdns_zone_missing', 'Reverzní zónu pro tuto adresu platforma nespravuje; nastavit záznam nelze.', 409, ['address' => (string) $address->address]);
                        }
                        $ipam->setReverseDns($address, $hostname === '' ? null : $hostname, $context->actor, 'customer request');

                        return ProviderResult::completed($ref, ['rdns' => $address->fresh()->rdns, 'address' => (string) $address->address]);
                    })(),
                    'rescue.start' => (function () use ($context, $ref, $p) {
                        $session = $context->container->make(RescueMode::class)->start($this->service($context), $context->actor, $p('image'), $p('hours') === null ? null : (int) $p('hours'));

                        return ProviderResult::completed($ref, ['image' => basename((string) $session['iso']), 'until' => $session['until']]);
                    })(),
                    'rescue.stop' => (function () use ($context, $ref) {
                        $result = $context->container->make(RescueMode::class)->stop($this->service($context), $context->actor, 'the customer ended it');

                        return ProviderResult::completed($ref, $result);
                    })(),
                    'command.send' => $this->capability($context, GameProvider::class)->sendCommand($ref, (string) $p('command')),
                    'schedule.create' => $this->capability($context, GameProvider::class)->createSchedule($ref, ['name' => $p('name'), 'cron' => $p('cron'), 'actions' => (array) $p('actions', [])]),
                    'mailbox.create' => $this->capability($context, MailProvider::class)->createMailbox($ref, ['address' => $p('address'), 'password' => $p('password'), 'name' => $p('name'), 'quota_mb' => $p('quota_mb', 2048)]),
                    'mailbox.update' => $this->capability($context, MailProvider::class)->updateMailbox(new ResourceRef('mailbox', (string) $p('remote_id'), $ref->node, ['client_id' => $ref->meta['client_id'] ?? null], $ref->serviceId), (array) $p('changes', [])),
                    'mailbox.delete' => $this->capability($context, MailProvider::class)->deleteMailbox(new ResourceRef('mailbox', (string) $p('remote_id'), $ref->node, [], $ref->serviceId)),
                    'alias.create' => $this->capability($context, MailProvider::class)->createAlias($ref, ['source' => $p('source'), 'destination' => $p('destination')]),
                    'alias.delete' => $this->capability($context, MailProvider::class)->deleteAlias(new ResourceRef('mail_alias', (string) $p('remote_id'), $ref->node, [], $ref->serviceId)),
                    'sending.set' => (function () use ($context, $ref, $p) {
                        // in every domain the service has mail in: a receive-only mode that covers half of them is
                        // still a relay, and the customer who switched sending off believes all of it stopped
                        $mail = $this->capability($context, MailProvider::class);
                        $result = null;
                        foreach (MailDomains::refsOf($this->service($context)) ?: [$ref] as $domain) {
                            $result = $mail->setSendingEnabled($domain, (bool) $p('enabled', true));
                        }

                        return $result;
                    })(),
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
                        $service = $this->service($context);
                        $row = Backup::query()->where('service_id', $service->id)->where(fn ($q) => $q->where('id', (string) $p('remote_id'))->orWhere('remote_id', (string) $p('remote_id')))->first();
                        if ($row !== null && ($row->protected || $row->kind === 'final' || LegalHold::coversBackup($row))) { // asked again here: the row may have been protected since the request
                            throw new ProviderException((string) $context->instance()->provider, ProviderErrorCode::VALIDATION, 'The backup is protected and cannot be deleted.');
                        }
                        if ($row !== null && $row->remote_id === null && FinalArchive::isSet($row)) { // the platform's own set: it lives on the backup disk, the panel knows nothing about it
                            $context->container->make(FinalArchive::class)->deleteSet($row);
                            $row->forceFill(['state' => 'deleted', 'meta' => array_merge((array) $row->meta, ['deleted_at' => now()->toIso8601String(), 'deleted_by' => (string) ($context->actor->actorId ?? 'system')])])->save();

                            return ProviderResult::completed(null, ['deleted' => true, 'backup_id' => $row->id]);
                        }
                        $result = $this->capability($context, WebToolsProvider::class)->deleteBackup($ref, (string) $p('remote_id'));
                        Backup::query()->where('service_id', $service->id)->where('remote_id', (string) $p('remote_id'))->update(['state' => 'deleted']);

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
                $adapter = $this->capability($context, InfrastructureProvider::class);
                $service = $this->service($context);
                if ($adapter instanceof ComputeProvider && data_get($service->tags, 'suspension_was_running') === null) {
                    // a machine the customer had switched off themselves must not be started by the payment that lifts the suspension
                    $running = $adapter->getActualState($this->ref($context))->status === 'running';
                    $service->forceFill(['tags' => array_merge((array) $service->tags, ['suspension_was_running' => $running])])->save();
                }

                return $this->settle($adapter->suspend($this->ref($context)));
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
                $service = $this->service($context);
                $ref = $this->ref($context);
                $wasRunning = data_get($service->tags, 'suspension_was_running');
                if ($wasRunning !== null) {
                    $service->forceFill(['tags' => array_diff_key((array) $service->tags, ['suspension_was_running' => true])])->save();
                    if ($wasRunning === false) {
                        $ref = $ref->withMeta(['start' => false]); // released, not started: it was off before we touched it
                    }
                }

                return $this->settle($this->capability($context, InfrastructureProvider::class)->resume($ref));
            }
        };
    }

    /**
     * A suspended site is more than a stopped vhost: its cron jobs and its FTP accounts are switched off too, and remembered
     * (`SuspensionDepth`). The site is already down when this runs, so a panel that does not answer makes the step wait and
     * try again — it does not undo the suspension.
     */
    private function pauseExtrasStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Pozastavení úloh a přístupů';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                if (! in_array($service->family, ['web', 'managed', 'game'], true) || $context->get('identity_missing') === true) {
                    return StepResult::skip(); // nothing besides the service itself to pause
                }
                $paused = $context->container->make(SuspensionDepth::class)->pause($service, $context->adapter(), $this->ref($context));
                if ($paused['transient'] && (int) $context->operation->attempts < 4) { // a few tries; then the suspension stands and the errors are on record
                    return StepResult::fail('the panel did not answer while scheduled jobs and accesses were being paused: '.implode('; ', $paused['errors']), true, [], 60);
                }

                return StepResult::done(['paused' => array_map('count', array_intersect_key($paused, array_flip(SuspensionDepth::KINDS))), 'pause_errors' => $paused['errors']]);
            }
        };
    }

    private function resumeExtrasStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Obnovení úloh a přístupů';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                if (! in_array($service->family, ['web', 'managed', 'game'], true) || data_get($service->tags, SuspensionDepth::TAG) === null) {
                    return StepResult::skip(); // nothing was paused besides the service itself
                }
                $resumed = $context->container->make(SuspensionDepth::class)->resume($service, $context->adapter(), $this->ref($context));
                if ($resumed['transient'] && (int) $context->operation->attempts < 4) {
                    return StepResult::fail('the panel did not answer while scheduled jobs and accesses were being switched on again: '.implode('; ', $resumed['errors']), true, [], 60);
                }

                return StepResult::done(['resumed' => array_map('count', array_intersect_key($resumed, array_flip(SuspensionDepth::KINDS))), 'resume_errors' => $resumed['errors']]);
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

                // a web hosting holds several sites and the plan's space is divided between them: this site gets what the
                // plan leaves after the others, while the plan's own numbers travel on as the limits of the account
                $siteShare = in_array($service->family, ['web', 'managed'], true) ? ServiceSites::ownShare($service, (int) ($target['nvme_gb'] ?? 0)) : null;

                return $this->settle($infra->resize($this->ref($context), $context->spec($this->kindFor($service), [
                    'entitlements' => $target, 'vcpu' => (int) ($target['vcpu'] ?? 0) ?: null, 'ram_mb' => (int) ($target['ram_mb'] ?? 0) ?: null, 'nvme_gb' => (int) ($target['nvme_gb'] ?? 0) ?: null, 'cpu_limit' => ($target['cpu_class'] ?? 'shared') === 'dedicated' ? null : ((int) ($target['vcpu'] ?? 0) ?: null),
                    'limits' => (array) $context->desired('limits', []), 'php_version' => $context->desired('php_version'), 'site_nvme_gb' => $siteShare,
                ])), ['target_entitlements' => $target, 'site_nvme_gb' => $siteShare]);
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
                $tags = (array) ($service->tags ?? []);
                if ($context->get('site_nvme_gb') !== null) { // what of the plan's space this site holds after the change
                    $tags['sites'] = array_merge((array) ($tags['sites'] ?? []), ['quota_gb' => (int) $context->get('site_nvme_gb')]);
                }
                $service->forceFill(['tags' => $tags, 'entitlements' => array_replace((array) $service->entitlements, $target), 'desired_spec' => array_replace((array) $service->desired_spec, ['entitlements' => array_replace((array) $service->entitlements, $target)])])->save();
                ServiceSites::spread($service); // the plan is the group's: the sites it carries change with it, each keeping its own share
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

    /**
     * A cancelled service takes its add-ons with it. They have no resource of their own, so nothing here would ever have
     * stopped them: the add-on stayed ACTIVE and its subscription billed on after the service it belonged to was gone.
     */
    private function cancelAddonsStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Zrušení doplňků';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $addons = Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)
                    ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->get();
                if ($addons->isEmpty()) {
                    return StepResult::skip();
                }
                $services = $context->container->make(ServiceService::class);
                $addonsApi = $context->container->make(Addons::class);
                $cancelled = [];
                foreach ($addons as $addon) {
                    $addonsApi->revoke($service, $addon);
                    $addon->forceFill(['state' => ServiceStateMachine::SUSPENDING])->save(); // ACTIVE → SUSPENDING → SUSPENDED, as every other service goes
                    $services->settleTransient($addon, ServiceStateMachine::SUSPENDED, $context->actor, 'the service it belonged to was cancelled', $context->operation, null, 'service.deactivated');
                    $addon->forceFill(['terminate_at' => $service->terminate_at ?? now()->addDays($context->container->make(DeletionPolicy::class)->graceDays())])->save();
                    Subscription::query()->where('service_id', $addon->id)->whereNotIn('state', [Subscription::CANCELLED])->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);
                    $cancelled[] = $addon->product_key;
                }

                return StepResult::done(['addons_cancelled' => $cancelled]);
            }
        };
    }

    /**
     * A web hosting plan sells mailboxes („5 schránek“, „50 schránek“) and the site saga never made a mail domain for
     * them: the panel puts a mailbox inside one, so the first mailbox of a web service had nowhere to go. It is made
     * here, once, when the customer asks for the first address — with the DKIM key the panel generates, and with the
     * MX, SPF, DMARC and DKIM records published the same way the mail service publishes them, wherever the zone is
     * ours. A domain another service already holds as its mail domain is used as it is, never taken over.
     */
    private function ensureMailDomainStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Poštovní doména webu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                if (! in_array($service->family, ['web', 'managed'], true)) {
                    return StepResult::skip(); // a mail service's domain is its own primary resource
                }
                $site = $this->binding($context);
                // the domain of the address being made, not the site's name: a mail domain made for the wrong name is a
                // node that never accepts mail for the address the customer was just handed (MailDomains)
                $address = (string) ($context->desired('address') ?? $context->desired('source') ?? '');
                $domain = MailDomains::domainOf($address) ?: mb_strtolower((string) ($site->meta['domain'] ?? $service->spec('domain', $service->hostname)));
                if ($domain === '') {
                    return StepResult::fail('the service has no domain to make mail for', false);
                }
                if (MailDomains::forDomain($service, $domain) !== null) {
                    return StepResult::skip(); // the service already has mail for this domain
                }
                $mail = $this->capability($context, MailProvider::class);
                $result = $mail->createMailDomain($context->spec('mail_domain', ['domain' => $domain, 'entitlements' => (array) $service->entitlements]));
                if ($result->ref === null) {
                    return StepResult::fail('the panel returned no mail domain', true, [], 60);
                }
                $context->bind($context->instance(), 'mail_domain', $result->ref->remoteId, $result->ref->node, $result->ref->meta + ['domain' => $domain], ['managed_by' => 'onhost']);
                $meta = (array) $result->ref->meta;
                MailDomain::query()->updateOrCreate(['service_id' => $service->id, 'domain' => $domain], [
                    'remote_client_id' => isset($meta['client_id']) ? (int) $meta['client_id'] : null,
                    'remote_id' => (int) $result->ref->remoteId, 'remote_node' => $result->ref->node,
                    'dkim_selector' => $meta['dkim_selector'] ?? null, 'dkim_public' => $meta['dkim_public'] ?? null, 'sending_enabled' => true, 'state' => 'active',
                ]);
                // the records are how mail finds the node, but they are not why the customer asked: a zone that is not
                // ours, or a DNS that will not take them now, leaves the mailbox standing and the records on record
                $records = self::mailRecords($context, $domain, $meta);
                $zone = DnsZone::query()->where('name', $domain)->where('organization_id', $service->organization_id)->where('state', 'active')->first();
                $version = null;
                $error = null;
                try {
                    $version = $zone === null ? null : $context->container->make(DnsService::class)->syncSystemRecords($zone, $records, $context->actor, "mail for {$service->id}");
                } catch (Throwable $e) {
                    $error = mb_substr($e->getMessage(), 0, 200);
                }

                return $this->settle($result, array_filter([
                    'mail_domain_id' => $result->ref->remoteId, 'mail_domain' => $domain, 'dns_version' => $version?->version,
                    'dns_records_required' => $version === null ? $records : null, 'dns_error' => $error,
                ], fn ($value) => $value !== null));
            }

            /**
             * @param  array<string,mixed>  $meta
             * @return list<array<string,mixed>>
             */
            private static function mailRecords(StepContext $context, string $domain, array $meta): array
            {
                $host = (string) ($context->instance()->option('mail_host') ?: config('onhost.dns.mail_host'));
                $records = [
                    ['name' => '@', 'type' => 'MX', 'content' => $host.'.', 'ttl' => 3600, 'prio' => 10],
                    ['name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 mx include:'.config('onhost.dns.spf_include').' -all', 'ttl' => 3600],
                    ['name' => '_dmarc', 'type' => 'TXT', 'content' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@'.$domain, 'ttl' => 3600],
                ];
                $records = array_merge($records, MailSettings::autoconfigRecords($domain)); // Thunderbird and Outlook find the settings themselves
                if (! empty($meta['dkim_selector']) && ! empty($meta['dkim_public'])) {
                    $records[] = ['name' => $meta['dkim_selector'].'._domainkey', 'type' => 'TXT', 'content' => 'v=DKIM1; k=rsa; p='.preg_replace('/\s+|-----[A-Z ]+-----/', '', (string) $meta['dkim_public']), 'ttl' => 3600];
                }

                return $records;
            }
        };
    }

    /** The mail domain a web service was given for its mailboxes goes with the service, like every other resource of it. */
    private function removeMailDomainStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Odstranění poštovní domény webu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $bindings = MailDomains::bindingsOf($service);
                if (! in_array($service->family, ['web', 'managed'], true) || $bindings->isEmpty()) {
                    return StepResult::skip(); // a mail service's own domain goes with its own terminate step
                }
                $mail = $this->capability($context, MailProvider::class);
                $result = null;
                foreach ($bindings as $binding) { // every domain the service has mail in, not only the first of them
                    $result = $mail->deleteMailDomain($binding->ref());
                    $binding->delete();
                }
                MailDomain::query()->where('service_id', $service->id)->update(['state' => 'deleted']);

                return $this->settle($result, ['mail_domains_removed' => $bindings->count()]);
            }
        };
    }

    /**
     * The further sites the service carries — the test copy, and the sites its plan sells — are cancelled with it.
     * Each one goes through its own cancellation, so each one gets its own final archive before anything is removed;
     * a site whose cancellation is already running is left alone. Without this step they served on for ever, with the
     * customer's files and databases on the node, while the cancellation dialog promised the customer they would go.
     */
    private function endIncludedServicesStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Ukončení dalších webů služby';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $included = IncludedServices::of($service)->filter(fn (Service $child) => $child->terminate_at === null);
                if ($included->isEmpty()) {
                    return StepResult::skip();
                }
                $services = $context->container->make(ServiceService::class);
                $staging = $context->container->make(StagingService::class);
                $ended = [];
                $errors = [];
                foreach ($included as $child) {
                    try {
                        if ($child->primaryBinding() === null) { // provisioning never reached the node: there is nothing to archive and nothing to remove
                            $child->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();
                            $ended[] = (string) ($child->hostname ?: $child->name);

                            continue;
                        }
                        $services->requestAction($child, 'terminate', CommandContext::system('cancelled with '.$service->id), "included:terminate:{$context->operation->id}:{$child->id}", ['reason' => 'zrušena služba, ke které web patřil']);
                        $ended[] = (string) ($child->hostname ?: $child->name);
                    } catch (Throwable $e) {
                        $errors[] = ($child->hostname ?: $child->id).': '.$e->getMessage();
                    }
                }
                $link = $staging->link($service); // the test copy is cancelled above; its link must not outlive it
                if ($link !== null && $included->contains(fn (Service $child) => $child->id === $link->staging_service_id)) {
                    $link->forceFill(['state' => 'deleted'])->save();
                    $link->delete();
                }
                if ($errors !== [] && (int) $context->operation->attempts < 4) { // a few tries; then the cancellation stands and the reasons are on record
                    return StepResult::fail('a site of the service could not be cancelled: '.implode('; ', $errors), true, [], 60);
                }

                return StepResult::done(['included_ended' => $ended, 'included_errors' => $errors]);
            }
        };
    }

    /**
     * Suspension reaches the sites the service carries too: an unpaid web hosting must not keep serving from its test
     * copy. Only the sites this step switched off are switched back on, so one the customer had suspended themselves
     * stays suspended (the same rule `SuspensionDepth` follows for cron jobs and FTP accounts).
     */
    private function holdIncludedServicesStep(bool $suspend): ServiceStep
    {
        return new class($suspend) extends ServiceStep
        {
            public function __construct(private readonly bool $suspend) {}

            public function label(): string
            {
                return $this->suspend ? 'Pozastavení dalších webů služby' : 'Obnovení dalších webů služby';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $services = $context->container->make(ServiceService::class);
                $touched = [];
                $errors = [];
                foreach (IncludedServices::of($service) as $child) {
                    $heldBy = (string) data_get($child->tags, 'included.held_by', '');
                    $wanted = $this->suspend
                        ? ($child->state === ServiceStateMachine::ACTIVE || $child->state === ServiceStateMachine::DEGRADED)
                        : ($child->state === ServiceStateMachine::SUSPENDED && $heldBy !== '' && $child->terminate_at === null);
                    if (! $wanted) {
                        continue;
                    }
                    try {
                        // the platform lifts exactly the hold it put on: a site the customer or an abuse case stopped stays stopped
                        $lift = $this->suspend ? null : ((string) data_get($child->tags, 'included.held_hold', '') ?: null);
                        $services->requestAction($child, $this->suspend ? 'suspend' : 'resume', CommandContext::system(($this->suspend ? 'suspended' : 'resumed').' with '.$service->id), 'included:'.($this->suspend ? 'suspend' : 'resume').":{$context->operation->id}:{$child->id}", array_filter(['reason' => 'stav služby, ke které web patří', 'lift' => $lift]));
                        $child->refresh(); // its own operation may have run already (and written to the same row)
                        $tags = (array) ($child->tags ?? []);
                        $tags['included'] = array_filter(array_merge((array) ($tags['included'] ?? []), $this->suspend
                            ? ['held_by' => $service->id, 'held_hold' => SuspensionHold::holds($child)[0] ?? null]
                            : ['held_by' => null, 'held_hold' => null]), fn ($v) => $v !== null);
                        $child->forceFill(['tags' => $tags])->save();
                        $touched[] = (string) ($child->hostname ?: $child->name);
                    } catch (Throwable $e) {
                        $errors[] = ($child->hostname ?: $child->id).': '.$e->getMessage();
                    }
                }
                if ($touched === [] && $errors === []) {
                    return StepResult::skip();
                }

                return StepResult::done([($this->suspend ? 'included_suspended' : 'included_resumed') => $touched, 'included_errors' => $errors]);
            }
        };
    }

    /** A cancelled add-on gives the parent back what it gave it: the extra address, the mailboxes, the CDN, the backup schedule. */
    private function detachAddonStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Odpojení doplňku';
            }

            public function run(StepContext $context): StepResult
            {
                $addon = $this->service($context);
                $parent = Service::query()->find((string) data_get($addon->tags, 'parent_service_id', ''));
                if ($parent === null) {
                    return StepResult::done(['detached' => 'no parent']); // the parent is gone already; there is nothing to give back
                }
                if (data_get($addon->tags, 'addon.revoked_at') !== null) {
                    return StepResult::done(['detached' => 'already']);
                }
                $result = $context->container->make(Addons::class)->revoke($parent, $addon);

                return StepResult::done(['detached' => true, 'parent_service_id' => $parent->id, 'restored' => $result['restored'], 'kept' => $result['kept'], 'backup_policy' => $result['backup_policy']]);
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
                $kind = (string) $context->desired('kind', 'manual');
                $days = (int) $context->desired('retention_days', 30);
                if (ServiceBackups::platformMade($service, $context->adapter())) {
                    // a web backup is files + every database, pulled off the node (ServiceBackups): fresh and whole, or the step fails
                    try {
                        $backup = $context->container->make(ServiceBackups::class)->take($service, $context->adapter(), $this->ref($context), $context->actor, $context->operation->id, $kind, $days, (bool) $context->desired('protected', false));
                    } catch (DomainError $e) {
                        return StepResult::fail('záloha se nepodařila: '.$e->getMessage(), $e->status >= 500, ['error' => $e->error], 120);
                    } catch (ProviderException $e) {
                        return self::fromProviderException($e);
                    } catch (Throwable $e) {
                        return StepResult::fail('záloha se nepodařila: '.$e->getMessage(), true, [], 120);
                    }

                    return StepResult::done(['backup_id' => $backup->id, 'backup_set' => data_get($backup->meta, 'set'), 'backup_bytes' => $backup->size_bytes]);
                }
                $adapter = $this->capability($context, BackupCapable::class);
                $backup = Backup::query()->firstOrCreate(['operation_id' => $context->operation->id], ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => $kind, 'state' => 'running', 'started_at' => now(), 'retention_until' => now()->addDays($days), 'protected' => (bool) $context->desired('protected', false)]);

                // what the panel already holds is written down first: the backup of this run is the one that was not there before
                $before = array_map(fn (array $b) => (string) $b['remote_id'], $adapter->listBackups($this->ref($context)));
                $result = $adapter->backup($this->ref($context), (array) $context->desired('policy', []));
                $known = ['backup_id' => $backup->id, 'backup_before' => $before, 'backup_named' => (string) ($result->data['backup_uuid'] ?? '')];
                if (! $result->isAsync()) { // panels that archive synchronously: the row is complete right away
                    return $this->adopt($context, $backup, $before, $known['backup_named']) ?? StepResult::done($known + ['backup_remote_id' => $backup->remote_id]);
                }

                return $this->settle($result, $known);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                $backup = Backup::query()->findOrFail((string) $context->get('backup_id'));

                return $this->adopt($context, $backup, (array) $context->get('backup_before', []), (string) $context->get('backup_named', ''), $status->detail) ?? StepResult::done(['backup_remote_id' => $backup->remote_id]);
            }

            /**
             * The archive this run made: the one the panel named in its answer, else the newest that was not there before.
             * "The newest one" alone was last night's archive whenever the panel made none — a backup that never happened,
             * shown as done. Null when the row is complete, a failure otherwise.
             *
             * @param  list<string>  $before
             * @param  array<string,mixed>  $finished  what the panel said about the task it finished
             */
            private function adopt(StepContext $context, Backup $backup, array $before, string $named, array $finished = []): ?StepResult
            {
                $list = collect($this->capability($context, BackupCapable::class)->listBackups($this->ref($context)));
                $made = $named !== '' ? $list->firstWhere('remote_id', $named) : $list->reject(fn (array $b) => in_array((string) $b['remote_id'], $before, true))->sortByDesc('created_at')->first();
                if (! is_array($made) && $named !== '' && (string) ($finished['uuid'] ?? '') === $named) { // a long list shows one page; the finished task named the archive itself
                    $made = ['remote_id' => $named, 'size_bytes' => $finished['bytes'] ?? null, 'verified' => true];
                }
                if (! is_array($made) || (string) ($made['remote_id'] ?? '') === '') {
                    return StepResult::fail('the panel reports the backup done, but its list holds no archive that was not there before', true, ['backup_unconfirmed' => true], 120);
                }
                $backup->forceFill(['state' => 'completed', 'finished_at' => now(), 'remote_id' => (string) $made['remote_id'], 'size_bytes' => $made['size_bytes'] ?? null,
                    'verify_status' => isset($made['verified']) ? ($made['verified'] ? 'ok' : 'pending') : null, 'remote_datastore' => $context->instance()->option('backup_storage')])->save();

                return null;
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
                if ($backup->service_id !== $service->id || ($backup->remote_id === null && ! FinalArchive::isSet($backup))) {
                    return StepResult::fail('Backup does not belong to this service or has no remote id', false);
                }
                $job = RestoreJob::query()->firstOrCreate(['operation_id' => $context->operation->id], ['backup_id' => $backup->id, 'service_id' => $service->id, 'organization_id' => $service->organization_id, 'target' => (string) $context->desired('target', 'in_place'), 'state' => 'running', 'requested_by' => $context->actor->actorId, 'started_at' => now()]);
                if ($backup->remote_id === null) { // the platform's own set (ServiceBackups): files over the site root, every dump into its database
                    try {
                        $restored = $context->container->make(ServiceBackups::class)->restoreInPlace($service, $this->capability($context, WebToolsProvider::class), $this->ref($context), $backup);
                    } catch (DomainError $e) {
                        return StepResult::fail('obnova se nepodařila: '.$e->getMessage(), $e->status >= 500, ['error' => $e->error], 120);
                    } catch (ProviderException $e) {
                        return self::fromProviderException($e);
                    }
                    $job->forceFill(['state' => 'completed', 'finished_at' => now(), 'duration_seconds' => $job->started_at ? (int) now()->diffInSeconds($job->started_at, true) : null, 'result' => $restored])->save();

                    return StepResult::done(['restore_job_id' => $job->id, 'restored' => true, 'restored_files' => $restored['files'], 'restored_databases' => count($restored['databases'])]);
                }
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

    /**
     * What a destructive action replaces is kept first (audit §5ag). A restore writes a backup over the service, a
     * rollback throws away everything since the snapshot, a game reinstall rewrites the server's files — and none of
     * them kept a copy of what they destroyed. The customer who restored the wrong backup, or rolled back a day too
     * far, had no way back: the panel only told them to take a backup themselves.
     *
     * A server takes a provider snapshot (instant, and the rollback stops the machine anyway); everything else takes
     * the same archive a cancellation takes — files and every database, fresh or the step fails. The copy is
     * protected and kept for the retention the deletion policy sets, so nothing prunes it while it still matters.
     */
    /**
     * Whether what this dump becomes still fits in what the plan sells (H456).
     *
     * An import that runs out of disk half way takes the database with it, and the dump is not the size of what it
     * becomes — the rows are written again, the indexes are built beside them and the engine needs room while it
     * loads. Refused here, before the file is carried to the node and before a copy is made, so nothing has changed.
     */
    private function importRoomStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Kontrola místa pro import';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $file = $context->container->make(WebFileStore::class)->uploadPath($service, (string) $context->desired('upload_id'));
                $dump = DatabaseImport::dumpBytes($file);
                if ($dump <= 0) {
                    return StepResult::fail('nahraný soubor už na disku není; nahrajte ho prosím znovu', false, ['error' => 'upload_missing']);
                }
                try {
                    $quotas = $context->container->make(ServiceFeatures::class)->resources($service, 'quotas', true, []);
                } catch (Throwable) {
                    $quotas = []; // the panel does not measure disk, or is not answering: the node's own guard is still behind this
                }
                try {
                    $room = DatabaseImport::assertRoom($dump, (array) $quotas);
                } catch (DomainError $e) {
                    return StepResult::fail($e->getMessage(), false, ['error' => $e->error] + $e->extra);
                }

                return StepResult::done(['dump_bytes' => $dump, 'needs_bytes' => $room['needed'], 'free_bytes' => $room['free'], 'room_checked' => $room['checked']]);
            }
        };
    }

    /**
     * Does the archive really become a database again? (H458)
     *
     * Restored into databases of its own, compared by a round trip, and the test databases are removed whatever
     * happens — the live data is never the thing being experimented on.
     */
    private function restoreTestStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Test obnovy ze zálohy';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $backup = Backup::query()->where('service_id', $service->id)->find((string) $context->desired('backup_id'));
                if ($backup === null || $backup->state !== 'completed') {
                    return StepResult::fail('zálohu k otestování se nepodařilo najít', false, ['error' => 'backup_not_found']);
                }
                $tester = $context->container->make(RestoreTest::class);
                try {
                    $result = $tester->run($service, $context->adapter(), $this->ref($context), $backup);
                } catch (DomainError $e) {
                    return StepResult::fail($e->getMessage(), $e->status >= 500, ['error' => $e->error]);
                } catch (ProviderException $e) {
                    return self::fromProviderException($e);
                }
                $tester->record($service, $backup, $result);

                return StepResult::done(['restore_test' => $result['outcome'], 'databases' => $result['databases'], 'problems' => $result['problems']]);
            }
        };
    }

    private function safetyCopyStep(string $kind, bool $onlyTargetDatabase = false): ServiceStep
    {
        return new class($kind, $onlyTargetDatabase) extends ServiceStep
        {
            public function __construct(private readonly string $kind, private readonly bool $onlyTargetDatabase = false) {}

            public function label(): string
            {
                return 'Záloha před přepsáním';
            }

            /** The hypervisor finished the snapshot this step started: the copy is real, so the row says so. */
            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                $backup = Backup::query()->where('operation_id', $context->operation->id)->where('kind', $this->kind)->first();
                $backup?->forceFill(['state' => 'completed', 'finished_at' => now(), 'verified_at' => now(), 'verify_status' => 'ok'])->save();

                return StepResult::done(['safety_copy_id' => $backup?->id, 'safety_copy' => $backup?->remote_id]);
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $existing = Backup::query()->where('operation_id', $context->operation->id)->where('kind', $this->kind)->first();
                if ($existing !== null) {
                    // the step is entered again after the provider task it waited for finished — one copy per operation, never two
                    if ($existing->state !== 'completed') {
                        $existing->forceFill(['state' => 'completed', 'finished_at' => now(), 'verified_at' => now(), 'verify_status' => 'ok'])->save();
                    }

                    return StepResult::done(['safety_copy_id' => $existing->id, 'safety_copy' => $existing->remote_id]);
                }
                $days = $context->container->make(DeletionPolicy::class)->retentionDays();
                try {
                    if (in_array($service->family, ['cloud', 'data'], true)) {
                        $name = mb_substr('onhost-'.str_replace('_', '-', $this->kind).'-'.now()->format('ymdHis'), 0, 40);
                        $result = $this->capability($context, ComputeProvider::class)->snapshot($this->ref($context), $name, 'ONhost: stav před akcí '.$context->desired('action'));
                        $backup = Backup::query()->create([
                            'service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id,
                            'kind' => $this->kind, 'state' => $result->isAsync() ? 'running' : 'completed', 'started_at' => now(), 'finished_at' => $result->isAsync() ? null : now(),
                            'protected' => true, 'operation_id' => $context->operation->id, 'remote_id' => $name,
                            'retention_until' => now()->addDays($days), 'meta' => ['reason' => $this->kind, 'snapshot' => $name],
                        ]);

                        return $result->isAsync()
                            ? $this->settle($result, ['safety_copy_id' => $backup->id, 'safety_copy' => $name])
                            : StepResult::done(['safety_copy_id' => $backup->id, 'safety_copy' => $name]);
                    }
                    $backup = $context->container->make(ServiceBackups::class)
                        ->take($service, $context->adapter(), $this->ref($context), $context->actor, $context->operation->id, $this->kind, $days, protected: true,
                            onlyDatabase: $this->onlyTargetDatabase ? (string) $context->desired('remote_id') : null);
                } catch (DomainError $e) {
                    return StepResult::fail('zálohu před přepsáním se nepodařilo vytvořit: '.$e->getMessage().'; nic nebylo přepsáno', $e->status >= 500, ['error' => $e->error], 120);
                } catch (ProviderException $e) {
                    return self::fromProviderException($e);
                } catch (Throwable $e) {
                    return StepResult::fail('zálohu před přepsáním se nepodařilo vytvořit: '.$e->getMessage().'; nic nebylo přepsáno', true, [], 120);
                }

                return StepResult::done(['safety_copy_id' => $backup->id, 'safety_copy_set' => data_get($backup->meta, 'set')]);
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
