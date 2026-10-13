<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * S1 (phase S, P19 gap M1): a mail plan sells a number of aliases (Mail Business 50, Mail Enterprise 500) and nothing counted them —
 * `alias.create` never ran the plan's limit. ISPConfig keeps aliases, forwards and the catch-all in ONE table (`mail_forwarding`,
 * column `type` alias|aliasdomain|forward|catchall) and `mail_alias_get` / `mail_forward_get` filter by nothing but the columns they
 * are given (`remoting_lib::getDataRecord`), so a count by `source LIKE %@domain` would also count the forwards and the catch-all.
 * The adapter keeps the rows of its own type, and `alias.create` counts the aliases against the plan before the panel is asked.
 * The fake here answers like ISPConfig does: every row of the table that matches, whatever its type.
 */

const S1_PASSWORD = 'Correct-Horse-Battery-9-Staple';

function s1HonestForwardingTable(array &$panel): void
{
    Http::fake(function (Request $request) use (&$panel) {
        if (! str_contains($request->url(), E2E_ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (! in_array($function, ['mail_alias_get', 'mail_forward_get'], true)) {
            return null;
        }
        $suffix = mb_strtolower(substr((string) data_get($request->data(), 'primary_id.source', ''), 1)); // '%@domain' → '@domain'
        $rows = array_values(array_filter($panel['mail_aliases'] ?? [], fn (array $row) => str_ends_with(mb_strtolower((string) $row['source']), $suffix)));

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $rows]);
    });
}

/** @return array{0: Service, 1: Organization} a paid Mail Business service on the stateful fake panel */
function s1MailService(object $test, array &$panel): array
{
    $gate = [];
    $dns = [];
    s1HonestForwardingTable($panel); // first: it answers the alias and forward listings, the rest passes on
    e2eIspMail($panel);
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    e2ePowerDnsFake($dns);

    $test->withHeaders(e2eHeaders('register'))->postJson('/v1/auth/register', [
        'name' => 'Eva Malá', 'email' => 'eva.alias@example.cz', 'password' => S1_PASSWORD, 'organization' => 'Malá s.r.o.',
        'type' => 'company', 'ico' => '87654322', 'country' => 'CZ', 'terms' => true,
    ])->assertCreated();
    $user = User::query()->where('email', 'eva.alias@example.cz')->firstOrFail();
    $org = Organization::query()->where('owner_user_id', $user->id)->firstOrFail();
    $test->withHeaders(e2eHeaders('verify'))->postJson('/v1/auth/verify-email', ['token' => e2eVerificationToken($user)])->assertOk();

    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'mail', 'plan_key' => 'mail-business', 'config' => ['domain' => 'mala-posta.cz']]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', [
        'quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'],
    ])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk();
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    return [Service::query()->where('organization_id', $org->id)->where('product_key', 'mail')->firstOrFail(), $org];
}

function s1Action(object $test, Service $service, string $action, array $params, string $label): TestResponse
{
    return $test->withHeaders(e2eHeaders($label))->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params]);
}

function s1Run(object $test, Service $service, string $action, array $params, string $label): void
{
    $accepted = s1Action($test, $service, $action, $params, $label)->assertStatus(202);
    $operation = driveOperation(Operation::query()->findOrFail($accepted->json('operation_id') ?? $accepted->json('data.operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED, "{$action}: ".(string) data_get($operation->error, 'message', ''));
}

beforeEach(function () {
    e2eSeedPlatform();
    e2eMailInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

it('counts only the aliases of a mail plan, not its forwards or catch-all, and refuses the one over the plan without asking the panel', function () {
    LaravelNotification::fake();
    $panel = [];
    [$service] = s1MailService($this, $panel);

    $features = $this->withHeaders(e2eHeaders('features'))->getJson("/v1/services/{$service->id}/features")->assertOk();
    expect($features->json('data.features.aliases'))->toMatchArray(['enabled' => true, 'limit' => 50]); // the plan's own number

    // a smaller plan keeps the flow short; the number is read from the service, as the plan put it there
    $service->forceFill(['entitlements' => array_merge((array) $service->entitlements, ['aliases' => 2])])->save();

    s1Run($this, $service, 'alias.create', ['source' => 'sales@mala-posta.cz', 'destination' => 'eva@example.org'], 'alias-1');
    s1Run($this, $service, 'forward.create', ['source' => 'office@mala-posta.cz', 'destination' => 'eva@example.org'], 'forward-1');
    s1Run($this, $service, 'forward.create', ['source' => 'shop@mala-posta.cz', 'destination' => 'eva@example.org'], 'forward-2');
    $panel['mail_aliases'][700] = ['forwarding_id' => 700, 'source' => '@mala-posta.cz', 'destination' => 'eva@example.org', 'type' => 'catchall', 'active' => 'y', 'sys_groupid' => 1];

    // one alias, two forwards and a catch-all in the table: the second alias still fits a plan of two
    s1Run($this, $service, 'alias.create', ['source' => 'info@mala-posta.cz', 'destination' => 'eva@example.org'], 'alias-2');
    $aliases = $this->withHeaders(e2eHeaders('list'))->getJson("/v1/services/{$service->id}/resources/aliases")->assertOk()->json('data');
    expect(collect($aliases)->pluck('source')->sort()->values()->all())->toBe(['info@mala-posta.cz', 'sales@mala-posta.cz']);

    // the third is refused with the plan's number, and the panel is not asked to add it
    $adds = count(array_keys($panel['calls'], 'mail_alias_add', true));
    $over = s1Action($this, $service, 'alias.create', ['source' => 'jobs@mala-posta.cz', 'destination' => 'eva@example.org'], 'alias-3')->assertStatus(422);
    expect($over->json('error'))->toBe('feature_limit_reached')->and($over->json('limit'))->toBe(2)->and($over->json('used'))->toBe(2)
        ->and(count(array_keys($panel['calls'], 'mail_alias_add', true)))->toBe($adds);

    // forwards have no number of the plan: they stay possible
    s1Run($this, $service, 'forward.create', ['source' => 'hr@mala-posta.cz', 'destination' => 'eva@example.org'], 'forward-3');

    // deleting an alias frees a place
    $sales = collect($aliases)->firstWhere('source', 'sales@mala-posta.cz');
    s1Run($this, $service, 'alias.delete', ['remote_id' => $sales['remote_id']], 'alias-del');
    s1Run($this, $service, 'alias.create', ['source' => 'jobs@mala-posta.cz', 'destination' => 'eva@example.org'], 'alias-3-again');
});

it('keeps an alias delete to aliases: a forward id sent as an alias is not ours to delete', function () {
    LaravelNotification::fake();
    $panel = [];
    [$service] = s1MailService($this, $panel);
    s1Run($this, $service, 'forward.create', ['source' => 'office@mala-posta.cz', 'destination' => 'eva@example.org'], 'forward-1');
    $forward = collect($panel['mail_aliases'])->firstWhere('type', 'forward');

    $operation = driveOperation(Operation::query()->findOrFail(s1Action($this, $service, 'alias.delete', ['remote_id' => (string) $forward['forwarding_id']], 'alias-del')->assertStatus(202)->json('operation_id')));
    expect($operation->state)->toBe(Operation::FAILED)->and((string) data_get($operation->error, 'message', ''))->toContain('nepatří')
        ->and($panel['mail_aliases'])->toHaveKey($forward['forwarding_id']);
});

it('lists aliases and forwards apart although ISPConfig answers both from one table', function () {
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web', 'mail'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);
    /** @var IspConfigWebProvider $adapter */
    $adapter = $registry->forInstance($instance);
    $table = [
        ['forwarding_id' => '11', 'source' => 'sales@shop.cz', 'destination' => 'a@shop.cz', 'type' => 'alias', 'active' => 'y'],
        ['forwarding_id' => '12', 'source' => 'office@shop.cz', 'destination' => 'b@gmail.com', 'type' => 'forward', 'active' => 'y'],
        ['forwarding_id' => '13', 'source' => '@shop.cz', 'destination' => 'a@shop.cz', 'type' => 'catchall', 'active' => 'y'],
        ['forwarding_id' => '14', 'source' => '@shop.cz', 'destination' => '@shop.sk', 'type' => 'aliasdomain', 'active' => 'y'],
    ];
    Http::fake(function (Request $request) use ($table) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $function === 'login' ? 'sess' : (in_array($function, ['mail_alias_get', 'mail_forward_get'], true) ? $table : [])]);
    });
    $domain = new ResourceRef('mail_domain', '900', '1', ['domain' => 'shop.cz', 'client_id' => 3]);

    expect(collect($adapter->listAliases($domain))->pluck('remote_id')->all())->toBe(['11'])
        ->and(collect($adapter->listForwards($domain))->pluck('remote_id')->all())->toBe(['12']);
});

it('takes aliases off the list of metering gaps: enforced on mail plans, still not kept anywhere else', function () {
    expect(PlanPromises::KNOWN_GAPS)->not->toHaveKey('aliases')->toHaveCount(6)
        ->and(MetricRegistry::get('aliases')['status'])->toBe(MetricRegistry::ENFORCED_ONLY)
        ->and(MetricRegistry::isKept('aliases', 'mail'))->toBeTrue()
        ->and(MetricRegistry::isKept('aliases', 'web'))->toBeFalse();
});
