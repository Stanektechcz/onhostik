<?php

return [
    'url'       => env('UPTIME_KUMA_URL', ''),
    'api_token' => env('UPTIME_KUMA_API_TOKEN', ''),
    'timeout'   => 15,
    /*
     * Default check interval in seconds sent to UptimeKuma when creating monitors.
     * Minimum recommended: 60 (1 minute).
     */
    'interval'  => (int) env('UPTIME_KUMA_INTERVAL', 60),
];
