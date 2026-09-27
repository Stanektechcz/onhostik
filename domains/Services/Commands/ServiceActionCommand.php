<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Authorization\StaffModeCommand;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Platform\Commands\CommandContext;
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
final class ServiceActionCommand extends OrganizationCommand implements RiskAwareCommand, StaffModeCommand
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
        // locking a game backup is managing; unlocking one asks what deleting it asks (permissionFor): an unlocked copy can be
        // deleted on the panel, and a Pterodactyl scheduled backup task is understood to rotate the oldest unlocked one away at
        // the backup limit (review round 2; not verified against the installed panel version)
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
     * The key the BUS keeps its answer under: the caller's key plus a keyed fingerprint of what was asked (TASK-0036 review
     * round 1, IF-12). The bus answers a known key before the handler runs, so the same key with another body was answered
     * with the first run whenever the HTTP layer had kept nothing (the process died after the commit, a 5xx) — and
     * ServiceService's own 409 for a changed request never got the chance. Now another body passes the bus, and the
     * operation (looked up by the caller's key alone, `$this->idempotencyKey`) refuses it. Keyed with app.key: the params
     * carry passwords, and this key is stored in clear for a day. The same request still hashes the same, so a true retry
     * is replayed by the bus exactly as before.
     */
    public function idempotencyKey(): string
    {
        return self::fingerprinted($this->idempotencyKey, $this->payload);
    }

    /** The bus key of a service command: the caller's key and a keyed fingerprint of the payload (ServiceArchiveCommand too). @param array<mixed> $payload */
    public static function fingerprinted(string $key, array $payload): string
    {
        return $key.'#'.substr(hash_hmac('sha256', (string) json_encode($payload), (string) config('app.key')), 0, 16);
    }

    /**
     * The caller's key prefix for an action on one service, for EVERY door to it (TASK-0036 review round 1, red-team round): the
     * action endpoint and the archive endpoint name the service and the actor (the person a staff member acts for first, as the
     * operation's own namespace does), so the bus never answers one person's or one target's key for another, and both doors
     * to one archive restore reach the same operation.
     */
    public static function keyPrefix(string $serviceId, string $action, CommandContext $context): string
    {
        $actor = substr(hash('sha256', $context->actorType.':'.($context->onBehalfOfUserId ?? $context->actorId ?? '')), 0, 16);

        return "service.{$action}:{$serviceId}:{$actor}";
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

        return match (true) {
            self::skipsCustomerProtection($action, $params) => self::STAFF_DELETE, // TASK-0039 (IF-9): a forced purge or a skipped archive
            $action === 'schedule.create' && self::schedulesConsoleCommand($params) => 'service.console',
            $action === 'gbackup.lock' && ! self::keepsLocked($params) => self::PERMISSIONS['gbackup.delete'],
            default => $permission,
        };
    }

    /**
     * Whether a gbackup.lock asks to lock (TASK-0029 review round 2): a `svc_manage` guest could take the owner's lock off and let
     * the copy go on the panel. Fail closed: ServiceService::featureParams reads a missing `locked` as locking and anything that
     * is not a definite yes as an unlock, so only a missing word or a definite yes stays managing.
     *
     * @param  array<string,mixed>  $params
     */
    private static function keepsLocked(array $params): bool
    {
        return filter_var($params['locked'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
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
        if (self::skipsCustomerProtection((string) $this->get('action'), (array) $this->get('params', []))) {
            return PermissionCatalog::CRITICAL; // TASK-0039 (IF-9): a second person, or the sole approver's time lock
        }

        return in_array((string) $this->get('action'), self::HIGH_RISK, true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    // ── TASK-0039 (permission program P0-08/IF-9, audit SS-5/SE-3) ──
    /** The staff permission that removes a service before its time or without its final archive (a staff role's, never a customer's). */
    public const STAFF_DELETE = 'staff.service.delete';

    /**
     * A forced purge (inside the customer's restore window) or a skipped final archive (`archive_before_delete: false`) skips what
     * protects the customer's data, so it is `staff.service.delete`, declared CRITICAL: a second person, or the sole approver's
     * time lock (ServiceActionWorkflow::finalArchiveStep, ServiceService::requestAction). It was `service.delete`, HIGH: one
     * operator alone removed a service and its data at once. Decided from what was asked, not from who asks: a customer who asks
     * for the flags is refused them by the bus (they were silently dropped before, CustomerActionParams), a token too — the
     * permission has no token scope (TokenScopes::for). Fail closed: anything but a definite yes to the archive asks for the staff permission.
     *
     * @param  array<string,mixed>  $params
     */
    public static function skipsCustomerProtection(string $action, array $params): bool
    {
        if (! in_array($action, ['purge', 'terminate'], true)) {
            return false;
        }

        return ($action === 'purge' && self::forces($params)) || self::skipsArchive($params);
    }

    /** A forced purge, read exactly as ServiceService reads it (anything non-empty forces). @param array<string,mixed> $params */
    public static function forces(array $params): bool
    {
        return ! empty($params['force']);
    }

    /** A request to skip the final archive: anything that is not a definite yes to it (the workflow skips on `false`). @param array<string,mixed> $params */
    public static function skipsArchive(array $params): bool
    {
        return array_key_exists('archive_before_delete', $params) && filter_var($params['archive_before_delete'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== true;
    }
    // ── end TASK-0039 ──

    // ── TASK-0039 P0-16 re-check (staff mode asks staff keys) ──
    /**
     * The staff key of a customer key on /v1/staff/services/{id}/actions: managing a service (a resume, a suspend, a resize…) is
     * `staff.service.manage`, removing one `staff.service.delete`. The staff route used to ask the customer key, which the staff
     * person's own membership or a `svc_manage` share satisfied. A key with no staff counterpart stays what it is — the console,
     * a restore, a copy deleted, the owner's panel password: staff reach it only as staff reach (shadow-logged, CRITICAL where
     * the catalogue says so) or as a member, never with more than the customer route gives (StaffActor::may asks the staff key).
     */
    public const STAFF_PERMISSIONS = ['service.manage' => 'staff.service.manage', 'service.delete' => self::STAFF_DELETE, self::STAFF_DELETE => self::STAFF_DELETE];

    public function staffPermission(): string
    {
        $permission = self::permissionFor((string) $this->get('action'), (array) $this->get('params', []));

        return self::STAFF_PERMISSIONS[$permission] ?? $permission;
    }
    // ── end TASK-0039 P0-16 re-check ──

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
