<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Identity\WebSessions;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Integrations\Models\DiscordLink;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Marketplace\Models\MarketplaceListing;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Payments\Models\PaymentMethod;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0098: an identifier of somebody else's organization is answered as one that does not exist. The resolvers found the row,
 * answered 404 only when it was absent and then asked authorize(), whose "Missing permission X" (403) confirmed to a stranger
 * that the service, the invoice number, the domain name or the zone exists — invoice numbers are sequential and domain names
 * are public, so the 403 was an oracle. Somebody with no membership, no share and no staff reach in the organization now gets
 * the 404 of a missing row; a member who lacks the permission keeps the 403 that tells them to ask for it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::fake(); // nothing may reach a panel from here
    $this->withoutMiddleware(ThrottleRequests::class);
});

/** Organization A with one of everything the resolvers find. @return array{0:User,1:array<string,string>,2:array<string,string>} */
function eoOrganizationA(User $owner, Organization $org, CommandContext $ctx): array
{
    $service = featureWebService($org, 'aapanel');
    $project = Project::query()->where('organization_id', $org->id)->firstOrFail();
    $backup = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'completed', 'remote_id' => 'bk-1', 'size_bytes' => 1024, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'oracle-a.cz', 'fqdn_unicode' => 'oracle-a.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear()]);
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'domain_id' => $domain->id, 'name' => 'oracle-a.cz', 'provider' => 'powerdns', 'state' => 'active']);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'eo:q1', $ctx)['order'];
    $subscription = Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 10000, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now()->subDays(3), 'current_period_end' => now()->addDays(27), 'next_renewal_at' => now()->addDays(27)]);
    $invoice = Invoice::query()->findOrFail($order->invoice_id);

    $ids = ['service' => $service->id, 'organization' => $org->id, 'zone' => $zone->id, 'domain' => $domain->id, 'order' => $order->id, 'invoice' => $invoice->id,
        'project' => $project->id, 'backup' => $backup->id, 'intent' => (string) $order->payment_intent_id, 'subscription' => $subscription->id];
    expect(array_filter($ids, fn ($v) => $v === null || $v === ''))->toBe([]);
    // the same places addressed by what a stranger can guess or read: the sequential invoice number, the public domain name
    $named = ['invoice_number' => (string) $invoice->number, 'fqdn' => 'oracle-a.cz'];
    expect($named['invoice_number'])->not->toBe('');

    return [$owner, $ids, $named];
}

/** @param array{0:User,1:Organization} $pair @return array{0:User,1:Organization,2:CommandContext} */
function eoOwnerOf(array $pair): array
{
    return [$pair[0], $pair[1], new CommandContext('user', $pair[0]->id, $pair[1]->id, null, '127.0.0.1', 'pest', 'test-session')];
}

/** The customer routes of the resolvers this task covers, with the address filled from $ids. @return list<array{0:string,1:string}> */
function eoRoutes(array $ids, array $fill): array
{
    $controllers = ['Service', 'WebTools', 'ServiceAccess', 'Project', 'Invoice', 'Order', 'Domain', 'Billing', 'Dns', 'Payment'];
    $out = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'v1/') || str_starts_with($uri, 'v1/staff') || str_starts_with($uri, 'v1/hooks') || str_starts_with($uri, 'v1/webhooks')) {
            continue;
        }
        $class = class_basename((string) $route->getControllerClass());
        if (! in_array(substr($class, 0, -strlen('Controller')), $controllers, true)) {
            continue;
        }
        preg_match_all('/\{(\w+)\??\}/', $uri, $m);
        if ($m[1] === [] || array_intersect($m[1], array_keys($ids)) === []) {
            continue;
        }
        $path = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($p) => (string) ($ids[$p[1]] ?? $fill[$p[1]]), $uri);
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $out[] = [$method, $path];
        }
    }

    return $out;
}

function eoStatus(object $test, $who, string $method, string $uri, string $guard = 'sanctum'): int
{
    $test->actingAs($who, $guard);
    $status = $test->json($method, $uri, [], ['Idempotency-Key' => 'eo-'.md5($method.$uri.spl_object_id($who)), 'Accept' => 'application/json'])->getStatusCode();
    app('auth')->forgetGuards();

    return $status;
}

it('answers a stranger the same for another organization\'s identifier as for one that does not exist', function () {
    [, $ids] = eoOrganizationA(...eoOwnerOf($this->customerWithOrganization()));
    [$stranger] = $this->customerWithOrganization(); // the full owner of an organization of their own, freshly stepped up
    app(StepUpService::class)->grant($stranger, 'totp', null, '127.0.0.1');
    $fill = ['kind' => 'databases', 'token' => 'dl_abcdefghijklmnopqrstuvwx', 'action' => 'restart', 'grant' => 'grt_missing', 'monitor' => 'mon_missing', 'user' => 'usr_missing'];
    $missing = array_map(fn (string $id) => '01JZZZZZZZZZZZZZZZZZZZZZZZ', $ids);

    $foreign = eoRoutes($ids, $fill);
    $absent = eoRoutes($missing, $fill);
    expect(count($foreign))->toBe(count($absent))->and(count($foreign))->toBeGreaterThan(80);

    $oracles = [];
    $notFound = 0;
    foreach ($foreign as $i => [$method, $uri]) {
        $there = eoStatus($this, $stranger, $method, $uri);
        $nowhere = eoStatus($this, $stranger, $method, $absent[$i][1]);
        if ($there === 403 || $there !== $nowhere || $there < 300) {
            $oracles[] = "{$method} {$uri} → {$there} (a missing one → {$nowhere})";
        }
        $notFound += $there === 404 ? 1 : 0;
    }
    expect($oracles)->toBe([], "A stranger can tell another organization's identifier from a missing one:\n".implode("\n", $oracles))
        ->and($notFound)->toBeGreaterThan(80);
});

it('answers 404 for a foreign invoice number, domain name and zone name, 403 to a member without the permission', function () {
    [$owner, $ids, $named] = eoOrganizationA(...eoOwnerOf($this->customerWithOrganization()));
    $orgId = $ids['organization'];
    [$stranger] = $this->customerWithOrganization();
    // a member of A who may see nothing by that membership (a guest a service could be shared with): a party, told what is missing
    $guest = User::query()->create(['email' => 'eo-guest@oracle.test', 'name' => 'Host', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    OrganizationMembership::query()->create(['organization_id' => $orgId, 'user_id' => $guest->id, 'state' => 'active', 'role_key' => 'guest', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'guest', 'scope_type' => 'organization', 'scope_id' => $orgId, 'organization_id' => $orgId]);

    $probes = [
        ['GET', "/v1/services/{$ids['service']}"], ['GET', "/v1/services/{$ids['service']}/access"], ['GET', "/v1/services/{$ids['service']}/deploy"],
        ['GET', "/v1/invoices/{$ids['invoice']}"], ['GET', "/v1/invoices/{$named['invoice_number']}"],
        ['GET', "/v1/orders/{$ids['order']}"], ['GET', "/v1/domains/{$ids['domain']}"], ['GET', "/v1/domains/{$named['fqdn']}"],
        ['GET', "/v1/dns/zones/{$ids['zone']}"], ['GET', "/v1/dns/zones/{$named['fqdn']}"], ['GET', "/v1/domains/{$named['fqdn']}/zone"],
        ['GET', "/v1/payments/{$ids['intent']}"], ['POST', "/v1/subscriptions/{$ids['subscription']}/auto-renew"],
        ['GET', "/v1/organizations/{$orgId}/projects"], ['GET', "/v1/organizations/{$orgId}/projects/{$ids['project']}"],
    ];
    foreach ($probes as [$method, $uri]) {
        expect(eoStatus($this, $stranger, $method, $uri))->toBe(404, "stranger {$method} {$uri}")
            ->and(eoStatus($this, $guest, $method, $uri))->toBe(403, "member without the permission {$method} {$uri}");
    }
    // the control: the owner is answered, so the 404 above is about who asks
    expect(eoStatus($this, $owner, 'GET', "/v1/invoices/{$named['invoice_number']}"))->toBe(200)
        ->and(eoStatus($this, $owner, 'GET', "/v1/domains/{$named['fqdn']}"))->toBe(200)
        ->and(eoStatus($this, $owner, 'GET', "/v1/dns/zones/{$named['fqdn']}"))->toBe(200)
        ->and(eoStatus($this, $owner, 'GET', "/v1/services/{$ids['service']}"))->toBe(200);
});

it('answers 404 to another organization\'s service account token and lets staff with the customer view through', function () {
    [, $ids] = eoOrganizationA(...eoOwnerOf($this->customerWithOrganization()));
    [$otherOwner, $otherOrg] = $this->customerWithOrganization();
    $account = ServiceAccount::query()->create(['organization_id' => $otherOrg->id, 'name' => 'CI of B', 'state' => 'active', 'created_by' => $otherOwner->id]);
    $plain = $account->createToken('ci', ['services:read', 'org:'.$otherOrg->id], now()->addDays(30));
    $plain->accessToken->forceFill(['organization_id' => $otherOrg->id])->save();

    $this->withToken($plain->plainTextToken)->getJson("/v1/services/{$ids['service']}")->assertNotFound()->assertJsonPath('error', 'not_found');
    app('auth')->forgetGuards();

    $staff = $this->staff('platform_owner');
    expect(eoStatus($this, $staff, 'GET', "/v1/services/{$ids['service']}"))->not->toBe(404);
});

it('keeps the 403 for a guest who holds a share on one service and asks for its sibling: a party of the organization', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shared = featureWebService($org, 'aapanel');
    $sibling = Service::query()->create(array_merge($shared->only(['product_key', 'family', 'name', 'region_code', 'provider_instance_id', 'node_id', 'desired_spec', 'entitlements', 'sla_class']), ['organization_id' => $org->id, 'hostname' => 'sibling-a.cz', 'state' => $shared->state]));
    $guest = User::query()->create(['email' => 'eo-share@oracle.test', 'name' => 'Agentura', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $guest->id, 'state' => 'active', 'role_key' => 'guest', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'guest', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'svc_view', 'scope_type' => 'resource', 'scope_id' => $shared->id, 'organization_id' => $org->id]);

    expect(eoStatus($this, $guest, 'GET', "/v1/services/{$shared->id}"))->toBe(200)
        // by design (TASK-0098): somebody with a binding in the organization is a party of it — told what they lack, not "not found"
        ->and(eoStatus($this, $guest, 'GET', "/v1/services/{$sibling->id}"))->toBe(403)
        ->and(eoStatus($this, $owner, 'GET', "/v1/services/{$sibling->id}"))->toBe(200);
    // the share alone, without the guest membership row (a binding written by the share flow), is still a party
    OrganizationMembership::query()->where('user_id', $guest->id)->delete();
    PolicyBinding::query()->where('principal_id', $guest->id)->where('scope_type', 'organization')->delete();
    expect(eoStatus($this, $guest, 'GET', "/v1/services/{$sibling->id}"))->toBe(403);
});

// ── G7 (TASK-0115): the sweep over every customer controller ──
/*
 * The first sweep above covers the ten controllers of TASK-0098. Every other authenticated customer route that addresses a row by
 * an identifier — abuse cases, data requests, marketplace orders and listings, registrar connections, service accounts and their
 * tokens, tickets and work offers, payment methods, web sessions, personal tokens, invitations, members, hooks, Discord links,
 * archives, ISOs — must answer a stranger the same for organization A's identifier as for one that does not exist. The router is
 * the list: a new route with a parameter and no row below fails the sweep until it gets one.
 */

/** Organization A's further rows for the other controllers. Keys: `Controller@param` where one parameter name means different rows. @return array<string,string> */
function eoMoreRowsOfA(User $owner, Organization $org, array $ids): array
{
    $service = Service::query()->findOrFail($ids['service']);
    $partner = Partner::query()->create(['organization_id' => $org->id, 'code' => 'EOPARTNER', 'state' => 'active']);
    $listing = MarketplaceListing::query()->create(['partner_id' => $partner->id, 'key' => 'eo-care', 'title' => 'Care', 'price_minor' => 10000, 'state' => 'draft']);
    $marketOrder = MarketplaceOrder::query()->create(['listing_id' => $listing->id, 'partner_id' => $partner->id, 'organization_id' => $org->id, 'ordered_by' => $owner->id, 'state' => 'ordered', 'price_minor' => 10000]);
    $ticket = app(TicketService::class)->create(['subject' => 'Oracle', 'body' => 'Ticket of A.'], new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session'), $org, $owner);
    $offer = WorkOffer::query()->create(['ticket_id' => $ticket->id, 'organization_id' => $org->id, 'scope' => 'administration', 'description' => 'Work', 'price_net_minor' => 10000, 'currency' => 'CZK', 'state' => 'proposed']);
    $account = ServiceAccount::query()->create(['organization_id' => $org->id, 'name' => 'CI of A', 'state' => 'active', 'created_by' => $owner->id]);
    $accountToken = $account->createToken('ci', ['services:read', 'org:'.$org->id], now()->addDays(30))->accessToken;
    $personal = $owner->createToken('mine', ['services:read', 'org:'.$org->id])->accessToken;
    $personal->forceFill(['organization_id' => $org->id])->save();
    $invitation = OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => 'eo-invited@oracle.test', 'role_key' => 'viewer', 'token_hash' => hash('sha256', 'eo-invite'), 'expires_at' => now()->addDays(7)]);
    $hook = ActionHook::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'created_by' => $owner->id, 'name' => 'restart', 'action' => 'restart', 'params' => [], 'token_hash' => hash('sha256', 'eo-hook'), 'enabled' => true]);
    $link = DiscordLink::query()->create(['organization_id' => $org->id, 'user_id' => $owner->id, 'discord_user_id' => '7001', 'discord_username' => 'owner-a', 'state' => 'linked', 'linked_at' => now(), 'locale' => 'cs']);
    $connection = RegistrarConnection::query()->create(['organization_id' => $org->id, 'provider' => 'wedos', 'label' => 'WEDOS', 'login' => 'a@b.cz', 'secret_ref' => 'db://registrar-connections/eo', 'state' => 'active']);
    $method = PaymentMethod::query()->create(['organization_id' => $org->id, 'provider' => 'comgate', 'provider_token' => 'tok-eo', 'kind' => 'card', 'brand' => 'visa', 'last4' => '4242', 'state' => 'active']);
    $abuse = AbuseCase::query()->create(['number' => 'ABU-2026-9101', 'reporter' => ['name' => 'R', 'email' => 'r@example.cz'], 'category' => 'phishing', 'allegation' => 'A phishing page.', 'organization_id' => $org->id, 'service_id' => $service->id]);
    $dataRequest = DataRequest::query()->create(['organization_id' => $org->id, 'requested_by' => $owner->id, 'kind' => 'export', 'state' => 'requested', 'meta' => []]);
    $session = app(WebSessions::class)->open($owner->id, '127.0.0.1', 'pest');
    $endpoint = WebhookEndpoint::query()->create(['organization_id' => $org->id, 'url' => 'https://hooks.example.com/a', 'secret' => 'whsec-eo', 'events' => ['*'], 'state' => 'active', 'created_by' => $owner->id]);
    $delivery = WebhookDelivery::query()->create(['endpoint_id' => $endpoint->id, 'event' => 'service.activated', 'payload' => ['x' => 1], 'state' => 'failed', 'attempts' => 1]);

    return [
        'case' => $abuse->id, 'dataRequest' => $dataRequest->id, 'hook' => $hook->id, 'link' => $link->id, 'listing' => $listing->id,
        'MarketplaceController@order' => $marketOrder->id, 'entry' => 'evidence-1', 'key' => 'file-1', 'connection' => $connection->id, 'account' => $account->id,
        'ServiceAccountController@token' => (string) $accountToken->getKey(), 'MeController@token' => (string) $personal->getKey(), 'ticket' => $ticket->id, 'offer' => $offer->id,
        'method' => $method->id, 'session' => $session, 'invitation' => $invitation->id, 'user' => $owner->id, 'endpoint' => $endpoint->id, 'delivery' => $delivery->id,
    ];
}

/** The status and the error code a request is answered with. @return array{0:int,1:string} */
function eoAnswer(object $test, $who, string $method, string $uri): array
{
    $test->actingAs($who, 'sanctum');
    $response = $test->json($method, $uri, [], ['Idempotency-Key' => 'eo-'.md5($method.$uri.spl_object_id($who)), 'Accept' => 'application/json']);
    app('auth')->forgetGuards();

    return [$response->getStatusCode(), (string) ($response->json('error') ?? '')];
}

/** What stands in for each parameter when the row does not exist, shaped like a real one so a format check cannot tell them apart. @return array<string,string> */
function eoMissingOf(array $ids): array
{
    $missing = [];
    foreach ($ids as $key => $value) {
        $missing[$key] = match (true) {
            in_array($key, ['entry', 'key', 'kind', 'action', 'token', 'grant', 'monitor'], true) => $value,
            ctype_digit($value) => '987654321',
            str_starts_with($value, 'ws_') => 'ws_01jzzzzzzzzzzzzzzzzzzzzzzz',
            default => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        };
    }

    return $missing;
}

/**
 * Every authenticated customer route with a parameter, filled from `$ids` (`Controller@param` first, then `param`); routes with a
 * parameter nobody filled come back apart. @return array{0:list<array{0:string,1:string,2:string}>, 1:list<string>}
 */
function eoEveryCustomerRoute(array $ids): array
{
    $out = [];
    $unfilled = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'v1/') || str_starts_with($uri, 'v1/staff') || ! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
            continue; // public, staff and inbound routes answer to nobody's organization
        }
        if (preg_match_all('/\{(\w+)\??\}/', $uri, $m) === 0) {
            continue;
        }
        $controller = class_basename((string) $route->getControllerClass());
        $notFilled = array_filter($m[1], fn (string $p) => ! isset($ids["{$controller}@{$p}"]) && ! isset($ids[$p]));
        if ($notFilled !== []) {
            $unfilled[] = "{$uri} ({$controller}: ".implode(', ', $notFilled).')';

            continue;
        }
        $path = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($p) => (string) ($ids["{$controller}@{$p[1]}"] ?? $ids[$p[1]]), $uri);
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $out[] = [$method, $path, $controller];
        }
    }

    return [$out, $unfilled];
}

it('answers a stranger the same for organization A\'s identifier as for a missing one on every customer controller', function () {
    [$owner, $org, $ctx] = eoOwnerOf($this->customerWithOrganization());
    [, $ids] = eoOrganizationA($owner, $org, $ctx);
    $ids += eoMoreRowsOfA($owner, $org, $ids) + ['kind' => 'databases', 'action' => 'restart', 'grant' => 'grt_missing', 'monitor' => 'mon_missing', 'token' => 'dl_abcdefghijklmnopqrstuvwx'];
    [$stranger] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($stranger, 'totp', null, '127.0.0.1');

    [$foreign, $unfilled] = eoEveryCustomerRoute($ids);
    [$absent] = eoEveryCustomerRoute(eoMissingOf($ids));
    expect($unfilled)->toBe([], "Routes with a parameter the sweep has no row of A for — add one to eoMoreRowsOfA():\n".implode("\n", $unfilled))
        ->and(count($foreign))->toBe(count($absent));

    $oracles = [];
    $controllers = [];
    foreach ($foreign as $i => [$method, $uri, $controller]) {
        // the status AND the error code: a refusal that does not depend on the row (the stranger is no partner, a browser-only
        // route) is the same for both and tells nothing; a 403 for A's row next to a 404 for a missing one is the oracle
        $there = eoAnswer($this, $stranger, $method, $uri);
        $nowhere = eoAnswer($this, $stranger, $method, $absent[$i][1]);
        if ($there !== $nowhere || $there[0] < 300) {
            $oracles[] = "{$controller}: {$method} {$uri} → {$there[0]} {$there[1]} (a missing one → {$nowhere[0]} {$nowhere[1]})";
        }
        $controllers[$controller] = true;
    }
    expect($oracles)->toBe([], "A stranger can tell organization A's identifier from a missing one:\n".implode("\n", $oracles))
        ->and(array_keys($controllers))->toContain('MarketplaceController', 'RegistrarConnectionController', 'ServiceAccountController', 'SupportController', 'WalletController', 'WebSessionController', 'MeController', 'OrganizationController', 'ComplianceController', 'IntegrationController', 'ArchiveController', 'CustomIsoController', 'WebhookController');
});
// ── end G7 ──

// ── H4 (TASK-0124): the browser-side customer routes ──
/*
 * The two sweeps above walk `v1/*`. The routes a browser opens outside the API — the graphical console page, the console token
 * pre-flight, the signed downloads (data export, archive, calendar feed, mailbox password page) — address a row by an identifier
 * too. A stranger must get the same answer for organization A's identifier as for one that does not exist; the signed ones are
 * refused for the signature before any row is looked up. The router is the list: a new web route with a parameter and with
 * `auth` or `signed` middleware that is not named below fails this test until it gets a row.
 */

/** The web (non-API) customer routes with a parameter and why each is covered. @return array<string,string> uri => why */
function eoBrowserRoutesCovered(): array
{
    return [
        'panel/konzole/{service}' => 'the noVNC page: 404 to anybody who may not open this server\'s console',
        'console/check/{token}' => 'the token pre-flight: valid:false for a token that is not yours, as for one that does not exist',
        'export/{dataRequest}/{token}' => 'signed: the signature is checked before the row',
        'archiv/{organization}/{backup}' => 'signed: the signature is checked before the row',
        'calendar/{organization}.ics' => 'signed: the signature is checked before the row',
        'mailbox/password/{token}' => 'signed (GET and POST): the signature is checked before the token',
        'sprava/konzole/{service}' => 'staff console page: refused for a customer, with or without the row',
    ];
}

it('names every browser-side customer route that addresses a row, so a new one cannot escape the sweep', function () {
    $found = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        if (str_starts_with($uri, 'v1/') || ! str_contains($uri, '{')) {
            continue;
        }
        $middleware = array_map('strval', $route->gatherMiddleware());
        if (in_array('auth:sanctum', $middleware, true) || in_array('signed', $middleware, true)) {
            $found[$uri] = true;
        }
    }

    expect(array_values(array_diff(array_keys($found), array_keys(eoBrowserRoutesCovered()))))->toBe([], 'A web route with a parameter and auth/signed middleware has no row in eoBrowserRoutesCovered() and no probe below.')
        ->and(array_values(array_diff(array_keys(eoBrowserRoutesCovered()), array_keys($found))))->toBe([], 'eoBrowserRoutesCovered() names a route that no longer exists.');
});

it('answers a stranger the same for organization A\'s console page, console token and signed links as for ones that do not exist', function () {
    config()->set('onhost.console.relay_url', 'wss://relay.onhost.test');
    [$owner, $org, $ctx] = eoOwnerOf($this->customerWithOrganization());
    [, $ids] = eoOrganizationA($owner, $org, $ctx);
    [$stranger] = $this->customerWithOrganization();

    $instance = pveLab();
    $vps = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'label' => 'eo-console', 'hostname' => 'vm-eo.cust.onhost.cz', 'state' => 'active',
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => Node::query()->where('name', 'prg1-n2')->firstOrFail()->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $vps->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1043', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'eo-console-binding']);
    $missingService = 'svc_01JZZZZZZZZZZZZZZZZZZZZZZZ';

    // the console page: the owner is let in, a stranger is told "not found" for A's server exactly as for one that is not there
    $this->actingAs($owner)->get("/panel/konzole/{$vps->id}")->assertOk();
    app('auth')->forgetGuards();
    $a = $this->actingAs($stranger)->get("/panel/konzole/{$vps->id}");
    app('auth')->forgetGuards();
    $b = $this->actingAs($stranger)->get("/panel/konzole/{$missingService}");
    app('auth')->forgetGuards();
    expect([$a->getStatusCode(), $b->getStatusCode()])->toBe([404, 404]);

    // the staff console page is refused to a customer whatever the identifier
    $c = $this->actingAs($stranger)->get("/sprava/konzole/{$vps->id}");
    app('auth')->forgetGuards();
    $d = $this->actingAs($stranger)->get("/sprava/konzole/{$missingService}");
    app('auth')->forgetGuards();
    expect($c->getStatusCode())->toBe($d->getStatusCode())->and($c->getStatusCode())->not->toBe(200);

    // the token pre-flight: a live console token of A is "not valid" for a stranger, the same body as a token nobody issued
    $token = 'eo-console-token-'.bin2hex(random_bytes(6));
    Cache::put("onhost:console:{$token}", ['kind' => 'vnc', 'service_id' => $vps->id, 'organization_id' => $org->id, 'issued_to' => $owner->id, 'issued_at' => now()->toIso8601String()], 120);
    $real = $this->actingAs($stranger, 'sanctum')->getJson("/console/check/{$token}");
    app('auth')->forgetGuards();
    $none = $this->actingAs($stranger, 'sanctum')->getJson('/console/check/eo-console-token-nobody');
    app('auth')->forgetGuards();
    expect($real->json('data.valid'))->toBeFalse()->and($real->json('data.valid'))->toBe($none->json('data.valid'))->and($real->getStatusCode())->toBe($none->getStatusCode());

    // the signed links: refused for the signature, before any row is looked up, so A's identifier and a missing one cannot be told apart
    $probes = [
        ['GET', "/export/{$ids['order']}/anytoken", '/export/01JZZZZZZZZZZZZZZZZZZZZZZZ/anytoken'],
        ['GET', "/archiv/{$org->id}/{$ids['backup']}", '/archiv/01JZZZZZZZZZZZZZZZZZZZZZZZ/01JZZZZZZZZZZZZZZZZZZZZZZZ'],
        ['GET', "/calendar/{$org->id}.ics", '/calendar/01JZZZZZZZZZZZZZZZZZZZZZZZ.ics'],
        ['GET', '/mailbox/password/'.$token, '/mailbox/password/eo-console-token-nobody'],
        ['POST', '/mailbox/password/'.$token, '/mailbox/password/eo-console-token-nobody'],
    ];
    foreach ($probes as [$method, $foreign, $absent]) {
        $x = $this->actingAs($stranger)->call($method, $foreign);
        app('auth')->forgetGuards();
        $y = $this->actingAs($stranger)->call($method, $absent);
        app('auth')->forgetGuards();
        expect([$x->getStatusCode(), $y->getStatusCode()])->toBe([403, 403], "{$method} {$foreign}");
    }
});
// ── end H4 ──
