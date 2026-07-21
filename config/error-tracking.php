<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Error tracking (audit J141)
|--------------------------------------------------------------------------
|
| STATUS: no third-party tracker is installed. This is a decision, not an
| oversight, and it is recorded here so nobody has to guess.
|
| Sentry (sentry/sentry-laravel) would mean:
|   * a new composer dependency,
|   * an account and a DSN, which is a credential the operator owns,
|   * exception data — including whatever context is attached — leaving the
|     server and being retained by a third party.
|
| That last point is why the groundwork came first. ErrorContext deliberately
| carries request id / route / user id and nothing else, and everything it
| does carry passes through SecretRedactor. Wiring a tracker onto unfiltered
| context would ship credentials off-site on the very first exception.
|
| TO ENABLE:
|   1. composer require sentry/sentry-laravel
|   2. Put the DSN in .env as SENTRY_LARAVEL_DSN (never commit it).
|   3. In bootstrap/app.php, inside withExceptions():
|        Integration::handles($exceptions);
|   4. Leave `traces_sample_rate` low to start; full tracing on a hosting
|      panel is a lot of volume for little added signal.
|
| Until then errors go to the log stack with the context below, and failed
| queue jobs additionally notify an admin (see NotifyAdminOnFailedJob).
|
*/

return [

    /*
     | Whether a tracker is expected to be active. Kept false so that the
     | health/diagnostics surface reports "no tracker" honestly rather than
     | implying errors are being captured somewhere they are not.
     */
    'enabled' => env('ERROR_TRACKING_ENABLED', false),

    /*
     | Sampling for the eventual tracker. Recorded now so the decision is made
     | once, in the open, rather than hurriedly at install time.
     */
    'traces_sample_rate' => (float) env('ERROR_TRACKING_TRACES_SAMPLE_RATE', 0.05),

    /*
     | Environments that may report outward at all. Local and testing must
     | never ship data off the machine.
     */
    'environments' => ['production', 'staging'],

];
