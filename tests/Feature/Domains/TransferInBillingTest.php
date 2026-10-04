<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Catalog\Models\DomainPrice;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainTransferSecret;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\CommerceHousekeeping;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0058 (HIGH, found by TASK-0056): a transfer of a domain to ONhost renews the name at the registrar from the platform's
 * credit, and the customer paid nothing for it. `POST /v1/domains/transfer-in` started the saga with no reservation at all, and a
 * cart line `action: transfer` was priced by the quote but delivered by `register()` — a registration of a name somebody else holds.
 *
 * Now a transfer is bought like any domain: the cart line is priced at the list renewal price for the years it brings (owner rule:
 * whole years at the list price, no discount nobody approved), the paid order reserves the money, and the line waits for the
 * transfer code. The customer hands the code over once, with the paid line (`order_item_id`); the code goes into the encrypted,
 * short-lived secret store and is wiped when the registrar has it. Delivered → the reservation becomes revenue for exactly that
 * line; refused or never handed over → the money goes back. Without a paid line a customer's transfer is refused (422).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    Http::preventStrayRequests();
});

const TIB_CODE = 'Kod-Prevodu-7731-xQ';
const TIB_ADDRESS = ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000'];

/** A registry that knows transfers: `transfer_code` (WAPI answer code of domain-transfer), `transferred` once it went through. */
function tibRegistry(array &$state): void
{
    $state += ['commands' => [], 'transferred' => false, 'transfer_code' => 1000, 'expiration' => '2027-09-06'];
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$state) {
        $payload = json_decode((string) $request['request'], true)['request'];
        $command = $payload['command'];
        $data = (array) ($payload['data'] ?? []);
        $state['commands'][] = $command;
        $overrides = match ($command) {
            'contact-create' => ['data' => ['contact' => ['cname' => $data['cname'] ?? 'ONH-T']]],
            'nsset-info' => ['data' => ['nsset' => ['nsset' => $data['nsset'] ?? 'NSSET-ONHOST']]],
            'nsset-create', 'ping' => [],
            'domain-transfer-check' => [],
            'domain-check' => ['code' => 3201, 'result' => 'Domain is registered'],
            'domain-transfer' => (function () use (&$state, $data) {
                $state['transfer_data'] = $data;
                if ($state['transfer_code'] !== 1000) {
                    return ['code' => $state['transfer_code'], 'result' => 'Invalid authorization information'];
                }
                $state['transferred'] = true;

                return [];
            })(),
            'domain-info' => $state['transferred']
                ? ['data' => ['domain' => ['name' => $data['name'], 'status' => 'active', 'expiration' => $state['expiration'], 'created' => '2020-01-01', 'dns' => [['name' => 'ns1.example.net'], ['name' => 'ns2.example.net']], 'owner_c' => 'ONH-T', 'nsset' => 'NSSET-ONHOST']]]
                : ['code' => 3222, 'result' => 'Domain not found'],
            default => ['code' => 2100, 'result' => "unexpected {$command}"],
        };

        return Http::response(['response' => array_merge(['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'] ?? null, 'svTRID' => 'sv-'.uniqid(), 'command' => $command, 'data' => []], $overrides)]);
    }]);
}

function tibRegistrant(): array
{
    return ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];
}

/** A paid (credit) or unpaid (bank) order with one transfer line for `$fqdn`. */
function tibOrder(Organization $org, User $by, string $fqdn, string $mode = 'wallet', array $extra = []): Order
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'domain', 'config' => ['fqdn' => $fqdn, 'period_years' => 1, 'action' => 'transfer', 'registrant' => tibRegistrant()] + $extra]], 'CZK', ['country' => 'CZ'], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jana Nováková'], 'registry_terms_cz' => ['person' => 'Jana Nováková'], 'sla' => []];
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $by, $consents, ['mode' => $mode], 'tib:'.$fqdn.':'.$mode, new CommandContext('user', $by->id, $org->id))['order'];
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    return $order->refresh();
}

function tibTransfer(object $test, User $user, Organization $org, array $body, string $key): TestResponse
{
    $test->actingAs($user, 'sanctum');

    return $test->postJson('/v1/domains/transfer-in', $body + ['auth_info' => TIB_CODE, 'consent' => ['person' => 'Jana Nováková']], ['X-Organization' => $org->id, 'Idempotency-Key' => $key]);
}

/** @return array{0:User,1:Organization} a customer with 2 000 Kč of credit and a fresh step-up */
function tibCustomer(array $account): array
{
    [$owner, $org] = $account;
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'card', 'tib-seed-'.$org->id, new CommandContext('user', $owner->id, $org->id), bankProvider: 'comgate');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    return [$owner, $org];
}

it('refuses a customer transfer-in no paid order stands behind, and asks the registrar nothing', function () {
    $state = [];
    tibRegistry($state);
    [$owner, $org] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));

    tibTransfer($this, $owner, $org, ['fqdn' => 'zdarma.cz', 'registrant' => tibRegistrant()], 'tib-direct-1')
        ->assertStatus(422)->assertJsonPath('error', 'transfer_needs_order');

    expect($state['commands'])->toBe([])
        ->and(Domain::query()->where('fqdn_ascii', 'zdarma.cz')->exists())->toBeFalse()
        ->and(DomainTransferSecret::query()->count())->toBe(0);
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($balances['available']->minor)->toBe(200000)->and($balances['reserved']->minor)->toBe(0);
});

it('transfers a paid transfer line instead of registering it, and charges exactly what was quoted', function () {
    $state = [];
    tibRegistry($state);
    [$owner, $org] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));

    $order = tibOrder($org, $owner, 'prevod.cz');
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
    $renew = DomainPrice::query()->where('tld', 'cz')->where('currency', 'CZK')->firstOrFail()->renew()->minor;
    // priced at the list renewal price for the one year the transfer brings — not the catalogue's 0 Kč for a .cz transfer
    expect($item->unit_net_minor)->toBe($renew)->and($item->sku)->toBe('domain-cz-transfer')->and($order->total_minor)->toBeGreaterThan(0);
    // paid and reserved; the line waits for the code and nothing was sent to the registrar — above all no domain-create
    expect($order->state)->toBe(OrderStateMachine::PROVISIONING)->and($item->state)->toBe('provisioning')
        ->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('active')
        ->and($state['commands'])->not->toContain('domain-create')->not->toContain('domain-transfer');

    tibTransfer($this, $owner, $org, ['fqdn' => 'prevod.cz', 'order_item_id' => $item->id], 'tib-paid-1')->assertStatus(202);
    driveOperations();

    expect($state['commands'])->toContain('domain-transfer')->not->toContain('domain-create')
        ->and($state['transfer_data']['auth_info'])->toBe(TIB_CODE)->and((int) $state['transfer_data']['period'])->toBe(1);
    $domain = Domain::query()->where('fqdn_ascii', 'prevod.cz')->firstOrFail();
    expect($domain->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->organization_id)->toBe($org->id)->and($domain->order_item_id)->toBe($item->id);
    expect($item->refresh()->state)->toBe('active')->and($item->domain_id)->toBe($domain->id)->and($item->operation_id)->not->toBeNull();
    $order->refresh();
    $hold = WalletHold::query()->findOrFail($order->wallet_hold_id);
    expect($order->state)->toBe(OrderStateMachine::ACTIVE)->and($hold->state)->toBe('captured')->and($hold->amount_minor)->toBe($order->total_minor);
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($balances['posted']->minor)->toBe(200000 - $order->total_minor)->and($balances['reserved']->minor)->toBe(0)
        ->and(app(LedgerService::class)->balance(LedgerService::revenueAccount('domain', 'CZK'), 'CZK')->minor)->toBe($order->total_minor - $order->tax_minor);
    // the code was used once and is gone
    $secret = DomainTransferSecret::query()->where('domain_id', $domain->id)->firstOrFail();
    expect($secret->used_at)->not->toBeNull()->and($secret->auth_info)->not->toBe(TIB_CODE);
});

it('gives the money back when the registrar refuses the transfer', function () {
    $state = ['transfer_code' => 2202];
    tibRegistry($state);
    [$owner, $org] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));
    $order = tibOrder($org, $owner, 'odmitnuto.cz');
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

    tibTransfer($this, $owner, $org, ['fqdn' => 'odmitnuto.cz', 'order_item_id' => $item->id], 'tib-refused-1')->assertStatus(202);
    driveOperations();

    expect($state['commands'])->toContain('domain-transfer')->not->toContain('domain-create');
    expect($item->refresh()->state)->toBe('refunded')->and($order->refresh()->state)->toBe(OrderStateMachine::FAILED) // failed, then given back by the settlement
        ->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('released');
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($balances['available']->minor)->toBe(200000)->and($balances['reserved']->minor)->toBe(0)
        ->and(app(LedgerService::class)->balance(LedgerService::revenueAccount('domain', 'CZK'), 'CZK')->minor)->toBe(0);
});

it('keeps the transfer code out of every log line, outbox message, audit row, order line and replay answer', function () {
    $state = [];
    tibRegistry($state);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.' '.json_encode($e->context);
    });
    [$owner, $org] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));
    // a client that puts the code into the cart already: the quote does not keep it
    $order = tibOrder($org, $owner, 'tajne.cz', 'wallet', ['auth_info' => TIB_CODE, 'authid' => TIB_CODE]);
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

    tibTransfer($this, $owner, $org, ['fqdn' => 'tajne.cz', 'order_item_id' => $item->id], 'tib-secret-1')->assertStatus(202);
    driveOperations();
    app(OutboxPublisher::class)->relayPending();

    expect($state['transfer_data']['auth_info'])->toBe(TIB_CODE); // it reached the registrar ...
    expect(implode("\n", $logged))->not->toContain(TIB_CODE); // ... and nothing else
    $found = [];
    foreach (Schema::getTables() as $table) {
        $name = (string) ($table['name'] ?? '');
        if ($name !== '' && str_contains((string) json_encode(DB::table($name)->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), TIB_CODE)) {
            $found[] = $name;
        }
    }
    expect($found)->toBe([]);
});

it('accepts the code only for a paid transfer line of the organization, and only once', function () {
    $state = [];
    tibRegistry($state);
    [$owner, $org] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));
    [$stranger, $other] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));

    $unpaid = tibOrder($org, $owner, 'nezaplaceno.cz', 'bank');
    $unpaidItem = OrderItem::query()->where('order_id', $unpaid->id)->firstOrFail();
    tibTransfer($this, $owner, $org, ['fqdn' => 'nezaplaceno.cz', 'order_item_id' => $unpaidItem->id], 'tib-unpaid-1')->assertStatus(409)->assertJsonPath('error', 'transfer_order_not_paid');

    $order = tibOrder($org, $owner, 'jednou.cz');
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
    // another organization's paid line is not found from here, and the line names its own domain
    tibTransfer($this, $stranger, $other, ['fqdn' => 'jednou.cz', 'order_item_id' => $item->id], 'tib-stranger-1')->assertStatus(404);
    tibTransfer($this, $owner, $org, ['fqdn' => 'jiny.cz', 'order_item_id' => $item->id], 'tib-mismatch-1')->assertStatus(422)->assertJsonPath('error', 'transfer_order_mismatch');
    expect($state['commands'])->not->toContain('domain-transfer');

    tibTransfer($this, $owner, $org, ['fqdn' => 'jednou.cz', 'order_item_id' => $item->id], 'tib-once-1')->assertStatus(202);
    driveOperations();
    tibTransfer($this, $owner, $org, ['fqdn' => 'jednou.cz', 'order_item_id' => $item->id], 'tib-once-2')->assertStatus(409)->assertJsonPath('error', 'transfer_already_submitted');

    expect(array_count_values($state['commands'])['domain-transfer'])->toBe(1);
});

it('ends a paid transfer whose code never came and gives the money back', function () {
    $state = [];
    tibRegistry($state);
    [$owner, $org] = tibCustomer($this->customerWithOrganization([], TIB_ADDRESS));
    $order = tibOrder($org, $owner, 'zapomenuto.cz');
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

    app(CommerceHousekeeping::class)->expireUnpaid();
    expect($item->refresh()->state)->toBe('provisioning'); // still within the waiting period

    $this->travel(31)->days();
    app(CommerceHousekeeping::class)->expireUnpaid();

    expect($item->refresh()->state)->toBe('refunded')->and($order->refresh()->state)->toBe(OrderStateMachine::FAILED) // failed, then given back by the settlement
        ->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('released')
        ->and($state['commands'])->not->toContain('domain-transfer')->not->toContain('domain-create');
    expect(app(WalletService::class)->balances($org, 'CZK')['available']->minor)->toBe(200000);
});
