<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E10 — a customer ends a web hosting and, in the end, the account, touching only the real HTTP routes and the scheduled commands.
 *
 * At period end: the customer cancels (POST /v1/subscriptions/{id}/cancel), changes their mind (the same route, cancel=false) and the
 * period renews from the credit; cancels again, and when the period runs out `onhost:billing:renewals` ends the service: a final
 * archive is taken, the site is deactivated and waits out the restore window, then `onhost:services:purge` removes it from the panel
 * with everything that hung from it (databases and their logins, FTP and SSH accounts, cron jobs, alias vhosts) while a site and a
 * mailbox of the same panel client that the platform never made stay exactly as they were.
 * Immediately: the destructive preview, a fresh step-up, a stale confirmation refused, the undo (a plain resume) inside the window.
 * Withdrawal: a consumer's 14 days, the refund to the credit; a company's refusal; the day after the deadline.
 * The account: it outlives its services (login, invoices, the credit), and its erasure is the owner's alone, takes a step-up, waits 14
 * days, can be stopped inside the window and is carried out by the scheduled command.
 *
 * Only the edges are doubles (hosting panel and DNS server by Http::fake, the file half of the node in memory, the card gateway,
 * the mail). Asserts do not depend on row order, so the flow holds on SQLite and PostgreSQL alike.
 */

const E10_VENDORS = ['ispconfig', 'wedos', 'subreg', 'powerdns', 'pdns', 'aapanel', 'proxmox', 'pterodactyl', 'comgate'];

beforeEach(function () {
    // the catalogue is seeded "a day ago" relative to the clock, so the fixed test day must be set BEFORE the seed: with the clock still real,
    // the prices of a run later than 11:00 UTC the next day were not yet in force on 2026-10-07 and every quote failed (price_unavailable)
    $this->travelTo(CarbonImmutable::parse('2026-10-07 11:00:00', 'UTC'));
    e2eSeedPlatform();
    e2eWebInfrastructure();
    e2eComgateEnvironment();
    pdnsLab();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    config()->set('onhost.dns.web_ipv4', '192.0.2.10');
    Storage::fake('local'); // the final archive and the exports land here, not on the developer's disk
    e2eIspFileSeams();
    Http::preventStrayRequests();
});

afterEach(function () {
    e2eIspFileSeamsOff();
});

/** A throwaway test value (never a real secret): long enough for the password rules, different per purpose. */
function e10Secret(string $purpose): string
{
    return 'e10-'.hash('sha1', $purpose.'|e10');
}

/** Every fake the flow talks to, on one `$panel`: the site tree and the panel first, then the gateway and the DNS server. */
function e10Fakes(array &$panel, array &$gate, array &$dns): void
{
    e2eIspSiteTree($panel);
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    e2ePowerDnsFake($dns);
}

/** Sign up and verify (company by default, a consumer without an ICO when `$consumer`). @return array{0:User,1:Organization,2:string} */
function e10SignUp(object $test, string $email, bool $consumer = false): array
{
    if (! $consumer) {
        return e2eSignUp($test, $email, 'Obchod e10 s.r.o.');
    }
    $password = e10Secret('consumer');
    $test->withHeaders(e2eHeaders('signup-'.$email))->postJson('/v1/auth/register', ['name' => 'Jana Nováková', 'email' => $email, 'password' => $password, 'organization' => 'Jana Nováková', 'type' => 'person', 'country' => 'CZ', 'terms' => true])->assertCreated();
    $user = User::query()->where('email', $email)->firstOrFail();
    $test->withHeaders(e2eHeaders('verify-'.$email))->postJson('/v1/auth/verify-email', ['token' => e2eVerificationToken($user)])->assertOk();

    return [$user->refresh(), Organization::query()->where('owner_user_id', $user->id)->firstOrFail(), $password];
}

/**
 * A web hosting bought the way a customer buys it: cart → quote → card order → the gateway's callback → the saga. The customer's
 * zone for the domain exists beforehand, so the site's address records are published into it.
 *
 * @return array{0:User,1:Organization,2:string,3:Service,4:DnsZone}
 */
function e10Provision(object $test, array &$panel, array &$gate, string $domain, string $email = 'jana.e10@example.cz', bool $consumer = false): array
{
    [$user, $org, $password] = e10SignUp($test, $email, $consumer);
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'name' => $domain, 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id, 'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']]);
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => 'office', 'type' => 'A', 'content' => '198.51.100.5', 'ttl' => 600, 'managed_by' => 'customer']); // the customer's own record: never ours to remove

    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => $domain]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $gate['trans_id'] = 'E10-'.bin2hex(random_bytes(4)); // one gateway payment per order: a second organization in the same flow is not a repeated callback
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE);

    return [$user, $org, $password, $service, $zone];
}

/** One feature action of the customer (database, FTP, cron…): the route, then the queue. */
function e10Action(object $test, Service $service, string $action, array $params, string $label): TestResponse
{
    $answer = $test->withHeaders(e2eHeaders($label))->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params])->assertStatus(202);
    driveOperations();

    return $answer;
}

/** What the platform put under the site, through the customer's own routes: a database, an FTP and an SSH account, a cron job, an alias vhost. */
function e10Furnish(object $test, array &$panel, Service $service, string $domain): void
{
    // the plan sells no SSH of its own; the agent account the platform makes for a site's file tools is the one shell row a site carries
    $siteId = (int) e10Site($panel, $domain)['domain_id'];
    $panel['shells'][301] = ['shell_user_id' => 301, 'username' => 'onhost-agent-'.$siteId, 'parent_domain_id' => $siteId, 'active' => 'y', 'ssh_rsa' => 'ssh-ed25519 AAAA'];
    e10Action($test, $service, 'database.create', ['name' => 'shop', 'password' => e10Secret('')], 'db');
    e10Action($test, $service, 'ftp.create', ['user' => 'upload', 'password' => e10Secret('')], 'ftp');
    e10Action($test, $service, 'cron.create', ['schedule' => '*/15 * * * *', 'command' => 'php cron.php'], 'cron');
    e10Action($test, $service, 'subdomain.add', ['domain' => 'blog.'.$domain], 'alias');
}

/** A site and a mailbox of the same panel client that the platform never made (the panel's HISTORICAL rows). */
function e10Historical(array &$panel, string $domain): void
{
    $panel['sites'][50] = ['domain_id' => 50, 'domain' => 'stary-web.cz', 'sys_groupid' => 12, 'system_user' => 'web50', 'document_root' => '/var/www/clients/client12/web50'];
    $panel['databases'][50] = ['database_id' => 50, 'database_name' => 'c12_stary', 'parent_domain_id' => 50, 'database_user_id' => 50];
    $panel['db_users'][50] = ['database_user_id' => 50, 'database_user' => 'c12_stary'];
    $panel['ftps'][50] = ['ftp_user_id' => 50, 'username' => 'c12stary', 'parent_domain_id' => 50, 'active' => 'y'];
    $panel['crons'][50] = ['id' => 50, 'parent_domain_id' => 50, 'command' => 'php /var/www/stary/cron.php', 'active' => 'y'];
    $panel['aliases'][50] = ['domain_id' => 50, 'domain' => 'www.stary-web.cz', 'parent_domain_id' => 50];
}

/** The platform's own ISPConfig site: the one whose domain is `$domain`. @return array<string,mixed> */
function e10Site(array $panel, string $domain): ?array
{
    return collect($panel['sites'])->first(fn (array $site) => ($site['domain'] ?? '') === $domain);
}

/** A browser nobody is signed in on: a fresh session, fresh guards, and no cookie (step-up included) of the person before. */
function e10Forget(object $test): void
{
    $test->flushSession();
    app('auth')->forgetGuards();
    (function () {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
    })->call($test);
}

/** Switch the browser to another person. */
function e10ActAs(object $test, User $user): void
{
    e10Forget($test);
    $test->actingAs($user);
}

/** Names of the events of an organization the outbox holds, once relayed. @return array<string,int> */
function e10Events(Organization $org): array
{
    app(OutboxPublisher::class)->relayPending();

    return OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
}

/** The audit actions of an organization. @return list<string> */
function e10Audit(Organization $org): array
{
    return AuditEvent::query()->where('organization_id', $org->id)->pluck('action')->unique()->values()->all();
}

/** A customer-facing answer or mail must not name a vendor. */
function e10AssertNoVendor(mixed $payload): void
{
    $text = mb_strtolower(is_string($payload) ? $payload : (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    foreach (E10_VENDORS as $vendor) {
        expect($text)->not->toContain($vendor);
    }
}

it('ends a web hosting at the end of the paid period: undo, archive, deactivation, removal of everything under the site, the historical rows untouched', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    $dns = [];
    e10Fakes($panel, $gate, $dns);
    e10Historical($panel, 'obchod-e10.cz');
    [$user, $org, $password, $service, $zone] = e10Provision($this, $panel, $gate, 'obchod-e10.cz');
    $site = e10Site($panel, 'obchod-e10.cz');
    expect($site)->not->toBeNull()->and($panel['sites'])->toHaveCount(2);
    e10Furnish($this, $panel, $service, 'obchod-e10.cz');
    $siteId = (int) $site['domain_id'];
    expect($panel['databases'])->toHaveCount(2)->and($panel['ftps'])->toHaveCount(2)->and($panel['crons'])->toHaveCount(2)->and($panel['aliases'])->toHaveCount(2)->and($panel['shells'])->not->toBeEmpty();
    $before = collect($zone->records()->get())->map(fn (DnsRecord $r) => $r->name.'|'.$r->type)->sort()->values()->all();
    expect($before)->toContain('office|A');
    e2eTopUp($this, $gate, 1000);

    // 1. the customer cancels at period end: nothing stops, the service is told so
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    $end = $subscription->current_period_end->copy();
    $this->withHeaders(e2eHeaders('cancel'))->postJson("/v1/subscriptions/{$subscription->id}/cancel", ['cancel' => true])->assertOk()
        ->assertJsonPath('data.cancel_at_period_end', true)->assertJsonPath('data.auto_renew', false);
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($panel['deleted'])->toBe([]);

    // 2. …and takes it back before the end: auto-renew is what the customer had, the period renews from the credit, the service lives on
    $this->withHeaders(e2eHeaders('revoke'))->postJson("/v1/subscriptions/{$subscription->id}/cancel", ['cancel' => false])->assertOk()
        ->assertJsonPath('data.cancel_at_period_end', false)->assertJsonPath('data.auto_renew', true);
    $this->travelTo($end->copy()->addMinutes(5));
    Artisan::call('onhost:billing:renewals');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $renewedEnd = $subscription->fresh()->current_period_end->copy();
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($subscription->fresh()->state)->toBe(Subscription::ACTIVE)
        ->and($renewedEnd->gt($end))->toBeTrue()->and($panel['deleted'])->toBe([])
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'statement')->count())->toBeGreaterThanOrEqual(2);

    // 3. cancelled again, and this time the period is let run out: the renewal pass ends the service
    $this->withHeaders(e2eHeaders('cancel-2'))->postJson("/v1/subscriptions/{$subscription->id}/cancel", ['cancel' => true])->assertOk()->assertJsonPath('data.cancel_at_period_end', true);
    $this->travelTo($renewedEnd->copy()->subDay());
    Artisan::call('onhost:billing:renewals');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(Operation::query()->where('service_id', $service->id)->where('desired->action', 'terminate')->count())->toBe(0); // a day early: nothing yet
    $this->travelTo($renewedEnd->copy()->addMinutes(5));
    Artisan::call('onhost:billing:renewals');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    // 4. the site is archived first, then switched off, and waits out the restore window: nothing is deleted on the panel yet
    $service->refresh();
    $archive = Backup::query()->where('service_id', $service->id)->where('kind', 'final')->firstOrFail();
    $set = (string) data_get($archive->meta, 'set');
    expect($service->state)->toBe(ServiceStateMachine::SUSPENDED)->and($service->terminate_at)->not->toBeNull()->and($service->terminate_at->isFuture())->toBeTrue()
        ->and($archive->state)->toBe('completed')->and($archive->verify_status)->toBe('ok')->and($set)->not->toBe('')
        ->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue()
        ->and(Storage::disk('local')->exists($set.'/site-files.tar.gz'))->toBeTrue()
        ->and(collect(Storage::disk('local')->files($set))->contains(fn (string $file) => str_contains($file, 'database-')))->toBeTrue() // the database is in the set
        ->and($subscription->fresh()->state)->toBe(Subscription::CANCELLED)->and(e10Site($panel, 'obchod-e10.cz'))->not->toBeNull()
        // deactivation takes the logins into the site away (FTP and SSH accounts) and nothing else: the data stays for the restore window
        ->and(collect($panel['deleted'])->map(fn (string $entry) => explode(':', $entry)[0])->sort()->values()->all())->toBe(['sites_ftp_user_delete', 'sites_shell_user_delete'])
        ->and($panel['databases'])->toHaveCount(2)->and($panel['crons'])->toHaveCount(2)->and($panel['aliases'])->toHaveCount(2);
    $graceEnd = $service->terminate_at->copy();

    // 5. the account is still there with no running service: login, invoices, the credit
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->withHeaders(e2eHeaders('login'))->postJson('/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertOk();
    $this->withHeaders(e2eHeaders('me'))->getJson('/v1/me')->assertOk()->assertJsonPath('data.user.id', $user->id);
    $this->withHeaders(e2eHeaders('invoices'))->getJson('/v1/invoices')->assertOk()->assertJsonStructure(['data' => [['id', 'type', 'state']]]);

    // 6. the window runs out: the purge pass removes the site and every row that hung from it, and only those
    $this->travelTo($graceEnd->copy()->addMinutes(10));
    Artisan::call('onhost:services:purge');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service->refresh();
    expect($service->state)->toBe(ServiceStateMachine::TERMINATED)
        ->and(e10Site($panel, 'obchod-e10.cz'))->toBeNull()->and($panel['sites'])->toHaveCount(1)
        ->and($panel['databases'])->toHaveCount(1)->and($panel['db_users'])->toHaveCount(1)->and($panel['ftps'])->toHaveCount(1)->and($panel['crons'])->toHaveCount(1)->and($panel['aliases'])->toHaveCount(1)
        ->and(collect($panel['shells'])->where('parent_domain_id', $siteId))->toHaveCount(0)
        ->and(array_keys($panel['sites']))->toBe([50]);
    foreach ($panel['deleted'] as $entry) {
        expect($entry)->not->toEndWith(':50'); // not one delete named a row of the historical site
    }
    $deleted = array_map(fn (string $entry) => explode(':', $entry)[0], $panel['deleted']);
    foreach (['sites_database_delete', 'sites_ftp_user_delete', 'sites_shell_user_delete', 'sites_cron_delete', 'sites_web_aliasdomain_delete', 'sites_database_user_delete', 'sites_web_domain_delete'] as $function) {
        expect($deleted)->toContain($function);
    }
    expect(array_search('sites_database_user_delete', $deleted, true))->toBeGreaterThan(array_search('sites_database_delete', $deleted, true)) // a login goes after its database
        ->and(array_search('sites_web_domain_delete', $deleted, true))->toBeGreaterThan(array_search('sites_cron_delete', $deleted, true)); // the vhost goes last

    // 7. the domain no longer points at the node: the site's address records are gone, the customer's own record is not
    $shape = $zone->records()->get()->map(fn (DnsRecord $r) => $r->name.'|'.$r->type)->sort()->values()->all();
    expect($shape)->toBe(['office|A']);

    // 8. the retention of the final archive runs from the removal; the account carries on and can still read what it paid
    expect($archive->fresh()->retention_until->isFuture())->toBeTrue()->and($archive->fresh()->state)->toBe('completed');
    $this->withHeaders(e2eHeaders('services-after'))->getJson('/v1/services')->assertOk()->assertJsonMissing(['state' => ServiceStateMachine::ACTIVE]);
    $this->withHeaders(e2eHeaders('invoices-after'))->getJson('/v1/invoices')->assertOk();
    $this->withHeaders(e2eHeaders('me-after'))->getJson('/v1/me')->assertOk();
    expect(Organization::query()->find($org->id)->state)->not->toBe('closed');

    // 9. events, audit, mails: each said once, to this organization, without a vendor name
    $events = e10Events($org);
    foreach (['subscription.cancel_scheduled', 'subscription.cancel_revoked', 'subscription.expired', 'service.delegations.revoked', 'service.deletion.scheduled', 'service.deactivated', 'service.terminated'] as $name) {
        expect($events)->toHaveKey($name);
    }
    expect($events['subscription.cancel_revoked'])->toBe(1)->and($events['subscription.cancel_scheduled'])->toBe(2)->and($events['service.terminated'])->toBe(1)->and($events['service.deletion.scheduled'])->toBe(1)
        ->and(OutboxMessage::query()->where('organization_id', $org->id)->whereNull('published_at')->count())->toBe(0)
        ->and(OutboxMessage::query()->where('name', 'service.purge.leftover')->count())->toBe(0); // nothing was left behind on the node
    expect(e10Audit($org))->toContain('subscription.cancel_scheduled')->toContain('subscription.cancel_revoked')->toContain('service.final_archive');
    foreach (MailOutbox::query()->where('organization_id', $org->id)->get() as $mail) {
        e10AssertNoVendor([$mail->subject ?? '', $mail->vars ?? []]);
    }
    e10AssertNoVendor($this->withHeaders(e2eHeaders('subs'))->getJson('/v1/subscriptions')->assertOk()->json());
    $archives = $this->withHeaders(e2eHeaders('archives'))->getJson('/v1/services/archives')->assertOk()->json('data.archives'); // a removed service is gone from the list; what is left of it is its archive
    expect($archives)->toHaveCount(1)->and($archives[0]['id'])->toBe($archive->id);
    e10AssertNoVendor($archives);
    $this->withHeaders(e2eHeaders('svc'))->getJson("/v1/services/{$service->id}")->assertNotFound();
});

it('cancels at once behind a destructive preview and a fresh step-up, refuses a confirmation that went stale, and brings the service back inside the window', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    $dns = [];
    e10Fakes($panel, $gate, $dns);
    e10Historical($panel, 'rychly-konec.cz');
    [$user, $org, $password, $service, $zone] = e10Provision($this, $panel, $gate, 'rychly-konec.cz', 'rychly.e10@example.cz');
    e10Action($this, $service, 'database.create', ['name' => 'shop', 'password' => e10Secret('')], 'db');
    $siteId = (int) e10Site($panel, 'rychly-konec.cz')['domain_id'];

    // 1. the preview names what goes and what cannot be taken back, changes nothing and asks for no step-up
    $preview = $this->withHeaders(e2eHeaders('preview'))->getJson("/v1/services/{$service->id}/actions/terminate/preview")->assertOk()->json('data');
    expect($preview['action'])->toBe('terminate')->and($preview['service']['id'])->toBe($service->id)->and($preview['what'])->not->toBeEmpty()->and(strlen($preview['fingerprint']))->toBe(64)
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($panel['deleted'])->toBe([]);
    e10AssertNoVendor($preview);

    // 2. no fresh step-up: refused before anything runs
    $this->withHeaders(e2eHeaders('terminate-nostep'))->postJson("/v1/services/{$service->id}/terminate", ['confirm' => $preview['fingerprint'], 'reason' => 'zrušení'])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(Operation::query()->where('service_id', $service->id)->where('desired->action', 'terminate')->count())->toBe(0);

    // 3. with the step-up, but a confirmation that no longer describes the target (the database was swapped for another since the preview): refused
    e2eStepUp($this, $password);
    $old = collect($panel['databases'])->first(fn (array $row) => (int) ($row['parent_domain_id'] ?? 0) === $siteId);
    e10Action($this, $service, 'database.delete', ['remote_id' => (string) $old['database_id']], 'db-del');
    e10Action($this, $service, 'database.create', ['name' => 'blog', 'password' => e10Secret('')], 'db2');
    $this->withHeaders(e2eHeaders('terminate-stale'))->postJson("/v1/services/{$service->id}/terminate", ['confirm' => $preview['fingerprint'], 'reason' => 'zrušení'])->assertStatus(409)->assertJsonPath('error', 'target_changed');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // 4. a new preview, the confirmation it carries: the service is archived and deactivated at once, the data stays for the window
    $again = $this->withHeaders(e2eHeaders('preview-2'))->getJson("/v1/services/{$service->id}/actions/terminate/preview")->assertOk()->json('data');
    expect($again['fingerprint'])->not->toBe($preview['fingerprint']);
    $this->withHeaders(e2eHeaders('terminate'))->postJson("/v1/services/{$service->id}/terminate", ['confirm' => $again['fingerprint'], 'reason' => 'zrušení'])->assertStatus(202);
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service->refresh();
    $archive = Backup::query()->where('service_id', $service->id)->where('kind', 'final')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::SUSPENDED)->and($service->terminate_at?->isFuture())->toBeTrue()->and($archive->state)->toBe('completed')
        ->and(collect(Storage::disk('local')->files((string) data_get($archive->meta, 'set')))->filter(fn (string $file) => str_contains($file, 'database-'))->count())->toBe(1) // the database that is there now is in the archive
        ->and(e10Site($panel, 'rychly-konec.cz'))->not->toBeNull()->and($panel['databases'])->toHaveCount(2); // nothing of the data is deleted yet
    expect(Subscription::query()->where('service_id', $service->id)->value('state'))->toBe(Subscription::CANCELLED);

    // 5. the customer changes their mind inside the window: a resume brings the service back and, with the rule `services.reinstate`
    //    on (an operator switches it on; it is off until then), its bill with it
    app(AutomationLedger::class)->setEnabled('services.reinstate', true);
    $this->withHeaders(e2eHeaders('resume'))->postJson("/v1/services/{$service->id}/resume", ['reason' => 'rozmysleli jsme se'])->assertStatus(202);
    driveOperations();
    app(OutboxPublisher::class)->relayPending(); // the call-off is said after the resume ran, and the billing listens to it
    $service->refresh();
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->terminate_at)->toBeNull()->and(data_get($service->tags, 'deletion'))->toBeNull()
        ->and($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->cancel_at_period_end)->toBeFalse()
        ->and(e10Site($panel, 'rychly-konec.cz'))->not->toBeNull()->and($panel['databases'])->toHaveCount(2)
        // the customer's own database swap is the only delete the panel saw: no site, no cron, no alias
        ->and(collect($panel['deleted'])->map(fn (string $entry) => explode(':', $entry)[0])->unique()->sort()->values()->all())->toBe(['sites_database_delete']);
    $this->withHeaders(e2eHeaders('service-back'))->getJson("/v1/services/{$service->id}")->assertOk()->assertJsonPath('data.state', ServiceStateMachine::ACTIVE);

    // 6. the removal pass has nothing to do for a service that is back, whatever the clock says
    $this->travelTo(now()->addDays(60));
    Artisan::call('onhost:services:purge');
    driveOperations();
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(e10Site($panel, 'rychly-konec.cz'))->not->toBeNull()->and(array_key_exists(50, $panel['sites']))->toBeTrue()
        ->and($panel['sites'])->toHaveCount(2)->and($siteId)->toBeGreaterThan(0);

    // 7. what was said: the cancellation, the deactivation and the call-off, once each, to this organization
    $events = e10Events($org);
    foreach (['service.deletion.scheduled', 'service.deactivated', 'service.deletion.cancelled'] as $name) {
        expect($events)->toHaveKey($name);
    }
    expect($events['service.deletion.cancelled'])->toBe(1)->and(OutboxMessage::query()->where('organization_id', $org->id)->whereNull('published_at')->count())->toBe(0);
    expect(e10Audit($org))->toContain('service.final_archive');
});

it('lets a consumer withdraw within 14 days: the unused part goes back to the credit, the service ends, and it cannot be resumed for free', function () {
    LaravelNotification::fake();
    app(AutomationLedger::class)->setEnabled('billing.withdrawal', true);
    $panel = [];
    $gate = [];
    $dns = [];
    e10Fakes($panel, $gate, $dns);
    [$user, $org, $password, $service] = e10Provision($this, $panel, $gate, 'spotrebitel-e10.cz', 'spotrebitel.e10@example.cz', consumer: true);
    expect($org->customer_class)->toBe('b2c');
    $this->travelTo(now()->addDays(4)); // today is the fifth day of fourteen

    // 1. the panel says it can, until when, and what would come back
    $info = $this->withHeaders(e2eHeaders('wd-info'))->getJson("/v1/services/{$service->id}/withdrawal")->assertOk()->json('data');
    expect($info)->toMatchArray(['enabled' => true, 'eligible' => true, 'customer_class' => 'b2c'])->and($info['estimate']['refund']['minor'])->toBeGreaterThan(0)->and($info['deadline'])->not->toBeNull();
    e10AssertNoVendor($info);

    // 2. the agreement to a refund to the credit is voluntary (L-06) and must be a yes or a no; ending a contract needs a fresh step-up
    $this->withHeaders(e2eHeaders('wd-none'))->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => 'perhaps'])->assertStatus(422);
    $this->withHeaders(e2eHeaders('wd-nostep-0'))->postJson("/v1/services/{$service->id}/withdrawal", [])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    $this->withHeaders(e2eHeaders('wd-nostep'))->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    e2eStepUp($this, $password);
    $accepted = $this->withHeaders(e2eHeaders('wd'))->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true, 'statement' => 'Služba nám nevyhovuje.'])->assertStatus(202)->json();
    for ($i = 0; $i < 4; $i++) { // the service is switched off, the credit note written, the cancellation queued and carried out
        driveOperations();
        app(OutboxPublisher::class)->relayPending();
    }

    // 3. switched off first, then the credit note, then the cancellation: the unused part is on the credit
    $service->refresh();
    $note = Invoice::query()->where('organization_id', $org->id)->where('type', 'credit_note')->sole();
    $refund = (int) $info['estimate']['refund']['minor'];
    $wallets = app(WalletService::class);
    expect($service->state)->toBe(ServiceStateMachine::SUSPENDED)->and($service->terminate_at?->isFuture())->toBeTrue()->and(SuspensionHold::holds($service))->toContain(SuspensionHold::WITHDRAWAL)
        ->and(-$note->total_minor)->toBe($refund)->and($wallets->balances($org, 'CZK')['posted']->minor)->toBe($refund)
        ->and(Backup::query()->where('service_id', $service->id)->where('kind', 'final')->where('state', 'completed')->exists())->toBeTrue();
    $this->withHeaders(e2eHeaders('wallet'))->getJson('/v1/wallet')->assertOk();

    // 4. the same contract is withdrawn from once, and there is no free way back
    $this->withHeaders(e2eHeaders('wd-again'))->postJson("/v1/services/{$service->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(409);
    $this->withHeaders(e2eHeaders('wd-resume'))->postJson("/v1/services/{$service->id}/resume", [])->assertStatus(409)->assertJsonPath('error', 'service_suspension_held')->assertJsonPath('hold', 'withdrawal');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED);

    // 5. the window runs out and the purge removes it; the account stays with its credit and its documents
    $this->travelTo($service->terminate_at->copy()->addMinutes(10));
    Artisan::call('onhost:services:purge');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect($service->fresh()->state)->toBe(ServiceStateMachine::TERMINATED)->and($panel['sites'])->toHaveCount(0)
        ->and($wallets->balances($org, 'CZK')['posted']->minor)->toBe($refund);
    $this->withHeaders(e2eHeaders('invoices'))->getJson('/v1/invoices')->assertOk();

    // 6. what was said: the confirmation of receipt is a mandatory legal notice, said once, and nothing in it names a vendor
    $events = e10Events($org);
    expect($events['withdrawal.accepted'] ?? 0)->toBe(1)->and(OutboxMessage::query()->where('organization_id', $org->id)->whereNull('published_at')->count())->toBe(0);
    expect(MailOutbox::query()->where('template_key', 'withdrawal-accepted')->count())->toBe(1)->and(e10Audit($org))->toContain('billing.withdrawal.accept')->toContain('billing.withdrawal.refund');
    foreach (MailOutbox::query()->where('organization_id', $org->id)->get() as $mail) {
        e10AssertNoVendor([$mail->subject ?? '', $mail->vars ?? []]);
    }
    expect($accepted['id'])->not->toBeNull();
});

it('refuses a company the 14-day withdrawal, and a consumer the day after the deadline', function () {
    LaravelNotification::fake();
    app(AutomationLedger::class)->setEnabled('billing.withdrawal', true);
    $panel = [];
    $gate = [];
    $dns = [];
    e10Fakes($panel, $gate, $dns);

    // a company ordered for business: no right of withdrawal, said in the panel and refused by the route
    [, , $companyPassword, $firm] = e10Provision($this, $panel, $gate, 'firma-e10.cz', 'firma.e10@example.cz');
    $info = $this->withHeaders(e2eHeaders('co-info'))->getJson("/v1/services/{$firm->id}/withdrawal")->assertOk()->json('data');
    expect($info)->toMatchArray(['eligible' => false, 'reason' => 'withdrawal_consumers_only', 'customer_class' => 'b2b']);
    e2eStepUp($this, $companyPassword);
    $this->withHeaders(e2eHeaders('co-wd'))->postJson("/v1/services/{$firm->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(403)->assertJsonPath('error', 'withdrawal_consumers_only');
    expect($firm->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(0);

    // a consumer a day too late: the deadline is fourteen days in the seller's calendar
    e10Forget($this);
    $this->travelTo(now()->addMinutes(2));
    [, $person, $personPassword, $shop] = e10Provision($this, $panel, $gate, 'pozde-e10.cz', 'pozde.e10@example.cz', consumer: true);
    $this->travelTo(now()->addDays(15));
    $late = $this->withHeaders(e2eHeaders('late-info'))->getJson("/v1/services/{$shop->id}/withdrawal")->assertOk()->json('data');
    expect($late)->toMatchArray(['eligible' => false, 'reason' => 'withdrawal_period_over']);
    e2eStepUp($this, $personPassword);
    $this->withHeaders(e2eHeaders('late-wd'))->postJson("/v1/services/{$shop->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(409)->assertJsonPath('error', 'withdrawal_period_over');
    expect($shop->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(Invoice::query()->where('organization_id', $person->id)->where('type', 'credit_note')->count())->toBe(0);
});

/** A call of the person signed in now: browser headers, a fresh idempotency key, the organization worked in. */
function e10Call(object $test, string $method, string $uri, Organization $org, string $label, array $body = []): TestResponse
{
    return $test->withHeaders(e2eHeaders($label) + ['X-Organization' => $org->id])->json($method, $uri, $body);
}

/** The accept token out of the latest invitation mail to `$email` (the link is the only place it travels). */
function e10InvitationToken(string $email): string
{
    $mail = MailOutbox::query()->where('template_key', 'invitation')->where('to', $email)->orderByDesc('created_at')->firstOrFail();
    preg_match('/pozvanka=([^&"]+)/', (string) json_encode($mail->vars, JSON_UNESCAPED_SLASHES), $found);
    expect($found)->not->toBeEmpty();

    return rawurldecode($found[1]);
}

it('ends an account only for its owner, behind a step-up and 14 days, can be stopped inside the window, and is carried out by the scheduled command', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    $dns = [];
    e10Fakes($panel, $gate, $dns);
    [$owner, $org, $ownerPassword, $service] = e10Provision($this, $panel, $gate, 'konec-uctu-e10.cz', 'majitel.e10@example.cz');

    // the owner's colleague: an account of their own, invited to this organization as its administrator
    $this->travelTo(now()->addMinutes(2));
    e10Forget($this);
    [$admin, $adminOrg, $adminPassword] = e2eSignUp($this, 'admin.e10@example.cz', 'Kolega e10 s.r.o.');
    e10ActAs($this, $owner);
    e2eStepUp($this, $ownerPassword);
    e10Call($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'invite', ['email' => 'admin.e10@example.cz', 'role' => 'org_admin'])->assertCreated();
    e10ActAs($this, $admin);
    e10Call($this, 'POST', '/v1/organizations/invitations/accept', $org, 'accept', ['token' => e10InvitationToken('admin.e10@example.cz')])->assertOk();

    // 1. a running service blocks an erasure: it is asked for only when nothing is left to run
    e10ActAs($this, $owner);
    e2eStepUp($this, $ownerPassword);
    e10Call($this, 'POST', '/v1/data-requests', $org, 'erase-early', ['kind' => 'deletion'])->assertStatus(409)->assertJsonPath('error', 'deletion_blocked');
    expect(DataRequest::query()->where('organization_id', $org->id)->count())->toBe(0);

    // 2. the service is cancelled at once and removed when its window runs out; the account is left with no service
    e10Call($this, 'POST', "/v1/services/{$service->id}/terminate", $org, 'terminate', ['reason' => 'konec'])->assertStatus(202);
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service->refresh();
    e10Call($this, 'POST', '/v1/data-requests', $org, 'erase-pending', ['kind' => 'deletion'])->assertStatus(409)->assertJsonPath('error', 'deletion_blocked'); // a pending removal is a wait, not a go
    $this->travelTo($service->terminate_at->copy()->addMinutes(10));
    Artisan::call('onhost:services:purge');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $archive = Backup::query()->where('service_id', $service->id)->where('kind', 'final')->firstOrFail();
    $set = (string) data_get($archive->meta, 'set');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::TERMINATED)->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue();
    e10Call($this, 'GET', '/v1/me', $org, 'me')->assertOk(); // the account is there, and so are its documents
    e10Call($this, 'GET', '/v1/invoices', $org, 'invoices')->assertOk()->assertJsonStructure(['data' => [['id', 'type', 'state']]]);

    // 3. only the owner may ask: the administrator is refused for the permission they lack, and nothing is scheduled
    e10ActAs($this, $admin);
    e2eStepUp($this, $adminPassword);
    e10Call($this, 'POST', '/v1/data-requests', $org, 'erase-admin', ['kind' => 'deletion'])->assertStatus(403);
    expect(DataRequest::query()->where('organization_id', $org->id)->count())->toBe(0);

    // 4. the owner needs a fresh step-up; with it the erasure is scheduled for fourteen days from now, once
    e10ActAs($this, $owner);
    e10Call($this, 'POST', '/v1/data-requests', $org, 'erase-nostep', ['kind' => 'deletion'])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    e2eStepUp($this, $ownerPassword);
    $asked = now();
    $request = e10Call($this, 'POST', '/v1/data-requests', $org, 'erase', ['kind' => 'deletion', 'reason' => 'ukončujeme podnikání'])->assertStatus(202)->json('data');
    $record = DataRequest::query()->findOrFail($request['id']);
    expect($record->state)->toBe('requested')->and(CarbonImmutable::parse((string) data_get($record->meta, 'execute_after'))->equalTo($asked->copy()->addDays(14)))->toBeTrue();
    e10Call($this, 'POST', '/v1/data-requests', $org, 'erase-twice', ['kind' => 'deletion'])->assertStatus(409)->assertJsonPath('error', 'data_request_pending');

    // 5. inside the window nothing happens, and anybody who manages the organization may stop it
    $this->travelTo($asked->copy()->addDays(5));
    Artisan::call('onhost:compliance:data-requests');
    expect($record->fresh()->state)->toBe('requested')->and(User::query()->find($owner->id)->state)->toBe('active');
    e10ActAs($this, $admin);
    e10Call($this, 'POST', "/v1/data-requests/{$record->id}/cancel", $org, 'cancel-erasure')->assertOk();
    expect($record->fresh()->state)->toBe('cancelled');
    $this->travelTo($asked->copy()->addDays(15));
    Artisan::call('onhost:compliance:data-requests');
    expect($record->fresh()->state)->toBe('cancelled')->and(User::query()->find($owner->id)->state)->toBe('active')->and(Organization::query()->find($org->id)->state)->not->toBe('closed')
        ->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue();
    e10Call($this, 'POST', "/v1/data-requests/{$record->id}/cancel", $org, 'cancel-twice')->assertStatus(409)->assertJsonPath('error', 'data_request_not_cancellable');

    // 6. asked again (the owner, with a step-up), left alone for the 14 days: the scheduled command carries it out
    e10ActAs($this, $owner);
    e2eStepUp($this, $ownerPassword);
    $second = now();
    $again = e10Call($this, 'POST', '/v1/data-requests', $org, 'erase-again', ['kind' => 'deletion'])->assertStatus(202)->json('data');
    $this->travelTo($second->copy()->addDays(14)->subMinutes(5));
    Artisan::call('onhost:compliance:data-requests');
    expect(DataRequest::query()->findOrFail($again['id'])->state)->toBe('requested'); // five minutes early: not yet
    $this->travelTo($second->copy()->addDays(14)->addMinutes(5));
    Artisan::call('onhost:compliance:data-requests');
    app(OutboxPublisher::class)->relayPending();
    $done = DataRequest::query()->findOrFail($again['id']);
    $anonymised = User::query()->find($owner->id);
    expect($done->state)->toBe('completed')->and($done->meta['archives_erased'])->toBe(1)->and(Organization::query()->find($org->id)->state)->toBe('closed')
        ->and($anonymised->state)->toBe('deleted')->and($anonymised->email)->not->toBe('majitel.e10@example.cz')
        ->and(User::query()->find($admin->id)->state)->toBe('active')->and(User::query()->find($admin->id)->email)->toBe('admin.e10@example.cz') // a person who belongs to another organization too is not erased
        ->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeFalse()->and(Backup::query()->find($archive->id)->state)->toBe('purged')
        ->and(Invoice::query()->where('organization_id', $org->id)->count())->toBeGreaterThan(0); // tax documents are kept

    // 7. the owner's address no longer signs in; what is kept is the documents, the ledger and the audit trail
    e10Forget($this);
    $this->withHeaders(e2eHeaders('login-after'))->postJson('/v1/auth/login', ['email' => 'majitel.e10@example.cz', 'password' => $ownerPassword])->assertStatus(422);
    $events = e10Events($org);
    expect($events)->toHaveKey('compliance.data_request.deletion_scheduled')->and($events['compliance.data_request.deletion_scheduled'])->toBe(2)
        ->and(e10Audit($org))->toContain('compliance.data_request.deletion')->toContain('compliance.data_request.deletion_cancelled')->toContain('compliance.data_request.deleted');
});
