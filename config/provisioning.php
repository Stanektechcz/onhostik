<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Global mock mode
    |--------------------------------------------------------------------------
    | When true, ALL drivers resolve to their Mock implementation regardless
    | of per-server settings. Local/dev default. NEVER true in production.
    */
    'mock_mode' => env('PROVISIONING_MOCK_MODE', true),

    'wedos' => [
        'user'        => env('WAPI_USER'),
        'password'    => env('WAPI_PASSWORD'),
        'url'         => env('WAPI_URL', 'https://api.wedos.com/wapi/json'),
        'test_mode'   => env('WAPI_TEST_MODE', true),
        'timeout'     => env('WAPI_TIMEOUT', 30),
        // WEDOS limits: 1000 req/h total, 100 req/h domain-check/create/transfer-check
        'rate_limit_per_hour'        => 1000,
        'domain_check_limit_per_hour' => 100,
    ],

    'aapanel' => [
        'timeout' => env('AAPANEL_TIMEOUT', 30),
    ],

    'proxmox' => [
        'timeout'    => env('PROXMOX_TIMEOUT', 60),
        'verify_tls' => env('PROXMOX_VERIFY_TLS', true),
    ],

    'pterodactyl' => [
        'timeout' => env('PTERODACTYL_TIMEOUT', 30),
    ],

    'queues' => [
        'high'    => 'provisioning-high',   // payment webhooks → activation
        'default' => 'provisioning',
        'low'     => 'provisioning-low',    // status sync, cleanup
    ],

    /** Days a terminated service's data is retained before remote deletion. */
    'retention_days' => env('PROVISIONING_RETENTION_DAYS', 30),
];
