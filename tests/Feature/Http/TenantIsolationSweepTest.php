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
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\TicketService;

/*
 * One customer never reaches another's data (Brain card H03). The second customer here is no visitor: the owner of their
 * own organization, freshly verified, with every customer permission there is — and they walk every customer route of
 * the API with the first organization's identifiers. Wherever an identifier of the other organization is in the
 * address, the answer must be a refusal; a 2xx is a leak, whatever the body says.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('refuses every customer route addressed with another organization\'s identifiers', function () {
    Http::fake(); // nothing may reach a panel from here
    $this->withoutMiddleware(ThrottleRequests::class);

    // ── organization A and everything it owns
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $service = featureWebService($org, 'aapanel');
    $project = Project::query()->where('organization_id', $org->id)->firstOrFail();
    $backup = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'completed', 'remote_id' => 'bk-1', 'size_bytes' => 1024, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'tenant-a.cz', 'fqdn_unicode' => 'tenant-a.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear()]);
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'domain_id' => $domain->id, 'name' => 'tenant-a.cz', 'provider' => 'powerdns', 'state' => 'active']);
    $ticket = app(TicketService::class)->create(['subject' => 'Interní dotaz organizace A', 'body' => 'Tohle nesmí vidět nikdo jiný.'], $ctx, $org, $owner);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'iso:q1', $ctx)['order'];
    $subscription = Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 10000, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now()->subDays(3), 'current_period_end' => now()->addDays(27), 'next_renewal_at' => now()->addDays(27)]);

    $ids = [
        'service' => $service->id, 'organization' => $org->id, 'zone' => $zone->id, 'domain' => $domain->id, 'order' => $order->id, 'invoice' => $order->invoice_id,
        'project' => $project->id, 'ticket' => $ticket->id, 'user' => $owner->id, 'backup' => $backup->id, 'intent' => $order->payment_intent_id, 'subscription' => $subscription->id,
    ];
    expect(array_filter($ids, fn ($v) => $v === null || $v === ''))->toBe([]); // every identifier is a real row of organization A

    // ── organization B: a full owner of their own, step-up fresh — only the organization is wrong
    [$stranger] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($stranger, 'totp', null, '127.0.0.1');

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/') && ! str_starts_with($r->uri(), 'v1/staff'))
        ->filter(function ($r) use ($ids) { // only routes addressed by something that belongs to organization A
            preg_match_all('/\{(\w+)\??\}/', $r->uri(), $m);

            return $m[1] !== [] && array_intersect($m[1], array_keys($ids)) !== [] && array_diff($m[1], array_keys($ids), ['kind', 'token', 'key', 'entry']) === [];
        })->values();
    expect($routes->count())->toBeGreaterThan(80);

    $fill = ['kind' => 'databases', 'token' => 'dl_abcdefghijklmnopqrstuvwx', 'key' => 'k', 'entry' => '1'];
    $leaks = [];
    $unclear = [];
    $calls = 0;
    foreach ($routes as $route) {
        $uri = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($ids[$m[1]] ?? $fill[$m[1]]), $route->uri());
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $this->actingAs($stranger, 'sanctum');
            $response = $this->json($method, $uri, [], ['Idempotency-Key' => 'iso-'.md5($method.$uri), 'Accept' => 'application/json']);
            $calls++;
            $status = $response->getStatusCode();
            if ($status < 300) {
                $leaks[] = "{$method} {$uri} → {$status} ".substr((string) $response->getContent(), 0, 140);
            } elseif (! in_array($status, [401, 403, 404, 405], true) && $status < 500) {
                $unclear[] = "{$method} {$uri} → {$status} ".substr((string) $response->getContent(), 0, 110);
            } elseif ($status >= 500) {
                $leaks[] = "{$method} {$uri} → {$status} (a server error is not a refusal) ".substr((string) $response->getContent(), 0, 140);
            }
            app('auth')->forgetGuards();
        }
    }

    file_put_contents(storage_path('logs/tenant-isolation-sweep.txt'), ($leaks === [] ? 'no leaks' : implode("\n", $leaks))."\n{$calls} calls over {$routes->count()} routes\n\nnot a plain refusal (".count($unclear)."):\n".implode("\n", $unclear)."\n");
    expect($leaks)->toBe([], "Another organization's owner was answered:\n".implode("\n", $leaks))->and($calls)->toBeGreaterThan(80);

    // a route that validates before it authorizes answers 422 to an empty body, which proves nothing: those are tried again
    // with a body that would pass, and a new one appearing here has to be looked at before it is added to the list
    $validatesFirst = array_map(fn (string $line) => preg_replace('~/(ord|bkp)_[a-z0-9]+~', '/{id}', explode(' →', $line)[0]), $unclear);
    sort($validatesFirst);
    expect($validatesFirst)->toBe(['POST /v1/account/marketplace/orders/{id}/dispute', 'POST /v1/services/archives/{id}/restore']);
    [$strangerAgain, $strangerOrg] = [$stranger, Organization::query()->where('owner_user_id', $stranger->id)->firstOrFail()];
    $final = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true, 'size_bytes' => 2048, 'started_at' => now()->subDay(), 'finished_at' => now()->subDay()]);
    $theirs = Service::query()->create(array_merge($service->only(['product_key', 'family', 'name', 'region_code', 'provider_instance_id', 'node_id', 'desired_spec', 'entitlements', 'sla_class']), ['organization_id' => $strangerOrg->id, 'hostname' => 'tenant-b.cz', 'state' => $service->state]));
    $this->actingAs($strangerAgain, 'sanctum');
    foreach ([$theirs->id, $service->id] as $n => $target) { // into their own service, and into the victim's
        $this->withHeader('Idempotency-Key', "iso-restore-{$n}")->postJson("/v1/services/archives/{$final->id}/restore", ['service_id' => $target])->assertNotFound();
    }
    $this->withHeader('Idempotency-Key', 'iso-download')->postJson("/v1/services/archives/{$final->id}/download")->assertNotFound();
    $this->withHeader('Idempotency-Key', 'iso-dispute')->postJson("/v1/account/marketplace/orders/{$order->id}/dispute", ['reason' => 'zkouším cizí objednávku'])->assertNotFound();
    expect($final->fresh()->state)->toBe('completed');
    app('auth')->forgetGuards();
    // the control: the same addresses answer their rightful owner, so the refusals above are about the organization, not about identifiers that lead nowhere
    $answered = 0;
    $kinds = [];
    foreach ($routes as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $uri = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($ids[$m[1]] ?? $fill[$m[1]]), $route->uri());
        $this->actingAs($owner, 'sanctum');
        if ($this->json('GET', $uri, [], ['Accept' => 'application/json'])->getStatusCode() < 300) {
            $answered++;
            preg_match_all('/\{(\w+)\??\}/', $route->uri(), $m);
            $kinds = array_unique(array_merge($kinds, $m[1]));
        }
        app('auth')->forgetGuards();
    }
    expect($answered)->toBeGreaterThan(20);
    foreach (['service', 'organization', 'domain', 'zone', 'order', 'invoice', 'project', 'ticket'] as $kind) {
        expect(in_array($kind, $kinds, true))->toBeTrue("no route addressed by {$kind} answered its own organization: the sweep proves nothing about it");
    }

    // and nothing of organization A changed under the attempts
    expect($service->fresh()->state)->toBe($service->state)->and($domain->fresh()->state)->toBe(DomainStateMachine::ACTIVE)
        ->and(DnsZone::query()->whereKey($zone->id)->exists())->toBeTrue()->and($ticket->fresh()->state)->toBe($ticket->state);
});
