<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V2;
use App\Http\Controllers\Webhook\ComgateWebhookController;
use App\Http\Controllers\Webhook\GopayWebhookController;
use App\Http\Controllers\Webhook\InboundWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
 | Health check (audit J142) — for load balancers and external monitoring.
 |
 | Deliberately reports the database as well: a process that is "up" but
 | cannot reach its database should be taken out of rotation, not counted
 | healthy. Returns 503 in that case so a monitor actually notices.
 |
 | No auth: a health probe that needs credentials is one more thing to break
 | at 3am. It exposes nothing beyond up/down and a version string.
 */
Route::get('/up', function () {
    $checks = ['app' => true];
    $healthy = true;

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Throwable) {
        $checks['database'] = false;
        $healthy = false;
    }

    return response()->json([
        'status'  => $healthy ? 'ok' : 'degraded',
        'checks'  => $checks,
        'time'    => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
})->name('api.health');

/*
 | Readiness probe — deeper than /up (liveness). Round-trips DB, cache, queue and
 | storage so a load balancer / k8s readiness gate only sends traffic once the
 | app can actually serve it. Public + unauthenticated; leaks no error detail.
 */
Route::get('/ready', function (\App\Domains\Monitoring\Services\HealthChecker $health) {
    $result = $health->readiness();

    return response()->json(
        $result + ['time' => now()->toIso8601String()],
        $result['status'] === 'ready' ? 200 : 503,
    );
})->name('api.ready');

/*
 | Prometheus metrics — operational gauges for scraping. Self-gated: 404 until
 | METRICS_TOKEN is set, then token-authenticated (Bearer or ?token=). Not part
 | of the customer API surface (see the OpenAPI coverage exclusion).
 */
Route::get('/metrics', \App\Http\Controllers\Api\MetricsController::class)
    ->middleware('throttle:60,1')
    ->name('api.metrics');

// OpenAPI spec + Swagger UI — public, no auth
Route::get('/openapi.json', [V1\DocsController::class, 'spec'])->name('api.openapi');
Route::get('/docs',         [V1\DocsController::class, 'ui'])->name('api.docs');

// API changelog + version lifecycle (audit 103) — public, no auth
Route::get('/changelog', \App\Http\Controllers\Api\ChangelogController::class)->name('api.changelog');

/*
|--------------------------------------------------------------------------
| API / webhooks
|--------------------------------------------------------------------------
| Webhooks are exempt from CSRF (api middleware group). Signature/IP
| verification happens inside the processing layer, never skipped.
*/

Route::post('/webhooks/comgate', ComgateWebhookController::class)->name('webhooks.comgate');
Route::post('/webhooks/stripe',  StripeWebhookController::class)->name('webhooks.stripe');
Route::post('/webhooks/gopay',   GopayWebhookController::class)->name('webhooks.gopay');

// Generic inbound webhook bus — source is matched against webhook_endpoints table
Route::post('/webhook/{source}', [InboundWebhookController::class, 'receive'])->name('webhooks.inbound');

/*
 | CSP violation reports (audit C24).
 |
 | Unauthenticated by necessity — a browser posts these without credentials,
 | and a violation on a logged-out page still matters. Throttled because the
 | endpoint is world-writable; the controller caps body size and truncates
 | every field before logging.
 */
Route::post('/security/csp-report', \App\Http\Controllers\Security\CspReportController::class)
    ->middleware('throttle:60,1')
    ->name('security.csp-report');

/*
|--------------------------------------------------------------------------
| REST API v1 — Sanctum PAT
|--------------------------------------------------------------------------
| Customers create tokens in the panel at /panel/ucet/api-tokeny.
| Rate limit: 60 requests per minute per token.
*/

Route::middleware(['auth:sanctum', 'throttle:api', 'log-api-usage', 'idempotency', 'api-lifecycle:v1'])->prefix('v1')->name('api.v1.')->group(function (): void {
    // Profile
    Route::get('/profile', [V1\ProfileController::class, 'show'])->name('profile');

    // Services
    Route::get('/services',           [V1\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [V1\ServiceController::class, 'show'])->name('services.show');

    // Invoices
    Route::get('/invoices', [V1\InvoiceController::class, 'index'])->name('invoices.index');

    // Domains
    Route::get('/domains', [V1\DomainController::class, 'index'])->name('domains.index');

    // Credit balance + top-up
    Route::get('/billing/credit',        [V1\CreditController::class, 'balance'])->name('billing.credit');
    Route::post('/billing/credit/topup', [V1\CreditController::class, 'topup'])->name('billing.credit.topup');

    // Support tickets (full CRUD)
    Route::get('/support/tickets',                 [V1\SupportTicketController::class, 'index'])->name('support.tickets.index');
    Route::post('/support/tickets',                [V1\SupportTicketController::class, 'store'])->name('support.tickets.store');
    Route::get('/support/tickets/{ticket}',        [V1\SupportTicketController::class, 'show'])->name('support.tickets.show');
    Route::post('/support/tickets/{ticket}/reply', [V1\SupportTicketController::class, 'reply'])->name('support.tickets.reply');
    Route::post('/support/tickets/{ticket}/close', [V1\SupportTicketController::class, 'close'])->name('support.tickets.close');

    // Monitors for the customer's services
    Route::get('/monitors', [V1\MonitorController::class, 'index'])->name('monitors.index');

    // API tokens
    Route::post('/tokens',           [V1\TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/tokens/{token}', [V1\TokenController::class, 'destroy'])->name('tokens.destroy');
});

/*
|--------------------------------------------------------------------------
| REST API v2 — Sanctum PAT (enhanced)
|--------------------------------------------------------------------------
| Same Sanctum authentication as v1 but with:
|  - 2× rate limit (120 req/min)
|  - Services with embedded monitor data
|  - Customer-facing webhook subscription management
*/

Route::middleware(['auth:sanctum', 'throttle:api', 'log-api-usage', 'idempotency', 'api-lifecycle:v2'])->prefix('v2')->name('api.v2.')->group(function (): void {
    // Enhanced services with monitor data
    Route::get('/services',           [V2\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [V2\ServiceController::class, 'show'])->name('services.show');

    // Cursor-paginated collections (audit 500 #202/#203): ?sort=-created_at
    // &filter[status]=paid&fields=id,total&per_page=50&cursor=…
    Route::get('/invoices',           [V2\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [V2\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/orders',             [V2\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}',     [V2\OrderController::class, 'show'])->name('orders.show');
    Route::get('/domains',            [V2\DomainController::class, 'index'])->name('domains.index');
    Route::get('/support/tickets',    [V2\SupportTicketController::class, 'index'])->name('support.tickets.index');

    // Webhook subscriptions
    Route::get('/webhooks',                                [V2\WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks',                               [V2\WebhookController::class, 'store'])->name('webhooks.store');
    Route::delete('/webhooks/{webhook}',                   [V2\WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::get('/webhooks/{webhook}/deliveries',           [V2\WebhookController::class, 'deliveries'])->name('webhooks.deliveries');
});

/*
|--------------------------------------------------------------------------
| GraphQL — read-only, same Sanctum token as the REST API
|--------------------------------------------------------------------------
| A single POST endpoint over App\GraphQL\ApiSchema. Every resolver is scoped
| to the authenticated user's customer. Query-only by design; writes stay on
| the REST API where idempotency and per-token abilities already live.
*/
Route::post('/graphql', \App\Http\Controllers\Api\GraphQLController::class)
    ->middleware(['auth:sanctum', 'throttle:api', 'log-api-usage'])
    ->name('api.graphql');
