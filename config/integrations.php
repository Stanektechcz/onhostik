<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integration provider catalog
|--------------------------------------------------------------------------
| Drives the admin Integrations screens, the IntegrationSeeder and the
| ConnectionTester. `fields` lists the credential keys stored ENCRYPTED in
| integration_settings.credentials — values are never displayed back.
| Placeholders ship inactive + mock; real activation is documented in
| docs/integrations.md and always requires explicit env approval flags.
*/

return [

    'providers' => [
        'aapanel' => [
            'label'    => 'aaPanel (webhosting)',
            'category' => 'provisioning',
            'fields'   => ['base_url', 'api_key'],
        ],
        'wedos' => [
            'label'    => 'WEDOS WAPI (domény)',
            'category' => 'domains',
            'fields'   => ['user', 'password'],
        ],
        'comgate' => [
            'label'    => 'Comgate (platby)',
            'category' => 'payments',
            'fields'   => ['merchant_id', 'secret'],
        ],
        'gopay' => [
            'label'    => 'GoPay (platby) — placeholder',
            'category' => 'payments',
            'fields'   => ['goid', 'client_id', 'client_secret'],
        ],
        'stripe' => [
            'label'    => 'Stripe (platby) — placeholder',
            'category' => 'payments',
            'fields'   => ['publishable_key', 'secret_key'],
        ],
        'uptime_kuma' => [
            'label'    => 'Uptime Kuma (monitoring) — placeholder',
            'category' => 'monitoring',
            'fields'   => ['base_url', 'api_key'],
        ],
        'internal_monitoring' => [
            'label'    => 'Interní monitoring (mock)',
            'category' => 'monitoring',
            'fields'   => [],
        ],
        's3_backups' => [
            'label'    => 'S3-kompatibilní úložiště záloh — placeholder',
            'category' => 'backups',
            'fields'   => ['endpoint', 'bucket', 'access_key', 'secret_key'],
        ],
        'backblaze_b2' => [
            'label'    => 'Backblaze B2 (zálohy) — placeholder',
            'category' => 'backups',
            'fields'   => ['key_id', 'application_key', 'bucket'],
        ],
        'smtp' => [
            'label'    => 'SMTP / transakční e-mail',
            'category' => 'email',
            'fields'   => ['host', 'port', 'username', 'password'],
        ],
        'ai_mock' => [
            'label'    => 'AI asistent (mock)',
            'category' => 'ai',
            'fields'   => [],
        ],
        'claude' => [
            'label'    => 'Anthropic Claude — placeholder',
            'category' => 'ai',
            'fields'   => ['api_key'],
        ],
        'openai' => [
            'label'    => 'OpenAI — placeholder',
            'category' => 'ai',
            'fields'   => ['api_key'],
        ],
        'n8n' => [
            'label'    => 'n8n automatizace — placeholder',
            'category' => 'automation',
            'fields'   => ['base_url', 'api_key'],
        ],
        'cloudflare' => [
            'label'    => 'Cloudflare (DNS/CDN) — placeholder',
            'category' => 'dns',
            'fields'   => ['api_token'],
        ],
        'sitepro' => [
            'label'    => 'Site.pro website builder — placeholder',
            'category' => 'builder',
            'fields'   => ['api_url', 'api_key'],
        ],
        'softaculous' => [
            'label'    => 'Softaculous/Installatron — placeholder',
            'category' => 'builder',
            'fields'   => ['base_url', 'api_key'],
        ],
        'pterodactyl' => [
            'label'    => 'Pterodactyl Panel (game servery)',
            'category' => 'provisioning',
            'fields'   => ['base_url', 'application_api_key', 'client_api_key'],
        ],
        'proxmox' => [
            'label'    => 'Proxmox VE (VPS / Cloud)',
            'category' => 'provisioning',
            'fields'   => ['base_url', 'username', 'password', 'realm', 'node'],
        ],
    ],

    /*
    | Hard env-level approval gates for REAL write operations. These are
    | intentionally separate from the DB flags: flipping a checkbox in the
    | admin UI is never enough to let the platform touch real backends.
    */
    'real_write_gates' => [
        'aapanel' => env('AAPANEL_ALLOW_REAL_WRITES', false),
        'wedos'   => env('WAPI_ALLOW_REAL_WRITES', false),
        'ai'      => env('AI_ALLOW_REAL_CALLS', false),
    ],
];
