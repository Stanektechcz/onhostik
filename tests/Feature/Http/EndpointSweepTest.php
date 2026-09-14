<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;

/*
 * Smoke test of the whole /v1 surface: every route is called as an anonymous visitor, as a customer and as staff.
 * Bodies are empty and unknown ids are used where no fixture exists, so the expected answers are validation errors,
 * 401/403/404/409 or success — never a 5xx and never a non-JSON error page. The sweep is what makes "all endpoints
 * answer" a verifiable statement instead of a hope; a new route is covered automatically.
 */
beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]));

it('answers every v1 endpoint without a server error for visitors, customers and staff', function () {
    Http::fake(); // provider probes/discovery must never reach a network from the test suite
    $this->withoutMiddleware(ThrottleRequests::class);

    [$owner, $org] = $this->customerWithOrganization();
    $staff = $this->staff('platform_owner');
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha 1', 'country' => 'CZ', 'datacenter' => 'PRG1', 'state' => 'active', 'meta' => []]);
    $ctx = $this->contextFor($owner, $org);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'label' => 'sweep-web', 'hostname' => 'sweep.example', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => ['domain' => 'sweep.example', 'php_version' => '8.3'], 'sla_class' => 'standard', 'activated_at' => now()->subDay(), 'tags' => [], 'health' => []]);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'sweep:q1', $ctx)['order'];
    $instance = app(ProviderInstanceService::class)->upsert(['key' => 'sweep-pve', 'provider' => 'proxmox', 'name' => 'Sweep PVE', 'region_code' => 'cz1', 'base_url' => 'https://pve.sweep.test:8006', 'credentials' => ['token_id' => 'root@pam!sweep', 'token_secret' => 'secret']], CommandContext::system('sweep'));

    $ids = [
        'service' => $service->id, 'order' => $order->id, 'invoice' => $order->invoice_id, 'intent' => $order->payment_intent_id,
        'organization' => $org->id, 'user' => $owner->id, 'instance' => $instance->key, 'product' => 'web-hosting', 'provider' => 'comgate',
    ];
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'v1/'))->values();
    expect($routes->count())->toBeGreaterThan(200);

    $problems = [];
    $calls = 0;
    foreach ($routes as $route) {
        $uri = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($ids[$m[1]] ?? 'missing'), $route->uri());
        foreach ($route->methods() as $method) {
            if ($method === 'HEAD' || $method === 'OPTIONS') {
                continue;
            }
            foreach (['visitor' => null, 'customer' => $owner, 'staff' => $staff] as $who => $user) {
                if ($user !== null) {
                    $this->actingAs($user, 'sanctum');
                }
                $response = $this->json($method, $uri, [], ['Idempotency-Key' => 'sweep-'.$who.'-'.md5($method.$uri), 'Accept' => 'application/json']);
                $calls++;
                $status = $response->getStatusCode();
                $body = (string) $response->getContent();
                $isJson = str_contains((string) $response->headers->get('Content-Type'), 'json');
                $upstream = $status === 502 && $isJson && str_starts_with((string) (json_decode($body, true)['error'] ?? ''), 'provider_'); // a vendor API failure rendered by contract
                if ($status >= 500 && ! $upstream) {
                    $problems[] = "{$method} {$uri} as {$who} → {$status} ".substr($body, 0, 160);
                } elseif ($status >= 400 && ! $isJson && ! str_contains($uri, '/pdf') && ! str_contains($uri, '/ubl') && ! str_contains($uri, '/export') && ! str_contains($uri, '/download')) {
                    $problems[] = "{$method} {$uri} as {$who} → {$status} without a JSON error body";
                } elseif ($status >= 400 && $isJson && $status !== 401 && $status !== 419 && ! isset(json_decode($body, true)['error'])) {
                    $problems[] = "{$method} {$uri} as {$who} → {$status} JSON without the `error` slug";
                }
                app('auth')->forgetGuards();
            }
        }
    }

    file_put_contents(storage_path('logs/endpoint-sweep.txt'), ($problems === [] ? 'no problems' : implode("\n", $problems))."\n{$calls} calls\n"); // full report for humans; the JSON test output truncates it
    expect($problems)->toBe([], "Endpoint sweep found problems:\n".implode("\n", $problems))->and($calls)->toBeGreaterThan(600);
});
