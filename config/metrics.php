<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Prometheus metrics endpoint
    |--------------------------------------------------------------------------
    |
    | /api/metrics exposes operational gauges in Prometheus exposition format
    | for scraping. It is DISABLED (404) until a token is set — metrics can leak
    | operational detail, so exposure is opt-in. When set, the scraper must send
    | the token as a Bearer header or ?token=.
    |
    */

    'token' => (string) env('METRICS_TOKEN', ''),

];
