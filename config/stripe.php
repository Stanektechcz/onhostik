<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe payment gateway
    |--------------------------------------------------------------------------
    | api_key       — Stripe secret key (sk_live_... / sk_test_...)
    | public_key    — Stripe publishable key (pk_live_... / pk_test_...)
    | webhook_secret — Stripe webhook signing secret (whsec_...)
    |
    | Security: api_key and webhook_secret MUST NOT be logged or serialized.
    */
    'api_key'        => env('STRIPE_SECRET_KEY'),
    'public_key'     => env('STRIPE_PUBLIC_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'test_mode'      => env('STRIPE_TEST_MODE', true),
    'base_url'       => 'https://api.stripe.com/v1',

    'timeout'        => 30,
    'retry_attempts' => 3,
];
