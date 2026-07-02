<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Webhook\ComgateWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API / webhooks
|--------------------------------------------------------------------------
| Webhooks are exempt from CSRF (api middleware group). Signature/IP
| verification happens inside the processing layer, never skipped.
*/

Route::post('/webhooks/comgate', ComgateWebhookController::class)->name('webhooks.comgate');

/*
|--------------------------------------------------------------------------
| REST API v1 — Sanctum PAT (read-only)
|--------------------------------------------------------------------------
| Customers create tokens in the panel at /panel/ucet/api-tokeny.
| Rate limit: 60 requests per minute per token.
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/profile',  [V1\ProfileController::class, 'show'])->name('profile');

    Route::get('/services',        [V1\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [V1\ServiceController::class, 'show'])->name('services.show');

    Route::get('/invoices', [V1\InvoiceController::class, 'index'])->name('invoices.index');

    Route::post('/tokens',           [V1\TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/tokens/{token}', [V1\TokenController::class, 'destroy'])->name('tokens.destroy');
});
