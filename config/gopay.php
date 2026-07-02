<?php

declare(strict_types=1);

return [
    'client_id'     => env('GOPAY_CLIENT_ID'),
    'client_secret' => env('GOPAY_CLIENT_SECRET'),
    'go_id'         => env('GOPAY_GO_ID'),
    'base_url'      => env('GOPAY_BASE_URL', 'https://gw.sandbox.gopay.com/api'),

    'timeout' => 30,
];
