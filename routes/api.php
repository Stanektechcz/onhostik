<?php

declare(strict_types=1);

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
