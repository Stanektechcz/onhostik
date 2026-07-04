<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V2;
use App\Http\Controllers\Webhook\ComgateWebhookController;
use App\Http\Controllers\Webhook\GopayWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// OpenAPI spec + Swagger UI — public, no auth
Route::get('/openapi.json', [V1\DocsController::class, 'spec'])->name('api.openapi');
Route::get('/docs',         [V1\DocsController::class, 'ui'])->name('api.docs');

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

/*
|--------------------------------------------------------------------------
| REST API v1 — Sanctum PAT
|--------------------------------------------------------------------------
| Customers create tokens in the panel at /panel/ucet/api-tokeny.
| Rate limit: 60 requests per minute per token.
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('v1')->name('api.v1.')->group(function (): void {
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

Route::middleware(['auth:sanctum', 'throttle:120,1'])->prefix('v2')->name('api.v2.')->group(function (): void {
    // Enhanced services with monitor data
    Route::get('/services',           [V2\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [V2\ServiceController::class, 'show'])->name('services.show');

    // Webhook subscriptions
    Route::get('/webhooks',                                [V2\WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks',                               [V2\WebhookController::class, 'store'])->name('webhooks.store');
    Route::delete('/webhooks/{webhook}',                   [V2\WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::get('/webhooks/{webhook}/deliveries',           [V2\WebhookController::class, 'deliveries'])->name('webhooks.deliveries');
});
