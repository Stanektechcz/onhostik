<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
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
