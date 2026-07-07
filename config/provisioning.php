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

    // Hours between repeated quota-breach alerts for the same service.
    'quota_breach_cooldown_hours' => (int) env('QUOTA_BREACH_COOLDOWN_HOURS', 24),

    'wedos' => [
        'user'             => env('WAPI_USER'),
        'password'         => env('WAPI_PASSWORD'),
        'url'              => env('WAPI_URL', 'https://api.wedos.com/wapi/json'),
        'test_mode'        => env('WAPI_TEST_MODE', true),
        'timeout'          => env('WAPI_TIMEOUT', 30),
        'allow_real_writes' => env('WAPI_ALLOW_REAL_WRITES', false),
        // WEDOS limits: 1000 req/h total, 100 req/h domain-check/create/transfer-check
        'rate_limit_per_hour'         => 1000,
        'domain_check_limit_per_hour' => 100,
    ],

    'aapanel' => [
        'timeout'          => env('AAPANEL_TIMEOUT', 30),
        'verify_tls'       => env('AAPANEL_VERIFY_TLS', false),
        'allow_real_writes' => env('AAPANEL_ALLOW_REAL_WRITES', false),
    ],

    'proxmox' => [
        // Connection
        'host'             => env('PROXMOX_HOST'),                   // e.g. https://proxmox.example.com:8006
        'node'             => env('PROXMOX_NODE', 'pve'),            // Proxmox node name
        // API token auth (root@pam!mytoken=uuid) — preferred over ticket auth
        'api_user'         => env('PROXMOX_API_USER', 'root@pam'),
        'api_token_id'     => env('PROXMOX_API_TOKEN_ID'),           // token name (part before =)
        'api_token_secret' => env('PROXMOX_API_TOKEN_SECRET'),       // token UUID
        // VM defaults
        'template_vmid'    => env('PROXMOX_TEMPLATE_VMID', 9000),    // VMID of the cloud-init template
        'storage'          => env('PROXMOX_STORAGE', 'local-lvm'),   // storage for cloned disks
        'network_bridge'   => env('PROXMOX_BRIDGE', 'vmbr0'),        // network bridge
        'nameservers'      => env('PROXMOX_NAMESERVERS', '1.1.1.1 8.8.8.8'),
        // Request settings
        'timeout'          => env('PROXMOX_TIMEOUT', 60),
        'verify_tls'       => env('PROXMOX_VERIFY_TLS', true),
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
