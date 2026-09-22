<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

function ispEnvelope(mixed $response, string $code = 'ok', string $message = ''): array
{
    return ['code' => $code, 'message' => $message, 'response' => $response];
}

function ispFake(): void
{
    $site = ['domain_id' => 77, 'domain' => 'example.cz', 'sys_groupid' => 12, 'system_user' => 'web77', 'document_root' => '/var/www/clients/client12/web77', 'active' => 'y', 'fastcgi_php_version' => 'PHP 8.3:/usr/bin/php-fpm8.3:/etc/php/8.3/fpm', 'ssl' => 'y', 'ssl_letsencrypt' => 'y', 'rewrite_to_https' => 'y', 'hd_quota' => 51200, 'pm_max_children' => 4];
    $base = 'shared01.mgmt.test:8080/remote/json.php';
    Http::fake([
        "{$base}?login" => Http::response(ispEnvelope('sess-123')),
        "{$base}?client_get_by_username" => Http::response(ispEnvelope(false)),
        "{$base}?client_add" => Http::response(ispEnvelope(12)),
        "{$base}?sites_web_domain_get" => fn (Request $r) => is_array($r['primary_id']) ? Http::response(ispEnvelope([])) : Http::response(ispEnvelope($site)),
        "{$base}?sites_web_domain_add" => Http::response(ispEnvelope(77)),
        "{$base}?sites_web_domain_update" => Http::response(ispEnvelope(true)),
        "{$base}?monitor_jobqueue_count" => Http::response(ispEnvelope(0)),
    ]);
}

function fulfilmentConsents(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jan Novák'], 'registry_terms_cz' => ['person' => 'Jan Novák'], 'sla' => []];
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $isp = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true, 'mail.create' => true], 'options' => ['server_id' => 1, 'verify_tls' => false], 'adapter_version' => '1.0.0']);
    Node::query()->firstOrCreate(['provider_instance_id' => $isp->id, 'name' => 'shared01'], ['region_code' => 'cz1', 'role' => 'web', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => ['cpu_pct' => 20, 'ram_used_mb' => 20000, 'disk_used_gb' => 200], 'tags' => ['public_ipv4' => '192.0.2.10']]);
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], ['provider' => 'powerdns', 'name' => 'PowerDNS', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active', 'capabilities' => ['dns' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']], 'adapter_version' => '1.0.0']);
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

it('fulfils a paid order: web hosting on ISPConfig first, then the domain at the registrar, order becomes ACTIVE', function () {
    $state = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('example.cz');
    ispFake();
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'example.cz']], ['product_key' => 'domain', 'config' => ['fqdn' => 'example.cz']]], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2b'], 1, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, fulfilmentConsents(), ['mode' => 'wallet'], 'ful-1', $ctx)['order'];
    expect($order->fresh()->state)->toBe(OrderStateMachine::PAID);

    $delivered = app(OutboxPublisher::class)->relayPending();
    expect($delivered)->toBeGreaterThan(0);
    driveOperations();

    $order->refresh();
    expect($order->state)->toBe(OrderStateMachine::ACTIVE)->and(OrderItem::query()->where('order_id', $order->id)->pluck('state')->unique()->all())->toBe(['active']);
    $service = Service::query()->where('product_key', 'web-hosting')->where('organization_id', $org->id)->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->hostname)->toBe('example.cz')->and($service->order_item_id)->not->toBeNull();
    $website = Website::query()->where('service_id', $service->id)->firstOrFail();
    expect($website->domain)->toBe('example.cz')->and($website->remote_site_id)->toBe(77)->and($website->remote_client_id)->toBe(12)->and($website->https_forced)->toBeTrue()->and($website->ssl_state)->toBe('issued');
    $domain = Domain::query()->where('fqdn_ascii', 'example.cz')->firstOrFail();
    expect($domain->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->order_item_id)->not->toBeNull()->and($domain->dns_zone_id)->not->toBeNull();
    expect(OutboxMessage::query()->where('name', 'order.paid')->whereNotNull('published_at')->exists())->toBeTrue();
    expect(OutboxMessage::query()->whereIn('name', ['service.activated', 'domain.registered', 'order.active'])->count())->toBe(3);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '?sites_web_domain_add') && $r['params']['domain'] === 'example.cz' && $r['params']['ssl_letsencrypt'] === 'y');
    expect(array_count_values(wapiCommands())['domain-create'])->toBe(1);

    // redelivery of the same event is harmless
    OutboxMessage::query()->where('name', 'order.paid')->update(['published_at' => null]);
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect(Service::query()->where('organization_id', $org->id)->count())->toBe(1)->and(array_count_values(wapiCommands())['domain-create'])->toBe(1);
});

it('marks the order PARTIALLY_ACTIVE when one item cannot be provisioned and keeps the rest', function () {
    $state = ['registered' => false, 'nsset' => true, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('example.cz');
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    // mail hosting without a domain in its configuration cannot be provisioned (mail_domain_required)
    $quote = app(QuoteService::class)->quote([['product_key' => 'mail', 'plan_key' => 'mail-business'], ['product_key' => 'domain', 'config' => ['fqdn' => 'example.cz']]], 'CZK', ['country' => 'CZ'], 1, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, fulfilmentConsents(), ['mode' => 'wallet'], 'ful-2', $ctx)['order'];
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $order->refresh();
    expect($order->state)->toBe(OrderStateMachine::PARTIALLY_ACTIVE);
    expect(Domain::query()->where('fqdn_ascii', 'example.cz')->value('state'))->toBe(DomainStateMachine::ACTIVE);
    expect(OutboxMessage::query()->where('name', 'order.fulfilment_failed')->exists())->toBeTrue();

    // The money (blueprint §5.2). The order reserved its total; the reservation used to run out after a day and hand everything
    // back while the domain stayed registered. Now the delivered line is charged, the other one goes back with a credit note.
    $items = OrderItem::query()->where('order_id', $order->id)->orderBy('product_key')->get()->keyBy('product_key'); // PostgreSQL returns updated rows in any order
    expect($items->map->state->all())->toBe(['domain' => 'active', 'mail' => 'refunded']);
    $domainLine = $items['domain'];
    $mailLine = $items['mail'];
    $ledger = app(LedgerService::class);
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($balances['reserved']->minor)->toBe(0)
        ->and($balances['posted']->minor)->toBe(500000 - $domainLine->total_minor)                      // only what was delivered left the credit
        ->and($ledger->balance(LedgerService::revenueAccount('domain', 'CZK'), 'CZK')->minor)->toBe($domainLine->total_minor - $domainLine->tax_minor)
        ->and($ledger->balance(LedgerService::revenueAccount('mail', 'CZK'), 'CZK')->minor)->toBe(0)
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe($domainLine->tax_minor)
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
    $settlement = $order->meta['settlement'];
    expect($settlement['captured_minor'])->toBe($domainLine->total_minor)->and($settlement['returned_minor'])->toBe($mailLine->total_minor);
    $credit = Invoice::query()->findOrFail($settlement['credit_note_id']);
    expect($credit->type)->toBe('credit_note')->and($credit->total_minor)->toBe(-$mailLine->total_minor)->and($credit->lines()->count())->toBe(1);
    // the customer is told — by us, with the amount — instead of finding out from a missing service
    app(OutboxPublisher::class)->relayPending();
    $told = Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Část objednávky%')->first();
    expect($told)->not->toBeNull()->and((string) $told->body)->toContain('vrátili na váš kredit');
    expect(MailOutbox::query()->where('template_key', 'order-refunded')->where('to', $owner->email)->exists())->toBeTrue();

    // settling twice changes nothing; a line that was given back is not delivered by retrying its operation
    expect(app(OrderSettlement::class)->settle($order->id, $ctx))->toBe($settlement)->and(app(OrderSettlement::class)->sweep())->toBe(0);
    expect(Invoice::query()->where('order_id', $order->id)->where('type', 'credit_note')->count())->toBe(1);
});

it('gives the whole payment back when nothing of the order could be delivered', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed-none', $ctx, bankProvider: 'comgate');
    $quote = app(QuoteService::class)->quote([['product_key' => 'mail', 'plan_key' => 'mail-business']], 'CZK', ['country' => 'CZ'], 1, null, $org); // no domain in the configuration: cannot be provisioned
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, fulfilmentConsents(), ['mode' => 'wallet'], 'ful-none', $ctx)['order'];
    expect(app(WalletService::class)->balances($org, 'CZK')['reserved']->minor)->toBe($order->total_minor);
    expect(WalletHold::query()->findOrFail($order->wallet_hold_id)->expires_at)->toBeNull(); // the reservation lasts as long as the order does
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $order->refresh();
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($order->state)->toBe(OrderStateMachine::FAILED)->and($balances['reserved']->minor)->toBe(0)->and($balances['posted']->minor)->toBe(500000)->and($balances['available']->minor)->toBe(500000);
    expect($order->meta['settlement']['captured_minor'])->toBe(0)->and($order->meta['settlement']['returned_minor'])->toBe($order->total_minor);
    expect(Invoice::query()->where('order_id', $order->id)->where('type', 'statement')->value('state'))->toBe(Invoice::CREDITED);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Objednávku % se nepodařilo zřídit')->exists())->toBeTrue();
});

it('charges a delivered order: the reservation becomes revenue and cannot be spent again', function () {
    $state = ['registered' => false, 'nsset' => true, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('zaplaceno.cz');
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'card', 'seed-paid', $ctx, bankProvider: 'comgate');
    $quote = app(QuoteService::class)->quote([['product_key' => 'domain', 'config' => ['fqdn' => 'zaplaceno.cz']]], 'CZK', ['country' => 'CZ'], 1, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, fulfilmentConsents(), ['mode' => 'wallet'], 'ful-paid', $ctx)['order'];
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $order->refresh();
    expect($order->state)->toBe(OrderStateMachine::ACTIVE)->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('captured');
    // two days later the hold sweep finds nothing to hand back, and the credit is what it should be
    $this->travel(2)->days();
    expect(app(WalletService::class)->expireHolds())->toBe(0);
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($balances['posted']->minor)->toBe(100000 - $order->total_minor)->and($balances['available']->minor)->toBe(100000 - $order->total_minor)->and($balances['reserved']->minor)->toBe(0);
    expect(app(LedgerService::class)->balance(LedgerService::revenueAccount('domain', 'CZK'), 'CZK')->minor)->toBe($order->total_minor - $order->tax_minor);
});
