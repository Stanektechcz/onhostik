<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E3 — e-mail hosting from the order to the last mailbox, touching only the real HTTP routes.
 *
 * sign up and verify → order the Mail Business plan for a domain → Comgate callback → the saga makes the mail domain on a STATEFUL
 * fake ISPConfig and publishes MX/SPF/DKIM → the customer, through POST /v1/services/{id}/actions: makes mailboxes (the plan's number is
 * the ceiling), an alias and a forward, changes a password, switches sending off and on, deletes a mailbox. An address outside the
 * domain the service hosts is refused, and so is a mailbox the panel holds but the platform never made.
 *
 * Only the edges are doubles: gateway, hosting panel, DNS node (Http::fake) and the mail (Notification::fake). Asserts do not depend on
 * row order, so the flow holds on SQLite and PostgreSQL alike. Helpers: tests/Support/E2E/E2EHelpers.php.
 */

const MAILFLOW_PASSWORD = 'Correct-Horse-Battery-9-Staple';

beforeEach(function () {
    e2eSeedPlatform();
    e2eMailInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

/** One Http fake stack for the whole flow: the mail half and the rest of the panel, the gateway and the DNS node. */
function mailFlowDoubles(array &$panel, array &$gate, array &$dns): void
{
    e2eIspMail($panel); // first: it answers the mail functions and passes the rest on
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    e2ePowerDnsFake($dns);
}

/** Sign up and verify the address (Notification::fake() must be active). @return array{0:User,1:Organization} */
function mailFlowSignUp(object $test): array
{
    $test->withHeaders(e2eHeaders('register'))->postJson('/v1/auth/register', [
        'name' => 'Petr Dvořák', 'email' => 'petr.mail@example.cz', 'password' => MAILFLOW_PASSWORD, 'organization' => 'Dvořák a syn s.r.o.',
        'type' => 'company', 'ico' => '87654321', 'country' => 'CZ', 'terms' => true,
    ])->assertCreated();
    $user = User::query()->where('email', 'petr.mail@example.cz')->firstOrFail();
    $org = Organization::query()->where('owner_user_id', $user->id)->firstOrFail();
    $test->withHeaders(e2eHeaders('verify'))->postJson('/v1/auth/verify-email', ['token' => e2eVerificationToken($user)])->assertOk();

    return [$user, $org];
}

/** Order the Mail Business plan for `$domain`, pay it through the gateway and let the saga run. @return array{0:Order,1:Service} */
function mailFlowOrderAndPay(object $test, Organization $org, array &$gate, string $domain): array
{
    $plans = $test->getJson('/v1/catalog/mail')->assertOk()->json('data.plans');
    expect(collect($plans)->pluck('key')->all())->toContain('mail-business');

    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'mail', 'plan_key' => 'mail-business', 'config' => ['domain' => $domain]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', [
        'quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'],
    ])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and(Service::query()->where('organization_id', $org->id)->count())->toBe(0); // nothing before the money

    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'mail')->firstOrFail();

    return [$order->refresh(), $service];
}

/** One service action over the real route, with a fresh idempotency key. */
function mailFlowAction(object $test, Service $service, string $action, array $params, string $label): TestResponse
{
    return $test->withHeaders(e2eHeaders($label))->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params]);
}

/** An accepted action, run to its end by the scheduler; the operation it left. */
function mailFlowRun(object $test, Service $service, string $action, array $params, string $label): Operation
{
    $accepted = mailFlowAction($test, $service, $action, $params, $label)->assertStatus(202);
    $operation = Operation::query()->findOrFail($accepted->json('operation_id') ?? $accepted->json('data.operation_id') ?? $accepted->json('data.id'));
    $operation = driveOperation($operation);
    expect($operation->state)->toBe(Operation::SUCCEEDED, "{$action}: ".$operation->step_label.' '.(string) data_get($operation->error, 'message', ''));

    return $operation;
}

/** The mailboxes the platform lists for the service, by address. @return array<string,array<string,mixed>> */
function mailFlowMailboxes(object $test, Service $service, string $label): array
{
    $rows = $test->withHeaders(e2eHeaders($label))->getJson("/v1/services/{$service->id}/resources/mailboxes")->assertOk()->json('data');

    return collect($rows)->keyBy('address')->all();
}

it('takes an order for a mail plan to a mail domain with its MX, SPF and DKIM published in our DNS', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    $dns = [];
    mailFlowDoubles($panel, $gate, $dns);
    pdnsLab();
    [$user, $org] = mailFlowSignUp($this);

    // the customer's domain is already delegated to ONhost DNS: its zone is made over the real DNS route
    $this->withHeaders(e2eHeaders('zone'))->postJson('/v1/dns/zones', ['name' => 'dvorak-mail.cz'])->assertCreated();
    $zone = DnsZone::query()->where('organization_id', $org->id)->where('name', 'dvorak-mail.cz')->firstOrFail();

    [$order, $service] = mailFlowOrderAndPay($this, $org, $gate, 'dvorak-mail.cz');

    expect($order->state)->toBe(OrderStateMachine::ACTIVE)->and($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->family)->toBe('mail');
    expect($panel['mail_domains'])->toHaveCount(1)->and(array_values($panel['mail_domains'])[0]['domain'])->toBe('dvorak-mail.cz')
        ->and(array_values($panel['mail_domains'])[0]['dkim'])->toBe('y')->and($panel['clients'])->toHaveCount(1);
    $mailDomain = MailDomain::query()->where('service_id', $service->id)->firstOrFail();
    expect($mailDomain->domain)->toBe('dvorak-mail.cz')->and($mailDomain->state)->toBe('active')->and($mailDomain->sending_enabled)->toBeTrue();

    // the records mail needs are in the customer's zone: MX, SPF, DMARC and the DKIM key the panel generated
    $records = $zone->refresh()->records()->get();
    expect($records->where('type', 'MX')->count())->toBeGreaterThan(0)
        ->and($records->where('type', 'TXT')->contains(fn ($r) => str_starts_with($r->content, 'v=spf1')))->toBeTrue()
        ->and($records->where('type', 'TXT')->contains(fn ($r) => str_starts_with($r->content, 'v=DMARC1')))->toBeTrue()
        ->and($records->where('type', 'TXT')->contains(fn ($r) => str_starts_with($r->content, 'v=DKIM1') && str_contains($r->content, 'p=')))->toBeTrue();
    expect(collect($dns['calls'])->contains(fn ($call) => str_starts_with($call, 'PATCH ')))->toBeTrue(); // and the DNS node was told
});

it('lets the customer run the mailboxes of a paid mail plan, inside the plan and inside the domain', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    $dns = [];
    mailFlowDoubles($panel, $gate, $dns);
    // what the panel already holds that the platform never made: a mailbox of somebody else's domain on the shared server
    $panel['mail_users'][4001] = ['mailuser_id' => 4001, 'email' => 'old@legacy-firm.cz', 'name' => 'Old', 'quota' => 1073741824, 'postfix' => 'y', 'disablesmtp' => 'n', 'password' => '$1$legacy', 'sys_groupid' => 5];
    [$user, $org] = mailFlowSignUp($this);
    [, $service] = mailFlowOrderAndPay($this, $org, $gate, 'dvorak-mail.cz'); // no zone of ours: the DNS records are shown to the customer
    $responses = [];

    // 1. what the customer sees: the plan, the records to publish with their own DNS, and the actions of a mail service
    $detail = $this->withHeaders(e2eHeaders('service'))->getJson("/v1/services/{$service->id}")->assertOk();
    $responses[] = $detail->getContent();
    $needed = collect($detail->json('data.access.dns_records_required'));
    expect($needed->where('type', 'MX')->count())->toBe(1)
        ->and($needed->contains(fn ($r) => $r['type'] === 'TXT' && str_starts_with($r['content'], 'v=spf1')))->toBeTrue()
        ->and($needed->contains(fn ($r) => $r['type'] === 'TXT' && str_ends_with($r['name'], '._domainkey') && str_contains($r['content'], 'p=')))->toBeTrue();
    $features = $this->withHeaders(e2eHeaders('features'))->getJson("/v1/services/{$service->id}/features")->assertOk();
    $responses[] = $features->getContent();
    expect($features->json('data.features.mailboxes'))->toMatchArray(['enabled' => true, 'limit' => 10])
        ->and($features->json('data.actions'))->toContain('mailbox.create', 'mailbox.update', 'mailbox.delete', 'alias.create', 'forward.create', 'sending.set');

    // 2. a mailbox in the domain the service hosts, and none in a domain it does not
    $password = 'Mailbox-Heslo-2026-a';
    mailFlowRun($this, $service, 'mailbox.create', ['address' => 'info@dvorak-mail.cz', 'password' => $password, 'name' => 'Info'], 'mbx-1');
    $mailboxes = mailFlowMailboxes($this, $service, 'list-1');
    expect(array_keys($mailboxes))->toBe(['info@dvorak-mail.cz'])->and($panel['mail_users'])->toHaveCount(2); // ours and the historical one
    $created = collect($panel['mail_users'])->firstWhere('email', 'info@dvorak-mail.cz');
    expect($created['server_id'])->toBe(1)->and($created['sys_groupid'])->toBe(array_values($panel['mail_domains'])[0]['sys_groupid']); // in the customer's own client, next to the domain
    $adds = count(array_keys($panel['calls'], 'mail_user_add', true));
    $foreign = mailFlowAction($this, $service, 'mailbox.create', ['address' => 'info@konkurence.cz', 'password' => $password], 'mbx-foreign')->assertStatus(422);
    expect($foreign->json('error'))->toBe('mail_domain_not_yours')->and($foreign->json('message'))->toContain('dvorak-mail.cz')
        ->and(count(array_keys($panel['calls'], 'mail_user_add', true)))->toBe($adds);
    $responses[] = $foreign->getContent();

    // 3. an alias and a forward inside the domain; one whose source is somebody else's is refused
    mailFlowRun($this, $service, 'alias.create', ['source' => 'sales@dvorak-mail.cz', 'destination' => 'petr@example.org'], 'alias-1');
    mailFlowRun($this, $service, 'forward.create', ['source' => 'office@dvorak-mail.cz', 'destination' => 'petr@example.org'], 'forward-1');
    $rows = collect($panel['mail_aliases']);
    expect($rows->where('type', 'alias')->pluck('source')->all())->toBe(['sales@dvorak-mail.cz'])->and($rows->where('type', 'forward')->pluck('source')->all())->toBe(['office@dvorak-mail.cz'])
        ->and($rows->pluck('destination')->unique()->values()->all())->toBe(['petr@example.org']);
    mailFlowAction($this, $service, 'alias.create', ['source' => 'ceo@konkurence.cz', 'destination' => 'petr@example.org'], 'alias-foreign')->assertStatus(422)->assertJsonPath('error', 'mail_domain_not_yours');
    expect($panel['mail_aliases'])->toHaveCount(2);

    // 4. a new password for the mailbox: the panel gets the new one, and neither password is in the call log or the operation rows
    $remoteId = $mailboxes['info@dvorak-mail.cz']['remote_id'];
    $changed = 'Nove-Heslo-2026-b';
    mailFlowRun($this, $service, 'mailbox.update', ['remote_id' => $remoteId, 'password' => $changed], 'mbx-pass');
    $update = collect($panel['mail_updates'])->last();
    expect($update['id'])->toBe((int) $remoteId)->and($update['params']['password'])->toBe($changed)->and($update['params']['email'])->toBe('info@dvorak-mail.cz');
    $logged = json_encode(DB::table('provider_calls')->pluck('request')->all()).json_encode(Operation::query()->where('service_id', $service->id)->get()->toArray());
    expect($logged)->not->toContain($password)->not->toContain($changed);

    // 4b. or without ever telling the owner a password: a one-time link the mailbox user opens, which carries the new password through the same action
    $link = $this->withHeaders(e2eHeaders('pass-link'))->postJson("/v1/services/{$service->id}/mailbox-password-link", ['remote_id' => $remoteId])->assertCreated()->json('data');
    expect($link['mailbox'])->toBe('info@dvorak-mail.cz')->and($link['url'])->toContain('/mailbox/password/');
    $path = parse_url($link['url'], PHP_URL_PATH).'?'.parse_url($link['url'], PHP_URL_QUERY);
    $this->get($path)->assertOk()->assertSee('info@dvorak-mail.cz');
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $viaLink = 'Heslo-Z-Odkazu-2026-d';
    $this->post($path, ['password' => $viaLink, 'password_confirmation' => $viaLink])->assertOk();
    driveOperations();
    expect(collect($panel['mail_updates'])->last()['params']['password'])->toBe($viaLink);
    $this->get($path)->assertOk()->assertSee('Odkaz už neplatí'); // a link works once
    expect(json_encode(DB::table('provider_calls')->pluck('request')->all()))->not->toContain($viaLink);

    // 5. sending off and on again: every mailbox of the domain follows, the historical one on the panel is not touched
    mailFlowRun($this, $service, 'sending.set', ['enabled' => false], 'send-off');
    expect(collect($panel['mail_users'])->firstWhere('email', 'info@dvorak-mail.cz')['disablesmtp'])->toBe('y')
        ->and($panel['mail_users'][4001]['disablesmtp'])->toBe('n');
    mailFlowRun($this, $service, 'sending.set', ['enabled' => true], 'send-on');
    expect(collect($panel['mail_users'])->firstWhere('email', 'info@dvorak-mail.cz')['disablesmtp'])->toBe('n');

    // 6. the plan's number is the ceiling: nine more fill it, the eleventh is refused with the plan's own number and the panel is not asked
    foreach (range(2, 10) as $n) {
        mailFlowRun($this, $service, 'mailbox.create', ['address' => "box{$n}@dvorak-mail.cz", 'password' => "Heslo-Schranky-{$n}-2026"], "mbx-{$n}");
    }
    expect(mailFlowMailboxes($this, $service, 'list-full'))->toHaveCount(10);
    $adds = count(array_keys($panel['calls'], 'mail_user_add', true));
    $over = mailFlowAction($this, $service, 'mailbox.create', ['address' => 'box11@dvorak-mail.cz', 'password' => MAILFLOW_PASSWORD.'-11'], 'mbx-11')->assertStatus(422);
    expect($over->json('error'))->toBe('feature_limit_reached')->and($over->json('limit'))->toBe(10)->and($over->json('used'))->toBe(10)
        ->and(count(array_keys($panel['calls'], 'mail_user_add', true)))->toBe($adds);
    $responses[] = $over->getContent();

    // 7. a mailbox the panel holds but the platform never made cannot be changed or deleted, whatever id is sent
    foreach ([['mailbox.update', ['remote_id' => '4001', 'password' => MAILFLOW_PASSWORD.'-cizi']], ['mailbox.delete', ['remote_id' => '4001']]] as $n => [$action, $params]) {
        $refused = driveOperation(Operation::query()->findOrFail(mailFlowAction($this, $service, $action, $params, "historical-{$n}")->assertStatus(202)->json('operation_id')));
        expect($refused->state)->toBe(Operation::FAILED, "{$action} on a historical mailbox must not succeed")->and((string) data_get($refused->error, 'message', ''))->toContain('nepatří');
    }
    expect($panel['mail_users'])->toHaveKey(4001)->and($panel['mail_users'][4001]['password'])->toBe('$1$legacy')
        ->and(collect($panel['mail_updates'])->pluck('id')->all())->not->toContain(4001);

    // 8. deleting a mailbox frees a place in the plan
    mailFlowRun($this, $service, 'mailbox.delete', ['remote_id' => $remoteId], 'mbx-del');
    expect(mailFlowMailboxes($this, $service, 'list-after'))->toHaveCount(9)->not->toHaveKey('info@dvorak-mail.cz')
        ->and(collect($panel['mail_users'])->pluck('email')->all())->not->toContain('info@dvorak-mail.cz');
    mailFlowRun($this, $service, 'mailbox.create', ['address' => 'box11@dvorak-mail.cz', 'password' => MAILFLOW_PASSWORD.'-11'], 'mbx-11-again');
    expect(mailFlowMailboxes($this, $service, 'list-refilled'))->toHaveCount(10);

    // 9. events: said once and to this organization; no vendor in anything the customer was shown
    app(OutboxPublisher::class)->relayPending();
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    foreach (['order.paid', 'service.activated', 'order.active'] as $name) {
        expect($events)->toHaveKey($name);
    }
    expect($events['service.activated'])->toBe(1)->and(OutboxMessage::query()->where('organization_id', $org->id)->whereNull('published_at')->count())->toBe(0);
    expect($events['operation.failed'])->toBe(2)->and($events['operation.succeeded'])->toBeGreaterThan(15); // the two refusals above; every other action ran to its end
    expect(Operation::query()->where('service_id', $service->id)->whereNotIn('state', [Operation::SUCCEEDED, Operation::FAILED])->count())->toBe(0);
    foreach ($responses as $body) {
        expect(mb_strtolower($body))->not->toContain('ispconfig');
    }
});

it('does not take over a mail domain the panel already runs for somebody else', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    $dns = [];
    mailFlowDoubles($panel, $gate, $dns);
    // historical mail: the domain and a mailbox in it exist on the server, in a client group that is not this customer's
    $panel['mail_domains'][800] = ['domain_id' => 800, 'domain' => 'legacy-firm.cz', 'active' => 'y', 'sys_groupid' => 5];
    $panel['mail_users'][4001] = ['mailuser_id' => 4001, 'email' => 'old@legacy-firm.cz', 'quota' => 1073741824, 'postfix' => 'y', 'disablesmtp' => 'n', 'password' => '$1$legacy', 'sys_groupid' => 5];
    $before = $panel;
    [, $org] = mailFlowSignUp($this);
    [, $service] = mailFlowOrderAndPay($this, $org, $gate, 'legacy-firm.cz');

    $operation = Operation::query()->where('service_id', $service->id)->where('kind', 'provision.mail')->firstOrFail();
    expect($operation->state)->toBe(Operation::FAILED)->and((string) data_get($operation->error, 'message', ''))->toContain('not created by ONhost')
        ->and($service->refresh()->state)->not->toBe(ServiceStateMachine::ACTIVE)
        ->and(MailDomain::query()->where('service_id', $service->id)->count())->toBe(0);
    // the panel's own mail is exactly as it was: nothing added, changed or removed
    expect($panel['mail_domains'])->toBe($before['mail_domains'])->and($panel['mail_users'])->toBe($before['mail_users'])->and($panel['mail_updates'])->toBe([])
        ->and($panel['calls'])->not->toContain('mail_domain_add')->not->toContain('mail_domain_delete')->not->toContain('mail_user_update')->not->toContain('mail_user_delete');
    // and the customer, who paid, is told it did not work, not that it is running
    $detail = $this->withHeaders(e2eHeaders('service'))->getJson("/v1/services/{$service->id}")->assertOk();
    expect($detail->json('data.state'))->not->toBe(ServiceStateMachine::ACTIVE)->and(mb_strtolower($detail->getContent()))->not->toContain('ispconfig');
});
