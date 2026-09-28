<?php

declare(strict_types=1);

namespace App\Console\Commands\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\ApiAccessRevocation;
use Onhost\Domain\Identity\Commands\StaffAccountCommand;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\Commands\PartnerCommand;
use Onhost\Domain\Partners\Commands\PartnerPortalCommand;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use RuntimeException;

/**
 * The demo sign-ins of a staging that is wired to live panels (`onhost:demo:accounts`): people, organizations, partner
 * records and policy bindings — nothing else. No service, subscription, provider binding, domain, invoice, wallet movement
 * or ticket is made, so no panel, provisioning or billing job has anything to act on.
 *
 * Every account carries the marker `demo_account` (users.preferences, organizations.settings) and lives under one e-mail
 * domain; only an account with BOTH is ever changed again (password reset, disabling). A real person who happens to hold
 * one of the addresses is left alone. Staff are made by the command bus (`identity.staff.create`, as onhost:staff:create),
 * partners by the partner programme's own apply → approve → change-request → decide path.
 */
final class DemoAccountRoster
{
    public const MARKER = 'demo_account';

    /** Who acted, in the audit trail and on the partner records: the command line, nobody signed in. */
    public const REQUESTER = 'cli:demo:accounts';

    private const NOTE = 'Demo účet (onhost:demo:accounts)';

    public function __construct(
        private readonly CommandBus $bus,
        private readonly OrganizationService $organizations,
        private readonly PartnerService $partners,
        private readonly AuditRecorder $audit,
        private readonly StepUpService $stepUp,
        private readonly ApiAccessRevocation $revocation,
    ) {}

    /**
     * The six accounts, partners before the customer (the customer is attributed to the reseller).
     *
     * @return list<array{key:string, email:string, name:string, role:string, organization?:string, partner?:array{model:string, whitelabel_scope?:string}, staff_role?:string, attribute_to?:string}>
     */
    public static function accounts(string $domain, string $adminRole): array
    {
        return [
            ['key' => 'reseller', 'email' => "reseller@{$domain}", 'name' => 'Demo Reseller', 'role' => 'owner · partner share, white-label full', 'organization' => 'Demo reseller s.r.o.', 'partner' => ['model' => 'share', 'whitelabel_scope' => 'full']],
            ['key' => 'affiliate', 'email' => "affil@{$domain}", 'name' => 'Demo Affiliate', 'role' => 'owner · partner oneoff', 'organization' => 'Demo affiliate s.r.o.', 'partner' => ['model' => 'oneoff']],
            ['key' => 'partner', 'email' => "partner@{$domain}", 'name' => 'Demo Partner', 'role' => 'owner · partner share', 'organization' => 'Demo partner s.r.o.', 'partner' => ['model' => 'share']],
            ['key' => 'customer', 'email' => "zakaznik@{$domain}", 'name' => 'Demo Zákazník', 'role' => 'owner', 'organization' => 'Demo zákazník s.r.o.', 'attribute_to' => 'reseller'],
            ['key' => 'support', 'email' => "podpora@{$domain}", 'name' => 'Demo Podpora', 'role' => 'staff · support_manager', 'staff_role' => 'support_manager'],
            ['key' => 'admin', 'email' => "admin@{$domain}", 'name' => 'Demo Admin', 'role' => "staff · {$adminRole}", 'staff_role' => $adminRole],
        ];
    }

    public static function isDemo(User $user, string $domain): bool
    {
        return data_get($user->preferences, self::MARKER) === true && str_ends_with(mb_strtolower($user->email), '@'.mb_strtolower($domain));
    }

    public function existing(string $email): ?User
    {
        return User::withTrashed()->where('email', mb_strtolower($email))->first();
    }

    /**
     * @param  array<string, mixed>  $account  one entry of accounts()
     * @param  array<string, Partner>  $partners  partners made or found so far, by account key
     * @return array{row: array<string, string>, partner: ?Partner, failed: bool}
     */
    public function ensure(array $account, string $domain, ?string $password, bool $resetPassword, array $partners): array
    {
        $user = $this->existing($account['email']);
        if ($user !== null) {
            return $this->existingAccount($account, $user, $domain, $password, $resetPassword);
        }
        if ($password === null) {
            throw new RuntimeException('A password is required to create '.$account['email'].'.');
        }

        return isset($account['staff_role']) ? $this->createStaff($account, $password) : $this->createCustomer($account, $password, $partners);
    }

    /**
     * Turns sign-in off for one demo account: state `suspended` (User::isActive() is false — the login and the authorizer refuse
     * it), a random password, every session, remembered device, step-up and personal API token ended, and its partner record
     * suspended. Nothing is deleted: erasing an account is the owner's path (owner-only, step-up, 14 days), not a script's.
     *
     * @return array<string, string>
     */
    public function disable(array $account, string $domain): array
    {
        $user = $this->existing($account['email']);
        if ($user === null) {
            return $this->row($account, 'absent');
        }
        if (! self::isDemo($user, $domain)) {
            return $this->row($account, 'not a demo account — left alone');
        }
        $organization = $this->ownOrganization($user);
        $already = $user->state === 'suspended';
        $partnerNote = DB::transaction(function () use ($user, $organization) {
            $user->forceFill(['state' => 'suspended', 'password' => Str::random(64), 'remember_token' => Str::random(60)])->save();
            $this->endAccess($user);
            $partner = $organization !== null ? $this->partners->partnerFor($organization) : null;
            if ($partner !== null && $partner->state === 'active') {
                $this->bus->dispatch(new PartnerCommand(self::key('state'), ['op' => 'state', 'partner_id' => $partner->id, 'state' => 'suspended', 'reason' => self::NOTE.': --remove']), $this->context($organization?->id));
            }
            $this->audit->record($this->context($organization?->id), 'identity.demo_account.disabled', 'succeeded', ['email' => $user->email], 'user', $user->id);

            return $partner !== null ? $partner->code.' '.$partner->refresh()->state : '';
        });

        return $this->row($account, $already ? 'already disabled (sessions and tokens ended again)' : 'disabled', $organization->name ?? '', $partnerNote);
    }

    // ── making ──────────────────────────────────────────────────────────────

    /** @return array{row: array<string, string>, partner: ?Partner, failed: bool} */
    private function createStaff(array $account, string $password): array
    {
        return DB::transaction(function () use ($account, $password) {
            $result = $this->bus->dispatch(
                new StaffAccountCommand(self::key('staff.create'), ['email' => $account['email'], 'name' => $account['name'], 'role' => $account['staff_role'], 'password' => $password]),
                CommandContext::system(self::REQUESTER),
            );
            if (! (bool) data_get($result, 'created')) { // a role that decides approvals waits the time lock — never bypassed here
                return ['row' => $this->row($account, 'NOT made: time lock (approval '.data_get($result, 'approval_id').', repeat after '.data_get($result, 'not_before').')'), 'partner' => null, 'failed' => true];
            }
            $user = User::query()->findOrFail((string) data_get($result, 'user_id'));
            $user->forceFill(['preferences' => array_merge((array) ($user->preferences ?? []), [self::MARKER => true])])->save();
            $this->audit->record($this->context(), 'identity.demo_account.created', 'succeeded', ['email' => $user->email, 'kind' => $account['key'], 'role' => $account['staff_role']], 'user', $user->id);

            return ['row' => $this->row($account, 'created'), 'partner' => null, 'failed' => false];
        });
    }

    /**
     * @param  array<string, Partner>  $partners
     * @return array{row: array<string, string>, partner: ?Partner, failed: bool}
     */
    private function createCustomer(array $account, string $password, array $partners): array
    {
        return DB::transaction(function () use ($account, $password, $partners) {
            $user = User::query()->create(['name' => $account['name'], 'email' => $account['email'], 'password' => $password, 'locale' => 'cs', 'timezone' => 'Europe/Prague', 'state' => 'active', 'email_verified_at' => now(), 'password_changed_at' => now(), 'preferences' => [self::MARKER => true]]);
            // no IČO and no VAT number: nothing for VIES to be asked about, no b2b status claimed for a company that does not exist
            $organization = $this->organizations->create($user, ['name' => $account['organization'], 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK', 'billing_email' => $account['email']], $this->context());
            $organization->forceFill(['settings' => array_merge((array) ($organization->settings ?? []), [self::MARKER => true])])->save();
            $this->audit->record($this->context($organization->id), 'identity.demo_account.created', 'succeeded', ['email' => $user->email, 'kind' => $account['key']], 'user', $user->id);

            $partner = isset($account['partner']) ? $this->makePartner($organization, $account['partner']) : null;
            $note = $partner !== null ? $partner->code.' '.$partner->model.' '.$partner->state.($partner->whitelabel_scope ? ' · white-label '.$partner->whitelabel_scope : '') : '';
            $referrer = isset($account['attribute_to']) ? ($partners[$account['attribute_to']] ?? null) : null;
            if ($referrer !== null && $referrer->isActive() && $this->partners->attribute($organization, $referrer->code, $this->context($organization->id)) !== null) {
                $note = 'client of '.$referrer->code; // PartnerService::attribute only binds the organization: no panel, no billing
            }

            return ['row' => $this->row($account, 'created', $organization->name, $note), 'partner' => $partner, 'failed' => false];
        });
    }

    /**
     * The programme's own path, run as the system: the organization applies (PartnerPortalCommand `apply`), is approved
     * (PartnerCommand `approve`); a white-label scope is asked for as a contract change (`change.request`) and decided
     * (`model.decide`, which decides any term) — the scope has no money in it, so it applies the day it is approved.
     *
     * @param  array{model:string, whitelabel_scope?:string}  $terms
     */
    private function makePartner(Organization $organization, array $terms): Partner
    {
        $context = $this->context($organization->id);
        $this->bus->dispatch(new PartnerPortalCommand($organization->id, self::key('partner.apply'), ['op' => 'apply', 'model' => $terms['model'], 'company' => $organization->name, 'note' => self::NOTE]), $context);
        $partner = $this->partners->partnerFor($organization) ?? throw new RuntimeException('The partner application of '.$organization->name.' was not recorded.');
        $this->bus->dispatch(new PartnerCommand(self::key('partner.approve'), ['op' => 'approve', 'partner_id' => $partner->id]), $context);
        if (isset($terms['whitelabel_scope'])) {
            $request = $this->bus->dispatch(new PartnerPortalCommand($organization->id, self::key('partner.change'), ['op' => 'change.request', 'kind' => 'whitelabel_scope', 'value' => $terms['whitelabel_scope'], 'note' => self::NOTE]), $context);
            $this->bus->dispatch(new PartnerCommand(self::key('partner.decide'), ['op' => 'model.decide', 'request_id' => (string) data_get($request, 'id'), 'decision' => 'approve', 'note' => self::NOTE]), $context);
        }

        return $partner->refresh();
    }

    // ── what exists already ─────────────────────────────────────────────────

    /** @return array{row: array<string, string>, partner: ?Partner, failed: bool} */
    private function existingAccount(array $account, User $user, string $domain, ?string $password, bool $resetPassword): array
    {
        $organization = $this->ownOrganization($user);
        $partner = $organization !== null ? $this->partners->partnerFor($organization) : null;
        $note = $partner !== null ? $partner->code.' '.$partner->model.' '.$partner->state : '';
        if (! self::isDemo($user, $domain)) {
            return ['row' => $this->row($account, 'exists — not a demo account, left alone', $organization->name ?? ''), 'partner' => null, 'failed' => false];
        }
        if ($user->deleted_at !== null) {
            return ['row' => $this->row($account, 'exists, deleted — left alone', $organization->name ?? '', $note), 'partner' => null, 'failed' => false];
        }
        if (! $resetPassword || $password === null) {
            $state = $user->state === 'active' ? 'exists (skipped)' : "exists, {$user->state} (skipped; --reset-password re-enables it)";

            return ['row' => $this->row($account, $state, $organization->name ?? '', $note), 'partner' => $partner, 'failed' => false];
        }
        $reenabled = $user->state === 'suspended';
        DB::transaction(function () use ($user, $password, $organization, &$partner) {
            // a password change ends everything the old one opened, as MeController::password does
            $user->forceFill(['password' => $password, 'password_changed_at' => now(), 'remember_token' => Str::random(60), 'failed_login_attempts' => 0, 'locked_until' => null, 'state' => $user->state === 'suspended' ? 'active' : $user->state])->save();
            $this->endAccess($user);
            if ($partner !== null && $partner->state === 'suspended') {
                $this->bus->dispatch(new PartnerCommand(self::key('state'), ['op' => 'state', 'partner_id' => $partner->id, 'state' => 'active', 'reason' => self::NOTE.': --reset-password']), $this->context($organization?->id));
                $partner = $partner->refresh();
            }
            $this->audit->record($this->context($organization?->id), 'identity.demo_account.password_reset', 'succeeded', ['email' => $user->email], 'user', $user->id);
        });
        $note = $partner !== null ? $partner->code.' '.$partner->model.' '.$partner->state : '';

        return ['row' => $this->row($account, $reenabled ? 'password reset, re-enabled' : 'password reset', $organization->name ?? '', $note), 'partner' => $partner, 'failed' => false];
    }

    private function ownOrganization(User $user): ?Organization
    {
        return Organization::query()->where('owner_user_id', $user->id)->get()->first(fn (Organization $o) => data_get($o->settings, self::MARKER) === true);
    }

    /** Every session, remembered device, step-up and personal API token of the account ends. */
    private function endAccess(User $user): void
    {
        $this->stepUp->revokeAll($user);
        $this->revocation->revokePersonalTokens($user);
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function context(?string $organizationId = null): CommandContext
    {
        $context = CommandContext::system(self::REQUESTER);

        return $organizationId !== null ? $context->withScope($organizationId) : $context;
    }

    private static function key(string $what): string
    {
        return 'demo.'.$what.':'.Str::ulid();
    }

    /** @return array<string, string> */
    private function row(array $account, string $result, string $organization = '', string $partner = ''): array
    {
        return ['email' => $account['email'], 'role' => $account['role'], 'organization' => $organization !== '' ? $organization : ($account['organization'] ?? '—'), 'partner' => $partner, 'result' => $result];
    }
}
