<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;
use Onhost\Platform\Errors\DomainError;

/**
 * payload: service_id, action (one of ServiceActionWorkflow::ACTIONS), params{}
 *
 * Permission, risk and step-up are decided per action (TASK-0029, audit C13-H1): 121 of 132 actions used to fall through a
 * default arm to `service.manage` at NORMAL risk, so whoever could restart a site could also delete its backups. The map below
 * names every action once and has no default: an action nobody mapped is refused before anything runs. Every destructive
 * action (DestructivePreview) is HIGH with a fresh step-up; nothing here is ever auto-approved for AI actors. Backup deletion
 * is HIGH for customers although the catalogue rates `backup.delete` CRITICAL: IdentityCommandAuthorizer forces CRITICAL only
 * for staff-audience permissions, and there is no customer four-eyes (D29.2) — the step-up is the customer's second lock.
 */
final class ServiceActionCommand extends OrganizationCommand implements RiskAwareCommand
{
    /**
     * Every action of ServiceActionWorkflow::ACTIONS → the permission the bus asks for, and the operation row asks again before
     * each privileged step of a long run (H315). Written out in full on purpose: a new action has to be placed here by
     * somebody who decided what it is, or it does not run (ServiceActionPermissionMapTest keeps both lists equal).
     */
    public const PERMISSIONS = [
        // ── the service itself ───────────────────────────────────────────────────────────────────
        'terminate' => 'service.delete', 'purge' => 'service.delete',
        'power' => 'service.manage', 'suspend' => 'service.manage', 'resume' => 'service.manage', 'resize' => 'service.manage', 'reinstall' => 'service.manage',
        'rename' => 'service.manage', 'site.create' => 'service.manage', 'site.delete' => 'service.manage',
        // ── copies: making one is managing, bringing one back overwrites what is there now ──────────────
        'backup' => 'service.manage', 'snapshot' => 'service.manage', 'restore.test' => 'service.manage', // a restore test goes into a database of its own; the site is not touched
        'restore' => 'backup.restore', 'rollback_snapshot' => 'backup.restore', 'archive.restore' => 'backup.restore',
        // Deleting a copy is its own permission (C13-FM, D29.2): the day-to-day manager — a developer, a `svc_manage` guest, a
        // power token — must not be able to thin out the backups the owner relies on. Web/managed/mail sets ask `backup.delete`,
        // a game server's `game.manage` (the game operator's), a VM snapshot `compute.vm.delete` (the cloud operator's).
        'backup.delete' => 'backup.delete', 'gbackup.delete' => 'game.manage', 'snapshot.delete' => 'compute.vm.delete',
        'gbackup.lock' => 'service.manage', 'mailbox.backup' => 'service.manage', 'mailbox.restore' => 'service.manage',
        // what the server keeps, and whether it prunes, is the operator's (onhost:mail:backup-retention); CustomerActionParams
        // refuses customers too, as a second lock
        'mailbox.backup_retention' => 'backup.policy.manage',
        // ── a shell or root is the console (H334, C13-H1b) ───────────────────────────────────────────
        // „Service: manage“ promises actions and settings — restart, PHP, databases, cron, files, deploys, mailboxes — and
        // „Service: console“ promises the terminal, VNC and the game console; the catalogue's own rule is that a console is
        // more than managing, never less. Sending these to `service.manage` meant the one role that exists to hand over the
        // day-to-day work WITHOUT a shell handed over a shell: the agency could open a terminal, read `wp-config.php` and with
        // it the database, or put their own key on the site. Root access (new password / SSH keys), a rescue system and a
        // game panel sub-user (who gets the panel and its console, and outlives a revocation under another e-mail) are the same.
        'command.run' => 'service.console', 'command.send' => 'service.console', 'shell.create' => 'service.console', 'shell.key' => 'service.console', 'shell.delete' => 'service.console',
        'access.reset' => 'service.console', 'rescue.start' => 'service.console', 'subuser.create' => 'service.console',
        'rescue.stop' => 'service.manage', 'subuser.delete' => 'service.manage', // ending access is never more than managing
        // a schedule is raised to the console when one of its tasks is a console command (permissionFor)
        'schedule.create' => 'service.manage', 'schedule.delete' => 'service.manage', 'schedule.toggle' => 'service.manage', 'schedule.run' => 'service.manage',
        // The game panel account opens every server of that account, not only this service: the organization owner alone
        // sets its password (owner decision 15; left open by TASK-0007). Services\Access\OwnerOnlyActions checks the owner too.
        'panel.password' => 'service.panel_account.manage',
        // ── managing: FTP and database logins give what managing already gives (D29.3) ───────────────────
        'php.set' => 'service.manage', 'php.settings' => 'service.manage', 'security.set' => 'service.manage', 'http3.set' => 'service.manage',
        'database.create' => 'service.manage', 'database.delete' => 'service.manage', 'database.export' => 'service.manage', 'database.import' => 'service.manage', 'database.access' => 'service.manage',
        'dbuser.create' => 'service.manage', 'dbuser.password' => 'service.manage', 'dbuser.delete' => 'service.manage',
        'ftp.create' => 'service.manage', 'ftp.delete' => 'service.manage', 'ftp.password' => 'service.manage',
        'cron.create' => 'service.manage', 'cron.delete' => 'service.manage', 'cron.update' => 'service.manage', 'cron.run' => 'service.manage',
        'file.mkdir' => 'service.manage', 'file.delete' => 'service.manage', 'file.save' => 'service.manage', 'file.rename' => 'service.manage', 'file.copy' => 'service.manage',
        'file.chmod' => 'service.manage', 'file.archive' => 'service.manage', 'file.extract' => 'service.manage',
        'subdomain.add' => 'service.manage', 'subdomain.remove' => 'service.manage', 'redirect.set' => 'service.manage', 'index.set' => 'service.manage',
        'proxy.create' => 'service.manage', 'proxy.delete' => 'service.manage', 'proxies.set' => 'service.manage',
        'ssl.issue' => 'service.manage', 'ssl.upload' => 'service.manage', 'ssl.wildcard' => 'service.manage', 'https.force' => 'service.manage',
        'errpages.set' => 'service.manage', 'directives.set' => 'service.manage', 'folder.protect' => 'service.manage', 'folder.unprotect' => 'service.manage', 'stats.set' => 'service.manage',
        'node.create' => 'service.manage', 'node.action' => 'service.manage', 'app.install' => 'service.manage',
        'firewall.apply' => 'service.manage', 'rdns.set' => 'service.manage',
        // mail
        'mailbox.create' => 'service.manage', 'mailbox.update' => 'service.manage', 'mailbox.delete' => 'service.manage', 'alias.create' => 'service.manage', 'alias.delete' => 'service.manage',
        'sending.set' => 'service.manage', 'forward.create' => 'service.manage', 'forward.delete' => 'service.manage', 'catchall.set' => 'service.manage', 'autoresponder.set' => 'service.manage',
        'spam.policy' => 'service.manage', 'spam.list.add' => 'service.manage', 'spam.list.delete' => 'service.manage', 'filter.create' => 'service.manage', 'filter.delete' => 'service.manage',
        'list.create' => 'service.manage', 'list.delete' => 'service.manage', 'fetchmail.create' => 'service.manage', 'fetchmail.delete' => 'service.manage',
        // game
        'variable.set' => 'service.manage', 'image.set' => 'service.manage', 'gamedb.create' => 'service.manage', 'gamedb.rotate' => 'service.manage', 'gamedb.delete' => 'service.manage',
        'gfile.save' => 'service.manage', 'gfile.upload' => 'service.manage', 'gfile.delete' => 'service.manage', 'gfile.mkdir' => 'service.manage', 'gfile.rename' => 'service.manage',
        'allocation.add' => 'service.manage', 'allocation.primary' => 'service.manage', 'allocation.remove' => 'service.manage',
        // platform workflows around the site
        'staging.create' => 'service.manage', 'staging.refresh' => 'service.manage', 'staging.push' => 'service.manage', 'staging.delete' => 'service.manage',
        'deploy.run' => 'service.manage', 'deploy.rollback' => 'service.manage', 'wp.install' => 'service.manage', 'wp.update' => 'service.manage', 'wp.cache' => 'service.manage', 'wp.plugin' => 'service.manage',
        'import.run' => 'service.manage', 'cdn.enable' => 'service.manage', 'cdn.disable' => 'service.manage', 'cdn.purge' => 'service.manage',
    ];

    /**
     * A fresh step-up: every action that destroys or overwrites customer data (DestructivePreview — archive.restore was the one
     * restore without it, C13-H1c), the panel password that opens every server of the account, and new keys or a rescue system
     * that open the server itself.
     */
    public const STEP_UP = [...DestructivePreview::ACTIONS, 'panel.password', 'access.reset', 'rescue.start'];

    /** HIGH risk: the step-up list, a resize (it follows a paid plan change) and the operator's mailbox retention. */
    public const HIGH_RISK = [...self::STEP_UP, 'resize', 'mailbox.backup_retention'];

    /** The service is the resource; its project rides along so a project-scoped developer may act on it. */
    public function scope(): ?CommandScope
    {
        $serviceId = $this->get('service_id');
        $projectId = $this->get('project_id');

        return is_string($serviceId) && $serviceId !== ''
            ? CommandScope::resource($serviceId, $this->organizationId, is_string($projectId) && $projectId !== '' ? $projectId : null)
            : CommandScope::organization($this->organizationId);
    }

    public function permission(): ?string
    {
        return self::permissionFor((string) $this->get('action'), (array) $this->get('params', []));
    }

    /**
     * One map for the bus and for the operation row: a long run asks for the same permission again before each privileged
     * step (H315). An action nobody mapped is refused (the same answer as ServiceService::requestAction), whoever asks.
     *
     * @param  array<string,mixed>  $params
     */
    public static function permissionFor(string $action, array $params = []): string
    {
        $permission = self::PERMISSIONS[$action] ?? throw new DomainError('service_action_unknown', "Unknown service action {$action}.", 422, ['action' => $action]);

        return $action === 'schedule.create' && self::schedulesConsoleCommand($params) ? 'service.console' : $permission;
    }

    /** Whether the action asks for a fresh step-up — for callers without a person at the keyboard (hooks, Discord), which cannot give one. */
    public static function needsFreshStepUp(string $action): bool
    {
        return in_array($action, self::STEP_UP, true) || in_array($action, self::HIGH_RISK, true);
    }

    /**
     * A scheduled console command is a console command that runs later, by itself (C13-H1b). Fail closed: only a schedule whose
     * every task is a power or backup task stays managing; anything else — a command, a misspelt word, a task that is no
     * task — is the console. ServiceService::featureParams refuses what is neither later, so nothing is lost by asking more.
     *
     * @param  array<string,mixed>  $params
     */
    private static function schedulesConsoleCommand(array $params): bool
    {
        foreach ((array) ($params['actions'] ?? []) as $task) {
            if (! is_array($task) || ! in_array($task['action'] ?? null, ['power', 'backup'], true)) {
                return true;
            }
        }

        return false;
    }

    public function name(): string
    {
        return 'service.'.(string) $this->get('action', 'action');
    }

    public function riskLevel(): string
    {
        return in_array((string) $this->get('action'), self::HIGH_RISK, true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array((string) $this->get('action'), self::STEP_UP, true); // a reinstall wipes the server, the panel password opens every server of the account, new keys open the server itself
    }

    /**
     * Pruning mailbox backups deletes backups, and deleting backups is CRITICAL in the catalogue: the operator's retention run
     * with `allow_prune` takes a second person (ONHOST_FOUR_EYES=false waives it; the CLI system path is not a bus command).
     */
    public function requiresApproval(): bool
    {
        return (string) $this->get('action') === 'mailbox.backup_retention'
            && filter_var(((array) $this->get('params', []))['allow_prune'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
