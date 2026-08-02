<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention policy (audit INFRA #180)
    |--------------------------------------------------------------------------
    |
    | The `retention:apply` command prunes rows older than the window below.
    | Set any window to 0 to keep that table forever (the previous behaviour).
    |
    | These are operational/telemetry tables, NOT business records — invoices,
    | orders, payments, consent records and credit ledgers are deliberately
    | never pruned here (they are legal/financial records with their own
    | statutory retention).
    |
    */
    'policies' => [
        // Spatie activity log — high-volume audit trail. A year is generous.
        'activity_log' => [
            'table'  => 'activity_log',
            'column' => 'created_at',
            'days'   => (int) env('RETENTION_ACTIVITY_LOG_DAYS', 365),
        ],

        // Per-login history rows used for new-IP detection.
        'login_history' => [
            'table'  => 'user_login_history',
            'column' => 'created_at',
            'days'   => (int) env('RETENTION_LOGIN_HISTORY_DAYS', 180),
        ],

        // Generated export archives (invoice ZIPs etc.) — the file itself is
        // pruned by its own expiry; this clears the long-dead metadata rows.
        'export_jobs' => [
            'table'  => 'export_jobs',
            'column' => 'expires_at',
            'days'   => (int) env('RETENTION_EXPORT_JOBS_DAYS', 30),
        ],

        // Resolved service-health incidents — keep open ones untouched.
        'resolved_incidents' => [
            'table'  => 'service_health_incidents',
            'column' => 'resolved_at',
            'days'   => (int) env('RETENTION_RESOLVED_INCIDENTS_DAYS', 180),
            'where'  => ['status' => 'resolved'],
        ],

        // High-growth API telemetry — analytics keep aggregates, raw rows expire.
        'api_usage_logs' => [
            'table'  => 'api_usage_logs',
            'column' => 'created_at',
            'days'   => (int) env('RETENTION_API_USAGE_DAYS', 90),
        ],
    ],

];
