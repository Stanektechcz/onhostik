<?php

declare(strict_types=1);

use App\Console\Commands\Demo\DemoAccountRoster;
use App\Console\Commands\DemoAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Commands\StaffAccountCommandHandler;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerChangeRequest;

/*
 * onhost:demo:accounts — the shared-password demo sign-ins of a staging wired to live panels. Only people, organizations,
 * partner records and role bindings; the admin never decides approvals; the password never comes from an option.
 */

const DMO_SHARED_PHRASE = 'Ukazka-heslo-2026';
const DMO_PROMPT = 'Shared demo password (at least 12 characters, letters and numbers)';
const DMO_FLAG = '--i-know-this-is-not-production';

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]); // the password policy asks the breach list
});

function dmoRun(array $options = [], ?string $password = DMO_SHARED_PHRASE): int
{
    $pending = test()->artisan('onhost:demo:accounts', [DMO_FLAG => true] + $options);
    if ($password !== null) {
        $pending->expectsQuestion(DMO_PROMPT, $password);
    }

    return $pending->run();
}

/** @return list<string> */
function dmoEmails(): array
{
    return ['zakaznik@demo.onhost.cz', 'reseller@demo.onhost.cz', 'affil@demo.onhost.cz', 'partner@demo.onhost.cz', 'podpora@demo.onhost.cz', 'admin@demo.onhost.cz'];
}

function dmoUser(string $local): User
{
    return User::query()->where('email', $local.'@demo.onhost.cz')->firstOrFail();
}

function dmoPartnerOf(string $local): Partner
{
    return Partner::query()->where('organization_id', Organization::query()->where('owner_user_id', dmoUser($local)->id)->value('id'))->firstOrFail();
}

function dmoGlobalRole(string $local): ?string
{
    return PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', dmoUser($local)->id)->where('scope_type', 'global')->value('role_key');
}

it('refuses to run without the flag, whatever APP_ENV says unless it is staging', function () {
    $this->artisan('onhost:demo:accounts')->expectsOutputToContain('Refused')->assertExitCode(1);
    app()['env'] = 'production';
    $this->artisan('onhost:demo:accounts')->expectsOutputToContain('APP_ENV is production')->assertExitCode(1);

    expect(User::query()->where('email', 'like', '%@demo.onhost.cz')->count())->toBe(0);
});

it('creates the six accounts — people, organizations, partners and roles, nothing a panel or billing job could act on', function () {
    expect(dmoRun())->toBe(0);

    expect(User::query()->whereIn('email', dmoEmails())->count())->toBe(6);
    foreach (dmoEmails() as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();
        expect(Hash::check(DMO_SHARED_PHRASE, (string) $user->password))->toBeTrue()
            ->and($user->state)->toBe('active')
            ->and(DemoAccountRoster::isDemo($user, 'demo.onhost.cz'))->toBeTrue();
    }

    // the customer: a Czech company in CZK, no VAT claimed, no VIES asked
    $customerOrg = Organization::query()->where('owner_user_id', dmoUser('zakaznik')->id)->firstOrFail();
    expect($customerOrg->name)->toBe('Demo zákazník s.r.o.')
        ->and($customerOrg->type)->toBe('company')
        ->and($customerOrg->country)->toBe('CZ')
        ->and($customerOrg->currency)->toBe('CZK')
        ->and($customerOrg->vat_id)->toBeNull()
        ->and($customerOrg->vat_status)->not->toBe('valid')
        ->and(data_get($customerOrg->settings, 'demo_account'))->toBeTrue();

    // partners: the programme's own apply → approve (→ change request → decision)
    $reseller = dmoPartnerOf('reseller');
    expect($reseller->state)->toBe('active')->and($reseller->model)->toBe('share')->and($reseller->whitelabel_scope)->toBe('full');
    $change = PartnerChangeRequest::query()->where('partner_id', $reseller->id)->sole();
    expect($change->kind)->toBe('whitelabel_scope')->and($change->state)->toBe(PartnerChangeRequest::APPROVED)->and($change->applied_at)->not->toBeNull();
    expect(dmoPartnerOf('affil')->model)->toBe('oneoff')->and(dmoPartnerOf('affil')->state)->toBe('active');
    expect(dmoPartnerOf('partner')->model)->toBe('share')->and(dmoPartnerOf('partner')->state)->toBe('active')->and(dmoPartnerOf('partner')->whitelabel_scope ?: 'basic')->toBe('basic');
    expect($customerOrg->partner_organization_id)->toBe($reseller->organization_id); // the partner panel shows a client

    // staff: made through the bus, with the roles asked for
    expect(dmoUser('zakaznik')->is_staff)->toBeFalse()->and(dmoUser('reseller')->is_staff)->toBeFalse()->and(dmoGlobalRole('zakaznik'))->toBeNull();
    expect(dmoUser('podpora')->is_staff)->toBeTrue()->and(dmoGlobalRole('podpora'))->toBe('support_manager');
    expect(dmoUser('admin')->is_staff)->toBeTrue()->and(dmoGlobalRole('admin'))->toBe(DemoAccounts::DEFAULT_ADMIN_ROLE);
    expect(DB::table('audit_events')->where('action', 'identity.staff.created')->count())->toBe(2);

    // nothing any panel, provisioning or billing job could act on
    foreach (['services', 'provider_bindings', 'subscriptions', 'invoices', 'domains', 'orders', 'operations', 'support_tickets', 'wallet_topups', 'ledger_transactions'] as $table) {
        expect(DB::table($table)->count())->toBe(0, "{$table} must stay empty");
    }
});

it('gives the admin no approval power: the default role does not decide approvals, and an approver role is refused', function () {
    expect(in_array(ApprovalService::PERMISSION, RoleResolver::grantable(DemoAccounts::DEFAULT_ADMIN_ROLE), true))->toBeFalse()
        ->and(StaffAccountCommandHandler::decidesApprovals(DemoAccounts::DEFAULT_ADMIN_ROLE))->toBeFalse();

    foreach (['platform_owner', 'iam_admin', 'billing_finance_admin'] as $role) {
        $this->artisan('onhost:demo:accounts', [DMO_FLAG => true, '--admin-role' => $role])->expectsOutputToContain('decides approvals')->assertExitCode(1);
    }
    $this->artisan('onhost:demo:accounts', [DMO_FLAG => true, '--admin-role' => 'owner'])->expectsOutputToContain('not a global staff role')->assertExitCode(1);

    expect(dmoRun())->toBe(0);
    expect(ApprovalService::deciders()->pluck('email')->all())->not->toContain('admin@demo.onhost.cz');
    expect(User::query()->where('email', 'like', '%@demo.onhost.cz')->count())->toBe(6);
});

it('is idempotent: a second run reports the accounts, asks for no password and changes none', function () {
    expect(dmoRun())->toBe(0);
    $hashes = User::query()->whereIn('email', dmoEmails())->pluck('password', 'email')->all();
    $counts = [User::query()->count(), Organization::query()->count(), Partner::query()->count(), PolicyBinding::query()->count()];

    $this->artisan('onhost:demo:accounts', [DMO_FLAG => true])->expectsOutputToContain('exists (skipped)')->assertExitCode(0);

    expect([User::query()->count(), Organization::query()->count(), Partner::query()->count(), PolicyBinding::query()->count()])->toBe($counts)
        ->and(User::query()->whereIn('email', dmoEmails())->pluck('password', 'email')->all())->toBe($hashes);
});

it('resets the shared password only when asked', function () {
    expect(dmoRun())->toBe(0);
    expect(dmoRun(['--reset-password' => true], 'Nove-demo-heslo-2027'))->toBe(0);

    expect(Hash::check('Nove-demo-heslo-2027', (string) dmoUser('admin')->password))->toBeTrue()
        ->and(Hash::check('Nove-demo-heslo-2027', (string) dmoUser('zakaznik')->password))->toBeTrue();
});

it('reads the password from stdin with --stdin', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, DMO_SHARED_PHRASE."\n");
    rewind($stream);
    app()->instance(DemoAccounts::STDIN_BINDING, $stream);

    $this->artisan('onhost:demo:accounts', [DMO_FLAG => true, '--stdin' => true])->assertExitCode(0);

    expect(User::query()->whereIn('email', dmoEmails())->count())->toBe(6)
        ->and(Hash::check(DMO_SHARED_PHRASE, (string) dmoUser('partner')->password))->toBeTrue();
});

it('enforces the platform password policy and makes nothing with a weak password', function () {
    expect(dmoRun([], 'jenpismenadlouha'))->toBe(1);
    expect(dmoRun([], 'kratke1'))->toBe(1);

    expect(User::query()->where('email', 'like', '%@demo.onhost.cz')->count())->toBe(0);
});

it('leaves alone a real account that holds a demo address', function () {
    $real = User::query()->create(['name' => 'Skutečný', 'email' => 'admin@demo.onhost.cz', 'password' => 'Realne-heslo-2026', 'state' => 'active', 'is_staff' => false]);

    expect(dmoRun(['--reset-password' => true]))->toBe(0);
    expect(Hash::check('Realne-heslo-2026', (string) $real->refresh()->password))->toBeTrue()->and($real->is_staff)->toBeFalse();

    $this->artisan('onhost:demo:accounts', [DMO_FLAG => true, '--remove' => true])->expectsOutputToContain('not a demo account')->assertExitCode(0);
    expect($real->refresh()->state)->toBe('active');
});

it('disables the demo accounts with --remove: no sign-in, partners suspended, nothing deleted; --reset-password brings them back', function () {
    expect(dmoRun())->toBe(0);
    $organizations = Organization::query()->count();

    $this->artisan('onhost:demo:accounts', [DMO_FLAG => true, '--remove' => true])->expectsOutputToContain('disabled')->assertExitCode(0);

    foreach (dmoEmails() as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();
        expect($user->state)->toBe('suspended')->and($user->isActive())->toBeFalse()
            ->and(Hash::check(DMO_SHARED_PHRASE, (string) $user->password))->toBeFalse();
    }
    expect(dmoPartnerOf('reseller')->state)->toBe('suspended')->and(dmoPartnerOf('affil')->state)->toBe('suspended')
        ->and(Organization::query()->count())->toBe($organizations);
    $this->postJson('/v1/auth/login', ['email' => 'zakaznik@demo.onhost.cz', 'password' => DMO_SHARED_PHRASE])->assertStatus(422);

    expect(dmoRun(['--reset-password' => true]))->toBe(0);
    expect(dmoUser('zakaznik')->state)->toBe('active')->and(dmoPartnerOf('reseller')->state)->toBe('active');
    $this->postJson('/v1/auth/login', ['email' => 'zakaznik@demo.onhost.cz', 'password' => DMO_SHARED_PHRASE])->assertOk();
});

it('lets the customer and partner demo accounts sign in with the shared password; staff are asked to enrol MFA', function () {
    dmoRun();
    foreach (['zakaznik', 'reseller', 'affil', 'partner'] as $local) {
        auth()->forgetGuards();
        $this->postJson('/v1/auth/login', ['email' => "{$local}@demo.onhost.cz", 'password' => DMO_SHARED_PHRASE])->assertOk();
        $this->postJson('/v1/auth/logout')->assertOk();
    }
    foreach (['podpora', 'admin'] as $local) {
        auth()->forgetGuards();
        $this->postJson('/v1/auth/login', ['email' => "{$local}@demo.onhost.cz", 'password' => DMO_SHARED_PHRASE])->assertStatus(403)->assertSee('mfa_enrolment_required');
    }
});
