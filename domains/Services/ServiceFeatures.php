<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Catalog\WafLevels;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Services\Web\CdnService;
use Onhost\Domain\Services\Web\CertificateService;
use Onhost\Domain\Services\Web\DeployService;
use Onhost\Domain\Services\Web\ImportService;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Domain\Services\Web\StagingService;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Domain\Services\Web\WordPressService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\ConsoleCapable;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\KubernetesProvider;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\MailToolsProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\PowerCapable;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/**
 * What a customer can do with a service — derived from the executor's adapter (what the panel behind the service
 * offers), the plan's entitlements (limits) and the service state. The customer surfaces render their tabs from this
 * catalogue and never learn which vendor panel is behind it; the same keys drive `ServiceService::requestAction`.
 *
 *  features(): key => {enabled, limit?, options?}      resources(): live listings (databases, cron, ftp, …)
 */
final class ServiceFeatures
{
    /** Customer actions accepted by POST /v1/services/{id}/actions, grouped by the feature that enables them. */
    public const ACTIONS = [
        'power' => ['power'], 'suspend' => ['suspend'], 'resume' => ['resume'], 'resize' => ['resize'], 'terminate' => ['terminate'],
        'backups' => ['backup'], 'restore' => ['restore'], 'snapshots' => ['snapshot', 'rollback_snapshot', 'snapshot.delete'],
        'php' => ['php.set'], 'databases' => ['database.create', 'database.delete'], 'ftp' => ['ftp.create', 'ftp.delete', 'ftp.password'],
        'ssl' => ['ssl.issue'], 'https' => ['https.force'], 'cron' => ['cron.create', 'cron.delete'], 'subdomains' => ['subdomain.add', 'subdomain.remove'],
        'redirects' => ['redirect.set'], 'firewall' => ['firewall.apply'], 'command' => ['command.send'], 'schedules' => ['schedule.create'],
        'mailboxes' => ['mailbox.create', 'mailbox.update', 'mailbox.delete'], 'aliases' => ['alias.create', 'alias.delete'], 'sending' => ['sending.set'],
        // extended web tabs (what the prototype's workbench offers and the panels really support)
        'errpages' => ['errpages.set'], 'directives' => ['directives.set'], 'protected' => ['folder.protect', 'folder.unprotect'], 'db_users' => ['dbuser.create', 'dbuser.password', 'dbuser.delete'],
        'shell' => ['shell.create', 'shell.key', 'shell.delete'], 'stats' => ['stats.set'], 'ssl_upload' => ['ssl.upload'], 'files' => ['file.mkdir', 'file.delete', 'file.save'], 'apps' => ['app.install'],
        // tools on top of the panels (WebToolsProvider): terminal, PHP settings, security rules, HTTP/3, cron editing, database transfers and access, backups, files, Node projects, wildcard certificates
        'terminal' => ['command.run'], 'php_settings' => ['php.settings'], 'security' => ['security.set'], 'http3' => ['http3.set'], 'cron_edit' => ['cron.update', 'cron.run'],
        'db_export' => ['database.export', 'database.import'], 'db_access' => ['database.access'], 'backup_delete' => ['backup.delete'], 'files_advanced' => ['file.rename', 'file.copy', 'file.chmod', 'file.archive', 'file.extract'],
        'node_projects' => ['node.create', 'node.action'], 'proxy' => ['proxy.create', 'proxy.delete', 'proxies.set'], 'default_docs' => ['index.set'], 'ssl_wildcard' => ['ssl.wildcard'],
        // platform features around the site (workflows of their own): staging, git deploy, WordPress toolkit, CDN, imports
        'staging' => ['staging.create', 'staging.refresh', 'staging.push', 'staging.delete'], 'deploy' => ['deploy.run', 'deploy.rollback'], 'wordpress' => ['wp.install', 'wp.update', 'wp.cache', 'wp.plugin'],
        'cdn' => ['cdn.enable', 'cdn.disable', 'cdn.purge'], 'import' => ['import.run'], 'sites' => ['site.create', 'site.delete'],
        // mail tools (MailToolsProvider)
        'forwards' => ['forward.create', 'forward.delete'], 'catchall' => ['catchall.set'], 'autoresponder' => ['autoresponder.set'], 'spam' => ['spam.policy', 'spam.list.add', 'spam.list.delete'],
        'mail_filters' => ['filter.create', 'filter.delete'], 'mailing_lists' => ['list.create', 'list.delete'], 'fetchmail' => ['fetchmail.create', 'fetchmail.delete'], 'mail_backups' => ['mailbox.restore'], 'mail_backup_now' => ['mailbox.backup'],
        // game tools (GameToolsProvider): startup variables and image, server settings, schedule housekeeping, databases, collaborators, files, ports, backup housekeeping, the customer's panel account
        'startup' => ['variable.set', 'image.set'], 'game_settings' => ['rename', 'reinstall'], 'schedule_tools' => ['schedule.delete', 'schedule.toggle', 'schedule.run'], 'game_databases' => ['gamedb.create', 'gamedb.rotate', 'gamedb.delete'],
        'subusers' => ['subuser.create', 'subuser.delete'], 'game_files' => ['gfile.save', 'gfile.upload', 'gfile.delete', 'gfile.mkdir', 'gfile.rename'], 'allocations' => ['allocation.add', 'allocation.primary', 'allocation.remove'],
        'backup_tools' => ['gbackup.delete', 'gbackup.lock'], 'panel_access' => ['panel.password'],
        // a virtual server's own access (ComputeProvider): new SSH keys and/or a new password for the administrator, through cloud-init
        'vm_access' => ['access.reset'],
        'vm_rdns' => ['rdns.set'],
        'vm_rescue' => ['rescue.start', 'rescue.stop'],
    ];

    public const RESOURCES = [
        'databases', 'ftp', 'cron', 'subdomains', 'certificate', 'redirect', 'php', 'snapshots', 'mailboxes', 'aliases', 'dkim', 'firewall', 'site_settings', 'protected_folders', 'db_users', 'shell_users', 'files', 'apps',
        'tools', 'php_settings', 'security', 'http_versions', 'cron_logs', 'database_access', 'quotas', 'node_projects', 'staging', 'deploy', 'deployments', 'wordpress', 'monitoring', 'monitoring_samples', 'certificates', 'cdn', 'imports',
        'mail_forwards', 'mail_catchall', 'mail_autoresponder', 'mail_spam', 'mail_spam_lists', 'mail_filters', 'mail_lists', 'mail_fetchmail', 'mail_backups', 'mail_usage', 'mail_access', 'proxies', 'default_docs',
        'status', 'server_detail', 'startup', 'schedules', 'game_databases', 'subusers', 'game_files', 'allocations', 'panel_access', 'sites',
    ];

    /** Game actions the customer may take (ServiceActionCommand risk): what is destructive needs a fresh step-up. */
    public const GAME_ACTIONS = ['variable.set', 'image.set', 'rename', 'reinstall', 'schedule.delete', 'schedule.toggle', 'schedule.run', 'gamedb.create', 'gamedb.rotate', 'gamedb.delete', 'subuser.create', 'subuser.delete', 'gfile.save', 'gfile.upload', 'gfile.delete', 'gfile.mkdir', 'gfile.rename', 'allocation.add', 'allocation.primary', 'allocation.remove', 'gbackup.delete', 'gbackup.lock', 'panel.password'];

    /** resource kinds answered by the platform's own records (short cache: the panel refreshes them right after an action) */
    public const PLATFORM_RESOURCES = ['staging', 'deploy', 'deployments', 'wordpress', 'monitoring', 'monitoring_samples', 'certificates', 'cdn', 'imports'];

    public function __construct(private readonly ProviderRegistry $providers, private readonly CacheRepository $cache) {}

    /** Whether the platform holds a reverse zone for any address of this service — without one no PTR can be published. */
    private static function reverseZoneAvailable(Service $service): bool
    {
        foreach (IpAddress::query()->where('service_id', $service->id)->where('state', 'allocated')->get() as $address) {
            if (app(DnsService::class)->reverseZoneFor((string) $address->address) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why a feature is not there, so the panel can say it instead of showing a dead button (Brain cards H412, H413).
     * The words are the UI's to translate; the platform only says which of the four it is.
     */
    public const REASON_STATE = 'state';        // the service is not running right now (suspended, being cancelled, not provisioned yet)

    public const REASON_PERMISSION = 'permission'; // this person may see the service, not do this to it

    /**
     * What the service can do. With `$actor` the answer is what **that person** can do with it: a collaborator who was
     * given read-only access used to be shown every button and learnt the truth only as a 403 when they pressed one
     * (audit §5ah). The gate is the same permission the command bus will ask for, so the panel and the server cannot
     * disagree. Without `$actor` nothing changes: the internal gates ask what the SERVICE offers, not who is asking.
     *
     * @return array<string, array{enabled:bool, limit?:int|null, options?:mixed, reason?:string}>
     */
    public function features(Service $service, ?User $actor = null): array
    {
        $out = $this->offered($service);
        if ($actor === null) {
            return $out;
        }
        $authorizer = app(Authorizer::class);
        $scope = CommandScope::resource($service->id, (string) $service->organization_id);
        $allowed = [];
        foreach (self::ACTIONS as $feature => $actions) {
            if (empty($out[$feature]['enabled'])) {
                continue;
            }
            foreach ($actions as $action) {
                $permission = ServiceActionCommand::permissionFor($action);
                $allowed[$permission] ??= $authorizer->can($actor, $permission, $scope);
                if ($allowed[$permission]) {
                    continue 2; // one action of this feature is enough for the feature to stay
                }
            }
            $out[$feature] = ['enabled' => false, 'reason' => self::REASON_PERMISSION];
        }

        return $out;
    }

    /** @return array<string, array{enabled:bool, limit?:int|null, options?:mixed, reason?:string}> */
    private function offered(Service $service): array
    {
        $ent = (array) $service->entitlements;
        $active = in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true);
        $adapter = $this->adapter($service);
        $executor = (string) data_get($service->desired_spec, 'executor', '');
        // a feature the service HAS but cannot use right now says so: "the service is suspended" is not "the panel cannot do this" (H413)
        $on = fn (bool $enabled, ?int $limit = null, mixed $options = null) => array_filter([
            'enabled' => $enabled && $active, 'limit' => $limit, 'options' => $options,
            'reason' => $enabled && ! $active ? self::REASON_STATE : null,
        ], fn ($v) => $v !== null);

        $out = [
            'usage' => $on(true), 'operations' => $on(true), 'suspend' => $on($service->state === ServiceStateMachine::ACTIVE),
            'resume' => ['enabled' => $service->state === ServiceStateMachine::SUSPENDED], 'terminate' => ['enabled' => ! $service->legal_hold && $service->state !== ServiceStateMachine::TERMINATED],
        ];
        switch ($service->family) {
            case 'web':
            case 'managed':
                $site = $adapter instanceof WebHostingProvider ? $adapter->siteFeatures() : self::fallbackSite($executor);
                $flag = fn (string $k) => (bool) ($site[$k] ?? false);
                $mailTools = $flag('mail') && (int) ($ent['mailboxes'] ?? 0) > 0 && $adapter instanceof MailToolsProvider;
                $out += [
                    'site' => $on(true), 'php' => $on($flag('php')), 'databases' => $on($flag('databases'), (int) ($ent['databases'] ?? 1)), 'ftp' => $on($flag('ftp'), (int) ($ent['ftp_accounts'] ?? 5)),
                    'ssl' => $on($flag('ssl')), 'https' => $on($flag('https')), 'cron' => $on($flag('cron'), (int) ($ent['cron_jobs'] ?? 10)), 'logs' => $on($flag('logs')),
                    'backups' => $on($flag('backups'), (int) ($ent['backup_days'] ?? 7)), 'restore' => $on($flag('restore')), 'subdomains' => $on($flag('subdomains'), (int) ($ent['aliases'] ?? 0) ?: null),
                    // „10 webů“ on the price list means ten SITES of their own, not ten names on one site (`ServiceSites`)
                    'sites' => $on(ServiceSites::limit($service) > 1 && ! IncludedServices::isIncluded($service), ServiceSites::limit($service)),
                    'redirects' => $on($flag('redirects')), 'ssh' => $on($flag('ssh') && ! empty($ent['ssh'])), 'mail' => $on($flag('mail') && (int) ($ent['mailboxes'] ?? 0) > 0, (int) ($ent['mailboxes'] ?? 0)),
                    // „5 schránek“ is a number the customer has to be able to use: the mail domain is made at the first address
                    'mailboxes' => $on($flag('mail') && (int) ($ent['mailboxes'] ?? 0) > 0 && $adapter instanceof MailProvider, (int) ($ent['mailboxes'] ?? 0)),
                    'aliases' => $on($flag('mail') && (int) ($ent['mailboxes'] ?? 0) > 0 && $adapter instanceof MailProvider),
                    'sending' => $on($flag('mail') && (int) ($ent['mailboxes'] ?? 0) > 0 && $adapter instanceof MailProvider),
                    // …and the tools of the panel that go with them, exactly as a mail service has them
                    'forwards' => $on($mailTools), 'catchall' => $on($mailTools), 'autoresponder' => $on($mailTools), 'spam' => $on($mailTools),
                    'mail_filters' => $on($mailTools), 'mailing_lists' => $on($mailTools), 'fetchmail' => $on($mailTools), 'mail_backups' => $on($mailTools), 'mail_usage' => $on($mailTools),
                    'file_manager' => $on($flag('file_manager')),
                    'errpages' => $on($flag('errpages')), 'directives' => $on($flag('directives'), null, $executor === 'aapanel' ? ['rewrite'] : ['apache', 'nginx']), 'protected' => $on($flag('protected'), null, $executor === 'aapanel' ? 'site' : 'folders'),
                    'db_users' => $on($flag('db_users')), 'shell' => $on($flag('ssh') && ! empty($ent['ssh']) && $executor !== 'aapanel', (int) ($ent['shell_users'] ?? 2)), 'stats' => $on($flag('stats')), 'ssl_upload' => $on($flag('ssl_upload')),
                    'files' => $on($flag('files')), 'apps' => $on($flag('apps')), 'db_admin' => $on($flag('db_admin')),
                    // tools on top of the panel (WebToolsProvider) — what the executor offers, gated by what the plan sells
                    'terminal' => $on($flag('terminal') && $adapter instanceof WebToolsProvider && (! empty($ent['ssh']) || ! empty($ent['terminal']))),
                    'php_settings' => $on($flag('php_settings')), 'security' => $on($flag('security'), null, self::wafPromise((string) ($ent['waf'] ?? 'basic'), (bool) $flag('rate_limit'), $service)), 'http3' => $on($flag('http3')),
                    'cron_edit' => $on($flag('cron_edit')), 'cron_logs' => $on($flag('cron_logs')), 'db_export' => $on($flag('db_export')), 'db_access' => $on($flag('db_access')),
                    // backups of a web service are the platform's own sets (ServiceBackups): downloading and deleting them does not depend on what the panel can do with its archives
                    'backup_download' => $on($flag('backup_download') || $adapter instanceof WebToolsProvider), 'backup_delete' => $on($flag('backup_delete') || $adapter instanceof WebToolsProvider),
                    'backup_schedule' => $on($flag('backups'), null, ['frequency' => (string) ($ent['backup_frequency'] ?? 'daily'), 'days' => (int) ($ent['backup_days'] ?? 7), 'generations' => (int) ($ent['backup_generations'] ?? 7)]),
                    'files_advanced' => $on($flag('files_advanced')), 'quotas' => $on($flag('quotas')), 'proxy' => $on($flag('proxy')), 'default_docs' => $on($flag('default_docs')), 'node_projects' => $on($flag('node_projects') && ($ent['node_projects'] ?? true) !== false),
                    'staging' => $on($flag('staging') && ! empty($ent['staging'])), 'deploy' => $on($flag('deploy') && (! empty($ent['deploy']) || ! empty($ent['staging']) || ! empty($ent['ssh']))),
                    'wordpress' => $on($flag('wordpress') && ($ent['wordpress'] ?? true) !== false, null, ['object_cache' => $ent['object_cache'] ?? null, 'updates' => $ent['updates'] ?? null]),
                    'monitoring' => $on(true, (int) ($ent['monitors'] ?? (! empty($ent['monitoring']) ? 5 : 1))), 'ssl_wildcard' => $on($flag('ssl_upload')), 'hsts' => $on($flag('hsts')),
                    'cdn' => $on((string) config('onhost.cdn.cloudflare.secret_ref', '') !== '' && (str_contains(strtolower((string) ($ent['waf'] ?? '')), 'cdn') || ! empty($ent['cdn']))), 'import' => $on($flag('files_advanced')),
                    'panel_login' => $on($flag('panel_login')),
                ];
                break;
            case 'cloud':
            case 'data':
                $out += [
                    'power' => $on($adapter === null || $adapter instanceof PowerCapable), 'console' => $on($adapter === null || $adapter instanceof ConsoleCapable),
                    'snapshots' => $on($adapter === null || $adapter instanceof ComputeProvider, (int) ($ent['snapshots'] ?? 3)), 'backups' => $on($adapter === null || $adapter instanceof BackupCapable, (int) ($ent['backup_days'] ?? 7)),
                    'restore' => $on($adapter === null || $adapter instanceof BackupCapable), 'resize' => $on(true), 'firewall' => $on($adapter === null || $adapter instanceof ComputeProvider), 'disks' => $on(true), 'network' => $on(true),
                    // the customer's own server only: a managed database (family `data`) has no root for its customer
                    'vm_access' => $on($service->family === 'cloud' && ($adapter === null || $adapter instanceof ComputeProvider)),
                    // the reverse record of the server's own address, offered only where the platform holds the reverse zone to publish it into
                    'vm_rdns' => $on($service->family === 'cloud' && self::reverseZoneAvailable($service)),
                    // booting somebody else's system on the customer's own disks: offered where the hypervisor holds rescue images (H233)
                    'vm_rescue' => $on($service->family === 'cloud' && ($adapter === null || $adapter instanceof ComputeProvider), null, ['session' => RescueMode::session($service), 'hours' => RescueMode::hours()]),
                ];
                // the backups a server was sold (TASK-0019), offered only once the owner switched the rule on: until then
                // the feature list of every existing server stays exactly what it was (the rule is asked first, so a switched-off
                // rule costs no query and nothing in the lookup can break the list)
                $sold = app(AutomationLedger::class)->enabled(BackupScheduler::COMPUTE_RULE) ? $this->computeBackupSchedule($service) : null;
                if ($sold !== null) {
                    $out['backup_schedule'] = $on($adapter === null || $adapter instanceof BackupCapable, null, $sold);
                }
                break;
            case 'game':
                // the node unreachable: the tabs stay, their listings say so; a panel whose client API key is missing or refused (prerequisites) offers no server tools
                $clientApi = (string) data_get($service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id)?->capabilities : null, 'prereqs.client_api', 'ok');
                $tools = ($adapter === null || $adapter instanceof GameToolsProvider) && ! in_array($clientApi, ['missing', 'rejected'], true);
                $out += [
                    'power' => $on($adapter === null || $adapter instanceof PowerCapable), 'console' => $on($adapter === null || $adapter instanceof ConsoleCapable), 'command' => $on($adapter === null || $adapter instanceof GameProvider),
                    'schedules' => $on($adapter === null || $adapter instanceof GameProvider), 'backups' => $on($adapter === null || $adapter instanceof BackupCapable, (int) ($ent['backups'] ?? 5)),
                    'restore' => $on($adapter === null || $adapter instanceof BackupCapable), 'resize' => $on(true), 'network' => $on(true),
                    // game tools: what the panel behind the server offers, gated by the plan (databases, ports, collaborators)
                    'game_status' => $on($tools), 'startup' => $on($tools), 'game_settings' => $on($tools), 'schedule_tools' => $on($tools),
                    'game_databases' => $on($tools && (int) ($ent['databases'] ?? 1) > 0, (int) ($ent['databases'] ?? 1)), 'subusers' => $on($tools, (int) ($ent['subusers'] ?? 5)),
                    'game_files' => $on($tools), 'allocations' => $on($tools, (int) ($ent['allocations'] ?? 1)), 'backup_tools' => $on($tools), 'panel_access' => $on($tools),
                ];
                break;
            case 'mail':
                $out += [
                    'mailboxes' => $on($adapter === null || $adapter instanceof MailProvider, (int) ($ent['mailboxes'] ?? 5)), 'aliases' => $on($adapter === null || $adapter instanceof MailProvider),
                    'dkim' => $on($adapter === null || $adapter instanceof MailProvider), 'sending' => $on($adapter === null || $adapter instanceof MailProvider),
                    // mail tools (MailToolsProvider): forwards, catch-all, autoresponders, spam policies and lists, filters, mailing lists, fetchmail, mailbox backups, usage and webmail
                    'forwards' => $on($adapter instanceof MailToolsProvider), 'catchall' => $on($adapter instanceof MailToolsProvider), 'autoresponder' => $on($adapter instanceof MailToolsProvider), 'spam' => $on($adapter instanceof MailToolsProvider),
                    'mail_filters' => $on($adapter instanceof MailToolsProvider), 'mailing_lists' => $on($adapter instanceof MailToolsProvider), 'fetchmail' => $on($adapter instanceof MailToolsProvider), 'mail_backups' => $on($adapter instanceof MailToolsProvider),
                    // the panel's nightly mailbox backups can always be listed and restored; one "now" only where the panel can make it (ISPConfig cannot)
                    'mail_backup_now' => $on($adapter instanceof MailToolsProvider && (! $adapter instanceof WebHostingProvider || ($adapter->siteFeatures()['mailbox_backup_on_demand'] ?? true) !== false)),
                    'mail_usage' => $on($adapter instanceof MailToolsProvider), 'monitoring' => $on(true, 1),
                ];
                break;
            case 'apps':
                $out += ['logs' => $on($adapter === null || $adapter instanceof KubernetesProvider), 'deploys' => $on(true), 'resize' => $on(true)];
                break;
        }

        return $out;
    }

    /** Actions the customer may request right now (features → actions); with an actor, what THAT person may request. @return list<string> */
    public function actions(Service $service, ?User $actor = null): array
    {
        $features = $this->features($service, $actor);
        $out = [];
        foreach (self::ACTIONS as $feature => $actions) {
            if (! empty($features[$feature]['enabled'])) {
                $out = array_merge($out, $actions);
            }
        }

        return array_values(array_unique($out));
    }

    /** Live listing of one resource kind from the executor (cached briefly; never cached across services). */
    /** @param array<string,mixed> $params listing parameters (`path` for the file manager) */
    public function resources(Service $service, string $kind, bool $fresh = false, array $params = []): array
    {
        if (! in_array($kind, self::RESOURCES, true)) {
            throw new DomainError('resource_kind_unknown', 'Unknown resource kind: '.implode(', ', self::RESOURCES).' are supported.', 422, ['field' => 'kind']);
        }
        $path = trim(str_replace('\\', '/', (string) ($params['path'] ?? '')), '/');
        if ($path !== '' && (str_contains($path, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $path))) {
            throw new DomainError('action_param_invalid', 'path must stay inside the site root.', 422, ['field' => 'path']);
        }
        $gate = [
            'protected_folders' => 'protected', 'db_users' => 'db_users', 'shell_users' => 'shell', 'files' => 'files', 'apps' => 'apps',
            'tools' => 'site', 'php_settings' => 'php_settings', 'security' => 'security', 'http_versions' => 'security', 'cron_logs' => 'cron_logs', 'database_access' => 'db_access', 'quotas' => 'quotas', 'node_projects' => 'node_projects', 'proxies' => 'proxy', 'default_docs' => 'default_docs',
            'sites' => 'sites', 'mail_access' => 'mailboxes', 'staging' => 'staging', 'deploy' => 'deploy', 'deployments' => 'deploy', 'wordpress' => 'wordpress', 'monitoring' => 'monitoring', 'monitoring_samples' => 'monitoring', 'certificates' => 'ssl', 'cdn' => 'cdn', 'imports' => 'import',
            'mail_forwards' => 'forwards', 'mail_catchall' => 'catchall', 'mail_autoresponder' => 'autoresponder', 'mail_spam' => 'spam', 'mail_spam_lists' => 'spam', 'mail_filters' => 'mail_filters', 'mail_lists' => 'mailing_lists', 'mail_fetchmail' => 'fetchmail', 'mail_backups' => 'mail_backups', 'mail_usage' => 'mail_usage',
            'status' => 'game_status', 'server_detail' => 'game_settings', 'startup' => 'startup', 'schedules' => 'schedule_tools', 'game_databases' => 'game_databases', 'subusers' => 'subusers', 'game_files' => 'game_files', 'allocations' => 'allocations', 'panel_access' => 'panel_access',
        ][$kind] ?? null;
        if ($gate !== null && empty($this->features($service)[$gate]['enabled'])) {
            throw new DomainError('feature_unavailable', 'This listing is not available for the service.', 422, ['kind' => $kind]);
        }
        $reveal = ! empty($params['reveal']);
        $key = "onhost:service:{$service->id}:resources:{$kind}".(in_array($kind, ['files', 'game_files'], true) ? ':'.sha1($path) : '').(($params['remote_id'] ?? '') !== '' ? ':'.sha1((string) $params['remote_id']) : '').($reveal ? ':reveal' : '').($kind === 'deploy' && empty($params['secrets']) ? ':masked' : ''); // the full view keeps the key `forget()` clears; only the masked view of a read-only role is kept apart
        if (! $fresh) {
            $cached = $this->cache->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $adapter = $this->adapter($service, true);
        $ref = $this->ref($service);
        // a web service's mail lives in a mail domain of its own: the site's number is not its mail domain's, and a
        // listing read with the site's ref answered for a domain the customer does not have mail in (MailDomains)
        $ref = (in_array($kind, MailDomains::KINDS, true) ? MailDomains::firstRefOf($service) : null) ?? $ref;
        $list = match ($kind) {
            'databases' => $this->web($adapter)->listDatabases($ref),
            'ftp' => $this->web($adapter)->listFtpAccounts($ref),
            'cron' => $this->web($adapter)->listCron($ref),
            'subdomains' => $this->web($adapter)->listSubdomains($ref),
            'certificate' => $this->web($adapter)->certificate($ref),
            'redirect' => $this->web($adapter)->redirect($ref),
            'php' => (function () use ($adapter, $service) { // the node's versions narrowed to what the plan sells
                $versions = $this->web($adapter)->phpVersions();
                $allowed = array_map('strval', (array) data_get($service->entitlements, 'php_versions', []));
                if ($allowed !== []) {
                    $versions = array_values(array_intersect($versions, $allowed)) ?: $versions;
                }
                $current = data_get($service->actual_spec, 'php_version', data_get($service->desired_spec, 'php_version'));

                return ['versions' => $versions, 'current' => $current !== null ? (string) $current : null];
            })(),
            'snapshots' => $adapter instanceof ComputeProvider ? $adapter->listSnapshots($ref) : throw new DomainError('feature_unavailable', 'Snapshots are not available for this service.', 422),
            'firewall' => ['rules' => (array) data_get($service->desired_spec, 'firewall.rules', []), 'enabled' => (bool) data_get($service->desired_spec, 'firewall.enabled', true)],
            'site_settings' => array_replace($this->web($adapter)->siteSettings($ref), ['site_password' => data_get($service->desired_spec, 'site_password.user')]),
            'protected_folders' => (function () use ($adapter, $ref, $service) {
                $list = $this->web($adapter)->listProtectedFolders($ref);
                $sitePassword = data_get($service->desired_spec, 'site_password');
                if (is_array($sitePassword) && ! empty($sitePassword['user'])) { // aaPanel: site-wide basic auth remembered by the platform
                    $list[] = ['remote_id' => 'site', 'path' => '/', 'users' => [(string) $sitePassword['user']], 'active' => true];
                }

                return $list;
            })(),
            'db_users' => $this->web($adapter)->listDbUsers($ref),
            'shell_users' => $this->withKeyOwners($service, array_values(array_filter($this->web($adapter)->listShellUsers($ref), fn (array $u) => ($u['user'] ?? '') !== Naming::prefix($service->id).'ag'))), // the platform's agent user is not the customer's account

            'files' => $this->web($adapter)->listFiles($ref, $path),
            'apps' => $this->web($adapter)->listApps($ref),
            // tools on top of the panel
            'tools' => (function () use ($adapter, $ref, $service) {
                $tools = $this->tools($adapter);
                $shell = $tools->shellAvailable($ref);
                $agent = 'ready';
                if (! $shell) { // jailed panels: the first look prepares the site's agent user; the listing says so instead of failing
                    try {
                        $agent = $tools->ensureAgent($ref)->isAsync() ? 'preparing' : 'ready';
                        $shell = $agent === 'ready' && $tools->shellAvailable($ref);
                    } catch (ProviderException $e) {
                        $agent = 'unavailable';
                    }
                }

                // the customer sees what the node offers, never which panel runs it
                return ['shell' => $shell, 'agent' => $agent, 'user' => $tools->siteUser($ref), 'document_root' => $tools->documentRoot($ref), 'paths' => $shell ? $tools->toolPaths($ref) : [], 'shell_kind' => str_contains(strtolower($tools->shell($ref)->describe()), 'ssh') ? 'ssh' : 'panel', 'strategy' => (string) data_get($service->desired_spec, 'executor', '') === 'aapanel' ? 'run_path' : 'symlink'];
            })(),
            'php_settings' => $this->tools($adapter)->phpSettings($ref),
            'security' => array_replace($this->tools($adapter)->securityRules($ref), ['desired' => (array) data_get($service->desired_spec, 'security', [])]),
            'http_versions' => $this->tools($adapter)->httpVersions($ref),
            'cron_logs' => $this->tools($adapter)->cronLogs($ref, (string) ($params['remote_id'] ?? ''), 200),
            'database_access' => $this->tools($adapter)->databaseAccess($ref, (string) ($params['remote_id'] ?? '')),
            'quotas' => $this->tools($adapter)->quotas($ref),
            'node_projects' => $this->tools($adapter)->nodeProjects($ref),
            'proxies' => $this->tools($adapter)->listProxies($ref),
            'default_docs' => ['names' => $this->tools($adapter)->defaultDocuments($ref)],
            // the platform's own records around the site
            'sites' => app(ServiceSites::class)->listing($service),
            'staging' => app(StagingService::class)->status($service),
            'deploy' => app(DeployService::class)->status($service, ! empty($params['secrets'])), // the caller says whether the role may see values (H334)
            'deployments' => app(DeployService::class)->deployments($service),
            'wordpress' => app(WordPressService::class)->status($service, $fresh),
            'monitoring' => app(UptimeMonitor::class)->status($service),
            'monitoring_samples' => app(UptimeMonitor::class)->samples($service),
            'certificates' => app(CertificateService::class)->status($service),
            'cdn' => app(CdnService::class)->status($service),
            'imports' => app(ImportService::class)->list($service),
            // mail tools — the panel's capability is asked BEFORE the listing: a service with no mail domain lists
            // nothing (MailDomains::across), and "this panel has no mail" must not turn into an empty list
            'mail_forwards' => MailDomains::across($service, $ref, [$this->mailTools($adapter), 'listForwards']),
            'mail_catchall' => $this->mailTools($adapter)->catchAll($ref) ?? [],
            // one mailbox's settings are read only once the mailbox is the service's own (ownMailboxRef)
            'mail_autoresponder' => $this->mailTools($adapter)->autoresponder($this->ownMailboxRef($service, $ref, $adapter, (string) ($params['remote_id'] ?? ''))),
            'mail_spam' => ['policies' => $this->mailTools($adapter)->spamPolicies($ref), 'mailbox' => ($params['remote_id'] ?? '') !== '' ? $this->mailTools($adapter)->mailboxSpamPolicy($this->ownMailboxRef($service, $ref, $adapter, (string) $params['remote_id'])) : null],
            'mail_spam_lists' => MailDomains::across($service, $ref, [$this->mailTools($adapter), 'listSpamLists']),
            'mail_filters' => $this->mailTools($adapter)->listFilters($this->ownMailboxRef($service, $ref, $adapter, (string) ($params['remote_id'] ?? ''))),
            'mail_lists' => MailDomains::across($service, $ref, [$this->mailTools($adapter), 'listMailingLists']),
            'mail_fetchmail' => MailDomains::across($service, $ref, [$this->mailTools($adapter), 'listFetchmail']),
            'mail_backups' => MailDomains::across($service, $ref, [$this->mailTools($adapter), 'listMailboxBackups']),
            'mail_usage' => ['mailboxes' => $this->mailTools($adapter)->mailboxUsage($ref), 'webmail' => $this->mailTools($adapter)->webmailUrl($ref)],
            // where a mailbox is reached: the platform could make one and then said nothing about the server or the ports
            'mail_access' => MailSettings::of($service, $adapter instanceof MailToolsProvider ? $this->mailTools($adapter)->webmailUrl($ref) : null),
            // every domain the service has mail in, so that what the plan sells is counted over all of them
            'mailboxes' => MailDomains::across($service, $ref, [$this->mail($adapter), 'listMailboxes']),
            'aliases' => MailDomains::across($service, $ref, [$this->mail($adapter), 'listAliases']),
            'dkim' => $this->mail($adapter)->dkim($ref) ?? [],
            // game tools (GameToolsProvider)
            'status' => $this->gameTools($adapter)->status($ref),
            'server_detail' => $this->gameTools($adapter)->serverDetail($ref),
            'startup' => (function () use ($adapter, $ref, $service) { // §5t-3: the customer inputs that fail their rule are flagged
                $startup = $this->gameTools($adapter)->startup($ref);

                return $startup + ['attention' => app(GameTemplates::class)->attention((string) data_get($service->desired_spec, 'egg', ''), (array) ($startup['variables'] ?? []))];
            })(),
            'schedules' => $this->gameTools($adapter)->listSchedules($ref),
            'game_databases' => $this->gameTools($adapter)->listDatabases($ref, $reveal),
            'subusers' => $this->gameTools($adapter)->listSubusers($ref),
            'game_files' => ['path' => $path, 'entries' => $this->gameTools($adapter)->listFiles($ref, '/'.$path)],
            'allocations' => $this->gameTools($adapter)->listAllocations($ref),
            'panel_access' => $this->gameTools($adapter)->panelAccount($ref),
        };
        $this->cache->put($key, $list, in_array($kind, ['files', 'game_files', 'status'], true) || $reveal || in_array($kind, self::PLATFORM_RESOURCES, true) ? 5 : 30);

        return $list;
    }

    /** File contents for the download endpoint (never cached, size-capped in the adapter/controller). */
    public function fileContents(Service $service, string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $path)) {
            throw new DomainError('action_param_invalid', 'path must name a file inside the site root.', 422, ['field' => 'path']);
        }
        $features = $this->features($service);
        if ($service->family === 'game' && ! empty($features['game_files']['enabled'])) {
            return $this->gameTools($this->adapter($service, true))->readFile($this->ref($service), '/'.$path);
        }
        if (empty($features['files']['enabled'])) {
            throw new DomainError('feature_unavailable', 'The file manager is not available for this service.', 422);
        }

        return $this->web($this->adapter($service, true))->readFile($this->ref($service), $path);
    }

    /** Game tools (GameToolsProvider) — the adapter of a running game server, or a clear refusal. */
    public function gameTools(?ProviderAdapter $adapter): GameToolsProvider
    {
        if (! $adapter instanceof GameToolsProvider) {
            throw new DomainError('feature_unavailable', 'This game panel does not offer the tool.', 422);
        }

        return $adapter;
    }

    public function forget(Service $service): void
    {
        foreach (self::RESOURCES as $kind) {
            $this->cache->forget("onhost:service:{$service->id}:resources:{$kind}");
        }
    }

    /** @return list<string> */
    public function logs(Service $service, string $log = 'access', int $lines = 200): array
    {
        $adapter = $this->adapter($service, true);
        if ($adapter instanceof WebHostingProvider) {
            return $adapter->tailLog($this->ref($service), $log, $lines);
        }
        if ($adapter instanceof KubernetesProvider) {
            $app = (array) data_get($service->desired_spec, 'app', []);

            return $adapter->podLogs((string) data_get($service->desired_spec, 'namespace', ''), (string) ($app['name'] ?? ''), $lines);
        }
        if ($adapter instanceof GameToolsProvider) { // the game console log: the server's log file through the panel client API (Minecraft: logs/latest.log)
            $file = (string) config('onhost.game.eggs.'.(string) data_get($service->desired_spec, 'egg', '').'.log_file', 'logs/latest.log');
            $status = $adapter->status($this->ref($service)); // an offline server has no daemon file access; asking would only trip the circuit breaker
            if (in_array((string) ($status['state'] ?? ''), ['offline', 'installing'], true) || ! empty($status['installing'])) {
                return ['— server je '.((string) ($status['state'] ?? 'offline')).'; log se zobrazí po startu —'];
            }
            try {
                $content = $adapter->readFile($this->ref($service), $file);
            } catch (ProviderException $e) { // no file yet (the server never ran) or the daemon not answering: the console shows why instead of an error box
                return [$e->errorCode === ProviderErrorCode::NOT_FOUND ? '— log '.$file.' ještě neexistuje (server zatím neběžel) —' : '— log zatím nelze přečíst: '.$e->getMessage().' —'];
            }
            $all = preg_split('/?
/', rtrim($content)) ?: [];

            return array_values(array_slice($all, -max(1, min(2000, $lines))));
        }
        throw new DomainError('feature_unavailable', 'Logs are not available for this service.', 422);
    }

    /** Names on shared executors are scoped per service, so a customer-chosen suffix becomes `oh…_suffix`. */
    public static function scopedName(Service $service, string $suffix): string
    {
        return Naming::scoped($service->id, $suffix);
    }

    private function adapter(Service $service, bool $required = false): ?ProviderAdapter
    {
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        if ($instance === null) {
            if ($required) {
                throw new DomainError('service_not_provisioned', 'The service has no provider resource yet.', 409);
            }

            return null;
        }
        try {
            return $this->providers->forInstance($instance);
        } catch (\Throwable $e) {
            if ($required) {
                throw $e instanceof DomainError || $e instanceof ProviderException ? $e : new DomainError('provider_unavailable', 'The service executor is not reachable right now.', 503);
            }

            return null;
        }
    }

    private function ref(Service $service): ResourceRef
    {
        $binding = $service->primaryBinding();
        if ($binding === null) {
            throw new DomainError('service_not_provisioned', 'The service has no provider resource yet.', 409);
        }

        return $binding->ref();
    }

    /** Tools on top of the panel (WebToolsProvider) — the adapter of a running service, or a clear refusal. */
    public function tools(?ProviderAdapter $adapter): WebToolsProvider
    {
        if (! $adapter instanceof WebToolsProvider) {
            throw new DomainError('feature_unavailable', 'This tool is not available for the service.', 422);
        }

        return $adapter;
    }

    public function mailTools(?ProviderAdapter $adapter): MailToolsProvider
    {
        if (! $adapter instanceof MailToolsProvider) {
            throw new DomainError('feature_unavailable', 'This mail tool is not available for the service.', 422);
        }

        return $adapter;
    }

    /** The adapter and primary resource of a service for the tool services (staging, deploy, WordPress, imports). @return array{0:WebToolsProvider,1:ResourceRef} */
    public function toolsFor(Service $service): array
    {
        return [$this->tools($this->adapter($service, true)), $this->ref($service)];
    }

    /** The provider adapter of a provisioned service (platform services such as staging and import need the hosting API next to the tools). */
    public function adapterFor(Service $service): ProviderAdapter
    {
        return $this->adapter($service, true);
    }

    public function refFor(Service $service): ResourceRef
    {
        return $this->ref($service);
    }

    public static function mailboxRef(ResourceRef $domain, string $remoteId): ResourceRef
    {
        if ($remoteId === '') {
            throw new DomainError('action_param_invalid', 'remote_id of the mailbox is required.', 422, ['field' => 'remote_id']);
        }

        return new ResourceRef('mailbox', $remoteId, $domain->node, ['client_id' => $domain->meta['client_id'] ?? null, 'domain' => $domain->meta['domain'] ?? null], $domain->serviceId);
    }

    /**
     * The mailbox a listing is asked about, once one of the service's own mail domains lists it.
     *
     * `mailboxRef` builds a reference from whatever id it is given, and the panel reads any mailbox on the shared mail
     * server by its number: a customer read a historical customer's autoresponder text, filters and spam policy
     * through the listing just by counting. A mailbox that is not the service's is "not found" — the same answer as
     * one that does not exist, so the listing does not even confirm the number is taken.
     */
    private function ownMailboxRef(Service $service, ResourceRef $ref, ?ProviderAdapter $adapter, string $remoteId): ResourceRef
    {
        $mailbox = self::mailboxRef($ref, $remoteId); // no id at all is still the caller's mistake (422)
        if (MailDomains::ownRow($service, $ref, [$this->mail($adapter), 'listMailboxes'], 'remote_id', $remoteId) === null) {
            throw new DomainError('mailbox_not_found', 'Tahle schránka k téhle službě nepatří nebo neexistuje.', 404, ['remote_id' => $remoteId]);
        }

        return $mailbox;
    }

    /**
     * The panel says an account has a key; the ledger says whose it is and whether a revocation is still open (H185).
     * A key the ledger does not know — installed before the ledger, or at the panel by hand — is said to be unknown.
     *
     * @param  list<array<string,mixed>>  $accounts
     * @return list<array<string,mixed>>
     */
    private function withKeyOwners(Service $service, array $accounts): array
    {
        $grants = SshKeyGrant::query()->where('service_id', $service->id)->whereIn('state', [SshKeyGrant::ACTIVE, SshKeyGrant::REVOKING])->get()->keyBy('target_remote_id');
        $owners = User::query()->whereIn('id', $grants->pluck('owner_user_id')->filter()->unique()->all())->get()->keyBy('id');

        return array_map(function (array $account) use ($grants, $owners): array {
            $grant = $grants->get((string) ($account['remote_id'] ?? ''));
            $owner = $grant === null || $grant->owner_user_id === null ? null : $owners->get($grant->owner_user_id);
            $account['key'] = empty($account['has_key']) && $grant === null ? null : [
                'known' => $grant !== null, 'fingerprint' => $grant?->fingerprint, 'state' => $grant === null ? 'unknown' : $grant->state,
                'owner' => $grant === null || $grant->owner_user_id === null ? null : ['user_id' => $grant->owner_user_id, 'name' => $owner?->name, 'email' => $owner?->email],
                'installed_at' => $grant?->installed_at?->toIso8601String(), 'revocation_pending' => $grant !== null && $grant->state === SshKeyGrant::REVOKING,
            ];

            return $account;
        }, $accounts);
    }

    private function web(?ProviderAdapter $adapter): WebHostingProvider
    {
        if (! $adapter instanceof WebHostingProvider) {
            throw new DomainError('feature_unavailable', 'This feature is not available for the service.', 422);
        }

        return $adapter;
    }

    private function mail(?ProviderAdapter $adapter): MailProvider
    {
        if (! $adapter instanceof MailProvider) {
            throw new DomainError('feature_unavailable', 'This feature is not available for the service.', 422);
        }

        return $adapter;
    }

    /** Executor feature set when the adapter cannot be built (credentials missing): what each executor kind offers. */
    /**
     * Which of the managed security rules a panel can put on one site. Both apply the block of directives (deny and
     * allow lists, bad bots, hotlinking, HSTS, headers); a request or connection limit is aaPanel's alone, because
     * nginx wants its `limit_req_zone` in the http block of the node and a customer's site does not own that.
     *
     * @return list<string>
     */
    public static function securitySupports(string $executor): array
    {
        return self::supportsFor((bool) (self::fallbackSite($executor)['rate_limit'] ?? false));
    }

    /** @return list<string> */
    private static function supportsFor(bool $rate): array
    {
        return array_values(array_filter(WafLevels::RULES, fn (string $rule) => $rule !== 'rate' || $rate));
    }

    /**
     * The WAF line of the plan next to what this service's own server does of it: a level was a label and nothing
     * else, so a plan promising a rate limit delivered none of it on a shared ISPConfig node and nobody was told.
     *
     * @return array<string, mixed>
     */
    /**
     * The backup schedule a server or managed database was SOLD, whatever the automation switch says (the dry run asks
     * this too). A managed database carries it in its plan (`backup_days`, 14 on db-s, 30 on db-m). A VPS sells none of
     * its own: only an ACTIVE backup add-on does, together with the policy it wrote — a policy row that outlived its
     * add-on (re-saved under the plan's key, which the add-on's cancellation does not remove) must not go on taking
     * backups nobody pays for. The ceiling is what the add-ons sell, not what the row says now (`BackupScheduler::scheduleFrom` caps the row to it).
     *
     * @return array{frequency:string, days:int, generations:int}|null
     */
    public function computeBackupSchedule(Service $service): ?array
    {
        $ent = (array) $service->entitlements;
        if ($service->family === 'data') {
            $days = (int) ($ent['backup_days'] ?? 0);

            return $days > 0 ? ['frequency' => (string) ($ent['backup_frequency'] ?? 'daily'), 'days' => $days, 'generations' => max(1, (int) ($ent['backup_generations'] ?? $days))] : null;
        }
        if ($service->family !== 'cloud' || ! BackupPolicy::query()->where('service_id', $service->id)->exists()) {
            return null;
        }
        $addons = Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('created_at')->orderBy('id')->get();
        // two backup add-ons on one server (a daily one and an hourly one): the customer pays for both, so the ceiling is the
        // most generous of them in each dimension — the most frequent schedule, the longest history, the most copies
        $best = null;
        foreach ($addons as $addon) {
            $policy = Addons::backupPolicy((string) $addon->product_key, (array) $addon->entitlements);
            if ($policy === null) {
                continue;
            }
            $frequency = (string) $policy['schedule']['frequency'];
            $best = $best === null ? ['frequency' => $frequency, 'days' => (int) $policy['retention']['days'], 'generations' => (int) $policy['retention']['generations']] : [
                'frequency' => (BackupScheduler::FREQUENCIES[$frequency] ?? PHP_INT_MAX) < (BackupScheduler::FREQUENCIES[$best['frequency']] ?? PHP_INT_MAX) ? $frequency : $best['frequency'],
                'days' => max($best['days'], (int) $policy['retention']['days']),
                'generations' => max($best['generations'], (int) $policy['retention']['generations']),
            ];
        }

        return $best;
    }

    private static function wafPromise(string $waf, bool $rate, Service $service): array
    {
        $supports = self::supportsFor($rate);
        // what the daily look at the site found, so the person who can clean it up is the one who hears about it
        $integrity = (array) data_get($service->tags, 'integrity', []);

        return ['integrity' => array_filter(['findings' => $integrity['findings'] ?? null, 'checked_at' => $integrity['checked_at'] ?? null], fn ($v) => $v !== null),
            'rate' => $rate, 'waf' => $waf, 'level' => WafLevels::levelOf($waf), 'promised' => WafLevels::promised($waf), 'delivered' => array_values(array_intersect(WafLevels::promised($waf), $supports)), 'missing' => WafLevels::missing($waf, $supports)];
    }

    private static function fallbackSite(string $executor): array
    {
        return match ($executor) {
            'aapanel' => ['php' => true, 'rate_limit' => true, 'databases' => true, 'ftp' => true, 'ssl' => true, 'https' => true, 'cron' => true, 'logs' => true, 'backups' => true, 'restore' => false, 'subdomains' => true, 'redirects' => true, 'ssh' => false, 'mail' => false, 'file_manager' => true, 'usage' => true, 'errpages' => false, 'directives' => true, 'protected' => true, 'db_users' => false, 'stats' => false, 'ssl_upload' => true, 'files' => true, 'apps' => true, 'db_admin' => false],
            'ispconfig' => ['php' => true, 'rate_limit' => false, 'databases' => true, 'ftp' => true, 'ssl' => true, 'https' => true, 'cron' => true, 'logs' => true, 'backups' => true, 'restore' => true, 'subdomains' => true, 'redirects' => true, 'ssh' => true, 'mail' => true, 'file_manager' => false, 'usage' => true, 'errpages' => true, 'directives' => true, 'protected' => true, 'db_users' => true, 'stats' => true, 'ssl_upload' => true, 'files' => false, 'apps' => false, 'db_admin' => false],
            default => [],
        };
    }
}
