<?php

declare(strict_types=1);

return [
    'merchant_id' => env('COMGATE_MERCHANT_ID'),
    'secret'      => env('COMGATE_SECRET'),
    'test_mode'   => env('COMGATE_TEST_MODE', true),
    'base_url'    => env('COMGATE_BASE_URL', 'https://payments.comgate.cz/v1.0'),

    // Comgate notification source IPs — verify against current docs before prod.
    'webhook_ip_whitelist' => array_filter(explode(',', (string) env('COMGATE_IP_WHITELIST', ''))),

    'timeout'        => 30,
    'retry_attempts' => 3,
];
