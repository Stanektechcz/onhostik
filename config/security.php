<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy enforcement
    |--------------------------------------------------------------------------
    |
    | SecurityHeaders read `config('app.security_csp_enforce')`, but that key
    | was never defined in any config file — and config() reads config files,
    | not the environment. So SECURITY_CSP_ENFORCE=true in .env did nothing:
    | the policy has only ever been sent as Content-Security-Policy-Report-Only,
    | and every finding about "what breaks under enforce" was theoretical.
    |
    | Defined here so the switch is real. It stays OFF by default — turning it
    | on is an operator decision to make deliberately, after checking the
    | report-only violation reports for the deployment in question.
    |
    */
    'csp_enforce' => env('SECURITY_CSP_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Known IP retention
    |--------------------------------------------------------------------------
    |
    | How long a successful login from an address keeps that address familiar,
    | for the purpose of the "sign-in from a new IP" alert. Longer means fewer
    | alerts; shorter means an attacker's address goes stale sooner and gets
    | flagged again.
    |
    */
    'known_ip_retention_days' => (int) env('SECURITY_KNOWN_IP_RETENTION_DAYS', 90),

    // Contact published in /.well-known/security.txt (RFC 9116).
    'disclosure_email' => env('SECURITY_DISCLOSURE_EMAIL', 'security@onhost.cz'),

    /*
    |--------------------------------------------------------------------------
    | PII blind-index key (audit 31)
    |--------------------------------------------------------------------------
    | Keyed HMAC secret for the sidecar index columns that make encrypted PII
    | (e.g. customer phone) searchable by exact match. Defaults to a derivation
    | of APP_KEY; set explicitly to rotate independently. Changing it requires
    | re-indexing (php artisan pii:reindex).
    */
    'pii_index_key' => env('SECURITY_PII_INDEX_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | CSP violation reporting (audit C24)
    |--------------------------------------------------------------------------
    |
    | Adds `report-uri` / `report-to` to the policy so violations land in the
    | log. This is what makes report-only mode useful: you learn what enforce
    | would break BEFORE flipping `csp_enforce`. Turn off only if an external
    | collector takes over.
    |
    */
    'csp_report_enabled' => env('SECURITY_CSP_REPORT_ENABLED', true),

];
