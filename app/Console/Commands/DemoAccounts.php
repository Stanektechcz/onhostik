<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Demo\DemoAccountRoster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Commands\StaffAccountCommandHandler;
use Onhost\Domain\Partners\Models\Partner;
use Throwable;

/**
 * Demo sign-ins for walking through the surfaces on a staging that talks to LIVE panels (DevAccountSeeder refuses production
 * and makes fake provider bindings, so it must never run there). Six accounts share one password — customer, reseller,
 * affiliate, partner, support and admin — and nothing but people, organizations, partner records and role bindings is made
 * (DemoAccountRoster). The password comes from a hidden prompt or stdin, never from an option, and is never printed.
 *
 * The admin account never decides approvals: a password several people know must not be the second person of four eyes,
 * and on an installation wired to live panels it holds no destructive provider power either — `auditor_read_only` (global
 * read of audit, security events, reports, customers and operations) by default. `--admin-role` takes another global staff
 * role, but never one holding `iam.approval.decide` (platform_owner, iam_admin, billing_finance_admin, or one given it in
 * role_permissions): a personal account for that is made with onhost:staff:create.
 */
final class DemoAccounts extends Command
{
    /** Tests bind a stream here to stand in for standard input. */
    public const STDIN_BINDING = 'onhost.demo_accounts.stdin';

    public const DEFAULT_ADMIN_ROLE = 'auditor_read_only';

    protected $signature = 'onhost:demo:accounts
        {--domain=demo.onhost.cz : E-mail domain of the demo accounts}
        {--admin-role='.self::DEFAULT_ADMIN_ROLE.' : Global staff role of admin@ — never one that decides approvals}
        {--stdin : Read the shared password from standard input}
        {--reset-password : Set the shared password again on demo accounts that exist (re-enables ones --remove disabled)}
        {--remove : Disable the demo accounts: sign-in off, sessions and API tokens ended, partner records suspended; nothing is deleted}
        {--i-know-this-is-not-production : Required unless APP_ENV=staging (the staging host runs with APP_ENV=production)}';

    protected $description = 'Create (or disable) the shared-password demo accounts of a staging: users, organizations, partners and roles only';

    public function handle(DemoAccountRoster $roster): int
    {
        if (! app()->environment('staging') && ! $this->option('i-know-this-is-not-production')) {
            $this->error('Refused: APP_ENV is '.app()->environment().'. Demo accounts share one password; run this only on a staging, with --i-know-this-is-not-production.');

            return self::FAILURE;
        }
        $domain = mb_strtolower(trim((string) $this->option('domain')));
        if (preg_match('/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
            $this->error("Refused: {$domain} is not a domain name.");

            return self::FAILURE;
        }
        $adminRole = (string) $this->option('admin-role');
        if (($refusal = $this->refuseAdminRole($adminRole)) !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }
        $accounts = DemoAccountRoster::accounts($domain, $adminRole);
        if ($this->option('remove')) {
            return $this->remove($roster, $accounts, $domain);
        }

        $needsPassword = (bool) $this->option('reset-password') || collect($accounts)->contains(fn (array $a) => $roster->existing($a['email']) === null);
        $password = null;
        if ($needsPassword) {
            $password = $this->password();
            if ($password === null) {
                return self::FAILURE;
            }
        }

        return $this->ensureAll($roster, $accounts, $domain, $password);
    }

    /** @param list<array<string, mixed>> $accounts */
    private function ensureAll(DemoAccountRoster $roster, array $accounts, string $domain, ?string $password): int
    {
        $rows = [];
        $failed = false;
        /** @var array<string, Partner> $partners */
        $partners = [];
        foreach ($accounts as $account) {
            try {
                $result = $roster->ensure($account, $domain, $password, (bool) $this->option('reset-password'), $partners);
            } catch (Throwable $e) {
                $rows[] = ['email' => $account['email'], 'role' => $account['role'], 'organization' => $account['organization'] ?? '—', 'partner' => '', 'result' => 'FAILED: '.$e->getMessage()];
                $failed = true;

                continue;
            }
            $rows[] = $result['row'];
            $failed = $failed || $result['failed'];
            if ($result['partner'] !== null) {
                $partners[$account['key']] = $result['partner'];
            }
        }
        $this->table(['E-mail', 'Role', 'Organization', 'Partner', 'Result'], $rows);
        $this->line('Staff sign in at '.rtrim((string) config('app.url'), '/').'/sprava and must enrol MFA (onhost:staff:totp helps); customers and partners at /panel and /partner.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $accounts */
    private function remove(DemoAccountRoster $roster, array $accounts, string $domain): int
    {
        $rows = [];
        $failed = false;
        foreach ($accounts as $account) {
            try {
                $rows[] = $roster->disable($account, $domain);
            } catch (Throwable $e) {
                $rows[] = ['email' => $account['email'], 'role' => $account['role'], 'organization' => $account['organization'] ?? '—', 'partner' => '', 'result' => 'FAILED: '.$e->getMessage()];
                $failed = true;
            }
        }
        $this->table(['E-mail', 'Role', 'Organization', 'Partner', 'Result'], $rows);
        $this->line('Nothing was deleted. Organizations stay (erasure is the owner\'s path); --reset-password re-enables the accounts.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function refuseAdminRole(string $role): ?string
    {
        // the staff roles are the catalogue's global ones: neither an organization/project role nor a one-service capability
        if (! RoleResolver::exists($role) || in_array($role, RoleCatalog::customerRoleKeys(), true) || RoleCatalog::isResourceRole($role)) {
            return "Refused: {$role} is not a global staff role.";
        }
        if (StaffAccountCommandHandler::decidesApprovals($role)) {
            return "Refused: {$role} decides approvals (iam.approval.decide). A password several people know is never the second person of four eyes; make a personal account with onhost:staff:create.";
        }
        $risky = array_values(array_filter(RoleResolver::grantable($role), fn (string $p) => PermissionCatalog::risk($p) !== PermissionCatalog::NORMAL));
        if ($risky !== []) {
            $this->warn("{$role} holds high-risk permissions (".implode(', ', $risky).') on an installation that may talk to live panels.');
        }

        return null;
    }

    /** The shared password: hidden prompt or stdin, the platform's policy (as registration and password change), never echoed. */
    private function password(): ?string
    {
        $password = $this->option('stdin')
            ? trim((string) stream_get_contents($this->stdin()))
            : (string) $this->secret('Shared demo password (at least 12 characters, letters and numbers)');
        $validator = Validator::make(['password' => $password], ['password' => ['required', 'string', Password::min(12)->letters()->numbers()->uncompromised()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->get('password') as $message) {
                $this->error($message);
            }

            return null;
        }

        return $password;
    }

    /** @return resource */
    private function stdin()
    {
        $bound = app()->bound(self::STDIN_BINDING) ? app(self::STDIN_BINDING) : null;

        return is_resource($bound) ? $bound : STDIN;
    }
}
