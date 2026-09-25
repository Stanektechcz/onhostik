<?php

declare(strict_types=1);
use Onhost\Providers\Subreg\SubregPublicPriceList;
use Onhost\Providers\Wedos\WedosPublicPriceList;

/**
 * ONhost Control Plane configuration. Values here are defaults; every secret is a
 * reference into the secret store, never a value (blueprint §6.1, §19).
 */
return [
    'brand' => 'ONhost',
    'version' => '4.0', // reported by /health, the OpenAPI document, traces and error reports
    'portal_url' => env('ONHOST_PORTAL_URL', env('APP_URL', 'http://localhost:8000')),
    'default_locale' => env('ONHOST_DEFAULT_LOCALE', 'cs'),
    'locales' => ['cs', 'sk', 'en'],
    'timezone_display' => 'Europe/Prague',
    'demo_mode' => (bool) env('ONHOST_DEMO_MODE', false), // surface role switcher etc. — never in production

    // Web hosting tools on top of the panels: CDN/WAF in front of sites (Cloudflare), platform-issued certificates, uptime checks, deploy and imports
    'cdn' => [
        'cloudflare' => [
            'secret_ref' => env('ONHOST_CDN_CLOUDFLARE_SECRET_REF'), // e.g. env://CLOUDFLARE → CLOUDFLARE_API_TOKEN (Zone:Edit, DNS:Edit, Zone Settings:Edit) + CLOUDFLARE_ACCOUNT_ID
            'base_url' => env('CLOUDFLARE_API_BASE', 'https://api.cloudflare.com/client/v4'),
        ],
    ],
    'acme' => [
        'directory' => env('ONHOST_ACME_DIRECTORY', 'https://acme-v02.api.letsencrypt.org/directory'),
        'contact' => env('ONHOST_ACME_CONTACT'), // mailto: address registered with the ACME account
        'renew_days_before' => (int) env('ONHOST_ACME_RENEW_DAYS', 30),
        // below this many days left the panel has demonstrably not renewed a site's certificate and the platform asks
        // for one itself (CertificateWatch); above it the panel is still renewing and asking too would only spend the
        // authority's duplicate-certificate allowance
        'watch_renew_days' => (int) env('ONHOST_CERT_WATCH_RENEW_DAYS', 10),
    ],
    'egress' => [ // destinations a CUSTOMER names (uptime checks, webhooks, import URLs): public addresses only (EgressGuard)
        'node_service_ports' => [22, 25, 80, 443, 2022, 3306, 5432, 6379, 8006, 8080, 8081, 8443, 8888, 9000, 11211, 27017], // loopback ports of a node a customer's reverse proxy may not point at (panels, databases, caches)
        'deny_cidrs' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONHOST_EGRESS_DENY_CIDRS', ''))))),   // the operator's own public management ranges
        'allow_cidrs' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONHOST_EGRESS_ALLOW_CIDRS', ''))))), // a lab on private addresses; empty in production
    ],
    'mail_health' => ['window_minutes' => (int) env('ONHOST_MAIL_HEALTH_WINDOW', 30), 'min_errors' => (int) env('ONHOST_MAIL_HEALTH_MIN_ERRORS', 3), 'stalled_minutes' => (int) env('ONHOST_MAIL_HEALTH_STALLED', 15)], // does the platform's own mail still leave (H24)
    'monitoring' => [
        'user_agent' => 'ONhost-Uptime/1.0 (+https://onhost.cz/stav)',
        'failures_before_down' => (int) env('ONHOST_MONITORING_FAILURES', 3),
        'power_passes_before_down' => (int) env('ONHOST_MONITORING_POWER_PASSES', 2), // reconciler readings of a stopped VPS / game server before the alarm (H14)
        'retention_days' => (int) env('ONHOST_MONITORING_RETENTION_DAYS', 30),
    ],
    'web_tools' => [
        'upload_max_bytes' => (int) env('ONHOST_UPLOAD_MAX_BYTES', 2147483648),
        'download_ttl_minutes' => 30,
        'wp_cli_url' => 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar',
        'staging_suffix' => env('ONHOST_STAGING_SUFFIX', 'web.onhost.cz'),
        // how long a site may hold a host name that nothing proves is its customer's before the operators hear (SiteClaim)
        'claim_grace_days' => (int) env('ONHOST_SITE_CLAIM_GRACE_DAYS', 30),
    ],
    'platform_backup' => [ // backups of the control plane itself: database dump + private file store (go-live checklist §1)
        'disk' => env('ONHOST_PLATFORM_BACKUP_DISK', 'local'),      // a filesystems.disks entry; S3-compatible and off the server in production
        'retention_days' => (int) env('ONHOST_PLATFORM_BACKUP_RETENTION_DAYS', 30),
        'files_root' => env('ONHOST_PLATFORM_BACKUP_FILES_ROOT'),   // default storage/app/private
        'service_archive_days' => (int) env('ONHOST_SERVICE_ARCHIVE_DAYS', 60), // a terminated service is archived (files, databases, metadata) and kept this long — audit §5aa
        'game_archive_timeout' => (int) env('ONHOST_GAME_ARCHIVE_TIMEOUT', 1800),
        'download_timeout' => (int) env('ONHOST_ARCHIVE_DOWNLOAD_TIMEOUT', 900),
        'stale_backup_hours' => (int) env('ONHOST_STALE_BACKUP_HOURS', 48),
        'archive_verify_days' => (int) env('ONHOST_ARCHIVE_VERIFY_DAYS', 14),   // how often a stored service archive is re-hashed against its manifest
        'archive_verify_batch' => (int) env('ONHOST_ARCHIVE_VERIFY_BATCH', 3),   // archives re-hashed per onhost:backups:run pass // how old the panel's own backup may be when it is the only way to archive the files
        'pg_bin' => env('ONHOST_PG_BIN', ''),                       // directory with pg_dump/pg_restore matching the server version (aaPanel: /www/server/pgsql/bin); empty = auto-detect
    ],
    // the deletion lifecycle (audit §5ab); staff override every number in "Nastavení systému → Životní cyklus služeb"
    'services' => [
        'deletion' => [
            'grace_days' => (int) env('ONHOST_DELETE_GRACE_DAYS', 30),          // the customer may bring a deactivated service back within this window
            'identity_checks' => (int) env('ONHOST_DELETE_IDENTITY_CHECKS', 5), // identifiers that must match before anything is deleted
            'download_fee_minor' => ['CZK' => (int) env('ONHOST_ARCHIVE_DOWNLOAD_FEE_CZK', 50000), 'EUR' => (int) env('ONHOST_ARCHIVE_DOWNLOAD_FEE_EUR', 2000)],
        ],
    ],
    // a rescue session boots the server from an image of the operator's ISO storage and ends by itself (H233)
    'rescue' => [
        'hours' => (int) env('ONHOST_RESCUE_HOURS', 8),
    ],

    'backups' => [
        'offsite_disk' => env('ONHOST_BACKUP_OFFSITE_DISK'), // a filesystems.disks entry (S3-compatible) for off-site copies; null = off
        'daily_hour' => (int) env('ONHOST_BACKUP_DAILY_HOUR', 2),
        'coverage_days' => (int) env('ONHOST_BACKUP_COVERAGE_DAYS', 3), // the doctor names web services without a finished backup in this many days
        'manual_max' => (int) env('ONHOST_BACKUP_MANUAL_MAX', 5), // backups a customer may keep per web service at once (they live on the platform's backup disk); the plan's `manual_backups` entitlement overrides it
    ],
    // Discord: one platform application; customers link their Discord account to their organization and drive services with /onhost
    'discord' => [
        'application_id' => env('ONHOST_DISCORD_APPLICATION_ID'),
        'public_key' => env('ONHOST_DISCORD_PUBLIC_KEY'),             // interaction signatures (Ed25519) from the developer portal
        'bot_token_ref' => env('ONHOST_DISCORD_BOT_SECRET_REF', 'env://DISCORD_BOT'), // secret with `token` (registers slash commands)
        'base_url' => env('DISCORD_API_BASE', 'https://discord.com/api/v10'),
    ],
    'secrets' => [
        'driver' => env('ONHOST_SECRETS_DRIVER', 'env'), // env | openbao
        'openbao' => [
            'address' => env('OPENBAO_ADDR', 'https://bao.mgmt.onhost.internal:8200'),
            'token' => env('OPENBAO_TOKEN'),
            'role_id' => env('OPENBAO_ROLE_ID'),
            'secret_id' => env('OPENBAO_SECRET_ID'),
            'namespace' => env('OPENBAO_NAMESPACE', ''),
            'ca_cert' => env('OPENBAO_CACERT'),
        ],
    ],

    'identity' => [
        'step_up_ttl_minutes' => (int) env('ONHOST_STEP_UP_TTL', 10),
        'session_ttl_minutes' => (int) env('ONHOST_SESSION_TTL', 720),
        'staff_mfa_required' => (bool) env('ONHOST_STAFF_MFA_REQUIRED', true),
        'max_failed_logins' => 8,
        'lockout_minutes' => 15,
        'trusted_device_days' => 30,
        'jit_default_ttl_minutes' => 60,
        'jit_max_ttl_minutes' => 480,
        'approval_ttl_hours' => (int) env('ONHOST_APPROVAL_TTL_HOURS', 24),
        // critical staff actions take a second person (ApprovalService). Off = one operator runs the platform alone: set on the server, never from the application
        'four_eyes' => (bool) env('ONHOST_FOUR_EYES', true),
        // ── TASK-0021 (owner decision 14): a password change revokes the person's personal API tokens, a reset always does. Off = the old "tokens kept" path and its mail
        'password_change_revokes_api_access' => (bool) env('ONHOST_PASSWORD_CHANGE_REVOKES_API_ACCESS', true),
        'oidc' => [
            'enabled' => (bool) env('OIDC_ENABLED', false),
            'issuer' => env('OIDC_ISSUER', 'https://id.onhost.cz/realms/onhost'),
            'client_id' => env('OIDC_CLIENT_ID', 'onhost-portal'),
            'client_secret_ref' => env('OIDC_CLIENT_SECRET_REF', 'env://OIDC_CLIENT'),
            'scopes' => ['openid', 'profile', 'email'],
            'staff_realm_role' => 'onhost-staff',
        ],
    ],

    'api' => [
        'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'onh_live_'),
        'default_rate_limit_per_minute' => 120,
        'public_rate_limit_per_minute' => 600,
        'idempotency_ttl_hours' => 24,
        'page_size' => 40,
        'max_page_size' => 200,
    ],

    'legal_entity' => [ // the operator's own company for documents (LegalEntitySeeder / onhost:production:prepare --legal); config, not env(), so a cached configuration still carries it
        'name' => env('ONHOST_LEGAL_NAME', ''), 'ico' => env('ONHOST_ICO', ''), 'dic' => env('ONHOST_DIC', ''), 'vat_id' => env('ONHOST_VAT_ID', ''),
        'street' => env('ONHOST_STREET', ''), 'city' => env('ONHOST_CITY', ''), 'zip' => env('ONHOST_ZIP', ''),
        'iban' => env('ONHOST_BANK_IBAN', ''), 'bic' => env('ONHOST_BANK_BIC', ''), 'bank_account' => env('ONHOST_BANK_ACCOUNT', ''),
    ],

    'support' => [ // paid work on a ticket (H29): offered with a price, billed only after the customer approved it
        'work_offer' => ['valid_days' => (int) env('ONHOST_WORK_OFFER_VALID_DAYS', 14), 'max_net' => (string) env('ONHOST_WORK_OFFER_MAX_NET', '250000')],
    ],

    'billing' => [
        'timezone' => env('ONHOST_BILLING_TIMEZONE', 'Europe/Prague'), // the day a document belongs to is the day at the seller's seat (AccountingClock), whatever zone the servers run in
        'currencies' => ['CZK', 'EUR'],
        'default_currency' => 'CZK',
        'min_topup' => ['CZK' => '100', 'EUR' => '5'],
        'legal_entity' => env('ONHOST_LEGAL_ENTITY', 'onhost-cz'),
        'invoice_due_days' => 14,
        'manual_credit_max' => 100000, // the most staff may credit to a wallet in one step, in major units (OrdersCommandHandler)
        'renew_lead_days' => (int) env('ONHOST_SERVICE_RENEW_LEAD_DAYS', 7),
        'refund_approval_threshold' => ['CZK' => 2000000, 'EUR' => 80000], // minor units: 20 000 Kč / 800 €
        'adjustment_step_up_threshold' => ['CZK' => 100000, 'EUR' => 4000],
        'auto_topup' => ['max_per_day' => 2, 'max_monthly' => ['CZK' => 1000000, 'EUR' => 40000]],
        'dunning' => [
            'overdue_notice_days' => [3, 7, 14],
            'grace_days' => 14,
            'suspend_after_days' => (int) env('ONHOST_DUNNING_SUSPEND_DAYS', 30),
            'terminate_after_days' => 60,
            'retention_after_termination_days' => 30,
        ],
        'domain_renewal_reserve_days' => 30,
        'vies_endpoint' => env('VIES_ENDPOINT', 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number'),
        // a tax document in another currency states its VAT in CZK at the rate of the Czech National Bank (CnbRates, CzkTaxStatement)
        'fx' => [
            'cnb_url' => env('ONHOST_CNB_RATES_URL', 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt'),
            'fetch' => (bool) env('ONHOST_FX_FETCH', true), // ask the bank while a document is being issued when the day's list is not stored yet; off = the scheduled sync only
            'timeout_seconds' => (int) env('ONHOST_FX_TIMEOUT', 5),
            'max_age_days' => (int) env('ONHOST_FX_MAX_AGE_DAYS', 7), // an older list is not a rate of the day: the document waits for the bank
        ],
    ],

    'payments' => [
        'default' => env('PAYMENT_GATEWAY', 'comgate'),
        'comgate' => [
            'merchant' => env('COMGATE_MERCHANT'),
            'secret_ref' => env('COMGATE_SECRET_REF', 'env://COMGATE'),
            'test' => (bool) env('COMGATE_TEST', true),
            'base_url' => env('COMGATE_BASE_URL', 'https://payments.comgate.cz/v2.0'),
            'callback_allowlist' => array_filter(explode(',', (string) env('COMGATE_CALLBACK_IPS', ''))),
            'recurring' => (bool) env('COMGATE_RECURRING', true), // stored cards for automatic top-ups (initRecurring / initRecurringId); switch off if the merchant contract has no recurring payments

        ],
        'gopay' => [
            'goid' => env('GOPAY_GOID'),
            'secret_ref' => env('GOPAY_SECRET_REF', 'env://GOPAY'),
            'base_url' => env('GOPAY_BASE_URL', 'https://gw.sandbox.gopay.com/api'),
            'recurring' => (bool) env('GOPAY_RECURRING', true), // stored cards for automatic top-ups (ON_DEMAND recurrence / create-recurrence); needs recurring payments enabled on the GoPay account
        ],
        'stripe' => [
            'secret_ref' => env('STRIPE_SECRET_REF', 'env://STRIPE'),
            'base_url' => 'https://api.stripe.com/v1',
            'recurring' => (bool) env('STRIPE_RECURRING', true), // stored cards for automatic top-ups (setup_future_usage=off_session, off-session PaymentIntents)
        ],
        'bank' => [
            'iban' => env('ONHOST_BANK_IBAN', ''),
            'bic' => env('ONHOST_BANK_BIC', ''),
            'account_number' => env('ONHOST_BANK_ACCOUNT', ''),
            'fio_token' => env('ONHOST_BANK_FIO_TOKEN', ''), // Fio API token (read-only) of the account customers pay to; enables onhost:bank:sync
        ],
    ],

    'einvoice' => [
        'driver' => env('EINVOICE_DRIVER', 'peppol'),
        'peppol' => [
            'base_url' => env('PEPPOL_BASE_URL'),
            'secret_ref' => env('PEPPOL_SECRET_REF', 'env://PEPPOL'),
            'sender_id' => env('PEPPOL_SENDER_ID'), // e.g. 9946:SK2020xxxxx
        ],
        'sk_mandatory_from' => '2027-01-01',
    ],

    'ai' => [
        'enabled' => (bool) env('ONHOST_AI_ENABLED', false),
        'driver' => env('ONHOST_AI_DRIVER', 'openai_compatible'), // openai_compatible | anthropic
        'openai_compatible' => [
            'base_url' => env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4.1-mini'),
            'embedding_model' => env('AI_OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'secret_ref' => env('AI_OPENAI_SECRET_REF', 'env://AI_OPENAI'),
        ],
        'anthropic' => [
            'base_url' => env('AI_ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('AI_ANTHROPIC_MODEL', 'claude-sonnet-5'),
            'secret_ref' => env('AI_ANTHROPIC_SECRET_REF', 'env://AI_ANTHROPIC'),
        ],
        'max_context_articles' => 6,
        'timeout_seconds' => 30,
        // what the model may cost (AssistantBudget): past a limit the assistant answers from the help centre, nothing is refused
        'budget' => [
            'user_per_hour' => (int) env('ONHOST_AI_USER_PER_HOUR', 40),
            'staff_per_hour' => (int) env('ONHOST_AI_STAFF_PER_HOUR', 120),
            'organization_per_day' => (int) env('ONHOST_AI_ORG_PER_DAY', 300),
            'tokens_per_day' => (int) env('ONHOST_AI_TOKENS_PER_DAY', 3000000), // input + output, the whole platform; 0 = no ceiling
        ],
        'prompt_version' => 'support-assistant/v1',
    ],

    // chargeback in credit: the share of the unused paid period returned when a customer leaves early (staff change it in the console; this is the default)
    'chargeback' => ['percent' => (int) env('ONHOST_CHARGEBACK_PERCENT', 70), 'cluster_threshold' => (int) env('ONHOST_CHARGEBACK_CLUSTER_THRESHOLD', 3), 'cluster_days' => (int) env('ONHOST_CHARGEBACK_CLUSTER_DAYS', 30)], // clusters of requests per node/product open an internal incident (audit §5j-6)

    // loyalty programme: points per action, levels with a promo-credit reward on reaching them (staff override the levels in system settings)
    'loyalty' => [
        'points' => ['order.paid_per_100' => 1, 'payment.on_time' => 10, 'mfa.enabled' => 50, 'backups.enabled' => 30, 'monitoring.enabled' => 20, 'service.first' => 100, 'anniversary' => 100, 'referral' => 200],
        'levels' => [['key' => 'bronze', 'name' => 'Bronze', 'min' => 0, 'reward_minor' => 0], ['key' => 'silver', 'name' => 'Silver', 'min' => 500, 'reward_minor' => 10000], ['key' => 'gold', 'name' => 'Gold', 'min' => 2000, 'reward_minor' => 30000], ['key' => 'platinum', 'name' => 'Platinum', 'min' => 5000, 'reward_minor' => 80000]],
        'reward_currency' => 'CZK',
        // customer-to-customer referrals (audit §5j-2): points and promo credit for both sides once the invited organization pays its first document
        'referral' => ['referrer_points' => 200, 'referred_points' => 100, 'referrer_credit_minor' => (int) env('ONHOST_REFERRAL_CREDIT', 20000), 'referred_credit_minor' => (int) env('ONHOST_REFERRAL_WELCOME_CREDIT', 10000), 'max_per_30d' => (int) env('ONHOST_REFERRAL_MONTHLY_CAP', 20), 'clawback_days' => (int) env('ONHOST_REFERRAL_CLAWBACK_DAYS', 90)], // clawback_days: a chargeback of the referred organization within this window claws the referral back (§5l-4)
        // monthly missions (audit §5j-3): points per completed mission; the streak of on-time months that may earn a permanent discount (finance approves it)
        'missions' => ['mfa_all' => 40, 'monitor' => 20, 'restore_test' => 60, 'on_time' => 30, 'profile' => 10],
        'streak' => ['months' => (int) env('ONHOST_STREAK_MONTHS', 12), 'discount_pct' => (float) env('ONHOST_STREAK_DISCOUNT', 5)],
        'forecast' => ['default_completion_pct' => (float) env('ONHOST_LOYALTY_FORECAST_DEFAULT_PCT', 25)], // the campaign cost forecast's rate for missions without history (audit §5n-5)
    ],

    // marketplace of partner services (audit §5j-1): the platform's share of every order, the window after which a delivery counts as accepted
    'marketplace' => ['commission_pct' => (int) env('ONHOST_MARKETPLACE_COMMISSION', 20), 'auto_accept_days' => (int) env('ONHOST_MARKETPLACE_AUTO_ACCEPT_DAYS', 14), 'overdue_grace_days' => (int) env('ONHOST_MARKETPLACE_OVERDUE_GRACE_DAYS', 7), 'late_credit_pct_per_day' => (float) env('ONHOST_MARKETPLACE_LATE_CREDIT_PCT', 5), 'late_credit_cap_pct' => (float) env('ONHOST_MARKETPLACE_LATE_CREDIT_CAP', 50), 'subscription_missed_credit_pct' => (float) env('ONHOST_MARKETPLACE_MISSED_PERIOD_CREDIT', 50), 'period_warn_pct' => (float) env('ONHOST_MARKETPLACE_PERIOD_WARN_PCT', 80)], // §5l-2: days past the due date before the customer may take a refund without a dispute

    // regional pricing (audit §5j-8): country groups with a suggested currency and a percentage on the catalogue price (staff override the table in Nastavení → Ceny)
    'pricing' => ['regions' => [
        ['key' => 'sk', 'label' => 'Slovensko', 'countries' => ['SK'], 'currency' => 'EUR', 'adjust_pct' => 0],
        ['key' => 'eu', 'label' => 'EU', 'countries' => ['DE', 'AT', 'PL', 'HU', 'SI', 'HR', 'NL', 'BE', 'FR', 'IT', 'ES', 'PT', 'IE', 'FI', 'SE', 'DK', 'EE', 'LV', 'LT', 'LU', 'RO', 'BG', 'GR', 'CY', 'MT'], 'currency' => 'EUR', 'adjust_pct' => (float) env('ONHOST_PRICING_EU_ADJUST', 5)],
    ]],

    // sandbox tenants (audit §5j-9): provisioning routed to lab instances (`options.sandbox`), a promo credit to test with, no loyalty, no commissions
    'sandbox' => ['credit_minor' => (int) env('ONHOST_SANDBOX_CREDIT', 500000)],

    // green hosting (audit §5j-10): energy profile per region (a node may override it in tags.energy), the estimate model
    'green' => [
        'regions' => ['cz1' => ['label' => 'Praha CZ1', 'source' => env('ONHOST_GREEN_CZ1_SOURCE', 'renewable'), 'gco2_per_kwh' => (float) env('ONHOST_GREEN_CZ1_GCO2', 20), 'pue' => (float) env('ONHOST_GREEN_CZ1_PUE', 1.25), 'renewable_pct' => (int) env('ONHOST_GREEN_CZ1_RENEWABLE', 100)]],
        'watts_per_gb_ram' => (float) env('ONHOST_GREEN_WATTS_PER_GB', 3.5), 'default_gco2_per_kwh' => 400,
        'measured_max_age_hours' => (int) env('ONHOST_GREEN_MEASURED_MAX_AGE_HOURS', 24), // a probe's watts count this long (audit §5k-6), then the model takes over again
        'bmc' => ['temp_warn_c' => (int) env('ONHOST_BMC_TEMP_WARN_C', 75)], // Redfish inventory (audit §5o-6): a hotter host or a failed PSU/fan alerts operations
        'statement' => 'Odhad podle prodané paměti, PUE datového centra a uhlíkové intenzity dodavatele energie; není to měření elektroměrem.',
    ],

    'provisioning' => [
        'freeze_cache_key' => 'onhost:provisioning:freeze',
        // what an operation forgets (OperationSecrets): a generated password is shown this long, a failed run keeps what it was given this long for a retry
        'secret_reveal_minutes' => (int) env('ONHOST_OPERATION_SECRET_REVEAL_MINUTES', 30),
        'secret_failed_days' => (int) env('ONHOST_OPERATION_SECRET_FAILED_DAYS', 7),
        'latency_target_seconds' => (int) env('ONHOST_LATENCY_TARGET_SECONDS', 30), // p95 of what a person waits for (restart, PHP, a new database…); the doctor and the operations board judge by it
        'rebalance' => ['high' => (float) env('ONHOST_REBALANCE_HIGH', 0.85), 'low' => (float) env('ONHOST_REBALANCE_LOW', 0.6), 'target' => (float) env('ONHOST_REBALANCE_TARGET', 0.75)], // node load (RAM sold / RAM capacity) that triggers, receives, and ends a rebalancing move
        'samples' => ['retention_days' => (int) env('ONHOST_NODE_SAMPLES_RETENTION_DAYS', 30)], // hourly node samples behind the trend (audit §5k-7)
        'capacity_forecast' => ['warn_days' => (int) env('ONHOST_CAPACITY_WARN_DAYS', 30), 'node_monthly_minor' => []], // node_monthly_minor: {role: price of one node per month in the budget currency} for the budget forecast (audit §5r-5) // a pool with fewer days left reaches operations (audit §5m-7)
        'capacity_budget' => ['monthly_minor' => (int) env('ONHOST_CAPACITY_BUDGET_MONTHLY_MINOR', 0), 'currency' => env('ONHOST_CAPACITY_BUDGET_CURRENCY', 'EUR')], // the monthly cap on vendor node orders, 0 = none (audit §5q-5)
        'node_bootstrap' => ['callback_base' => env('ONHOST_NODE_BOOTSTRAP_CALLBACK', ''), 'user_data' => env('ONHOST_NODE_BOOTSTRAP_USER_DATA', ''), 'ssh_key' => env('ONHOST_NODE_BOOTSTRAP_SSH_KEY', '')], // cloud-init of a vendor-ordered node and its readiness call-back (audit §5o-7)
        // what a node must make and remove again before it carries anybody (H479): the attributes of the smallest thing each
        // role sells, as the adapter reads them — e.g. compute: {template: 9000, cores: 1, memory_mb: 512, disk_gb: 5,
        // storage: "local-lvm"}. EMPTY on purpose: a role without a template creates nothing, so no panel is touched until
        // the owner has written one down for a test range. Once written, the synthetic run becomes a required point.
        'qualification' => ['synthetic' => []],
        'backlog' => ['threshold' => (int) env('ONHOST_QUEUE_BACKLOG_THRESHOLD', 25), 'age_minutes' => (int) env('ONHOST_QUEUE_BACKLOG_AGE_MINUTES', 5)], // operations due for longer than this pile up → platform.queue.backlog (audit §5h-5)
        'autoscale' => ['enabled' => (bool) env('ONHOST_QUEUE_AUTOSCALE', false), 'max_helpers' => (int) env('ONHOST_QUEUE_MAX_HELPERS', 3), 'cooldown_minutes' => (int) env('ONHOST_QUEUE_COOLDOWN_MINUTES', 15), 'max_time_seconds' => (int) env('ONHOST_QUEUE_MAX_TIME', 900)], // helper workers started on the backlog gauge (audit §5i-3)
        'console_token_ttl_seconds' => (int) env('ONHOST_CONSOLE_TOKEN_TTL', 120),
        'provider_timeout_seconds' => (int) env('ONHOST_PROVIDER_TIMEOUT', 10),
        'diagnostic_reserve' => (float) env('ONHOST_PROVIDER_DIAGNOSTIC_RESERVE', 0.05), // share of every panel quota only health reads may use (H323); never below 3 calls
        'provider_max_body_bytes' => (int) env('ONHOST_PROVIDER_MAX_BODY_BYTES', 8388608), // a panel answer larger than this is refused before it can exhaust a worker (H318)
        'reconcile' => [
            'critical_minutes' => 5,
            'normal_minutes' => 15,
            'domains_daily_hour' => 4,
        ],
        'scheduler_weights' => [
            'memory_headroom' => 0.30, 'cpu_headroom' => 0.25, 'storage_headroom' => 0.20,
            'io_health' => 0.10, 'failure_domain_affinity' => 0.10, 'network_health' => 0.05,
        ],
        'n_plus_one_sell_ratio' => 0.75,
        'capacity_gate' => (bool) env('ONHOST_CAPACITY_GATE', true), // a server no registered node can take is refused in the cart (H04); off = accept and let provisioning wait
        'default_region' => env('ONHOST_DEFAULT_REGION', 'cz1'),
        'hostname_suffix' => env('ONHOST_VM_HOSTNAME_SUFFIX', 'cust.onhost.cz'),
        'web_preview_suffix' => env('ONHOST_WEB_PREVIEW_SUFFIX', 'web.onhost.cz'),
        'search_domain' => env('ONHOST_VM_SEARCH_DOMAIN', 'onhost.cz'),
        'auto_repair' => (bool) env('ONHOST_DRIFT_AUTO_REPAIR', false), // handoff docs-provider-apis §7: drift is decided by a human, never repaired silently
        'retry_until_seconds' => (int) env('ONHOST_OPERATION_RETRY_UNTIL', 6 * 3600),
        // Default VM firewall (Proxmox rule format): SSH, HTTP/S and ICMP in; everything else dropped (policy_in DROP).
        'default_firewall' => [
            ['action' => 'ACCEPT', 'type' => 'in', 'proto' => 'tcp', 'dport' => '22', 'comment' => 'ssh'],
            ['action' => 'ACCEPT', 'type' => 'in', 'proto' => 'tcp', 'dport' => '80,443', 'comment' => 'web'],
            ['action' => 'ACCEPT', 'type' => 'in', 'proto' => 'icmp', 'comment' => 'icmp'],
        ],
    ],

    'orders' => [
        // an unpaid order with the same cart fingerprint placed within this window is returned instead of duplicated
        'duplicate_window_minutes' => (int) env('ONHOST_ORDER_DUPLICATE_WINDOW_MINUTES', 15),
        // a cart line with a quantity becomes that many lines (one line is one service); what one line and one order may hold
        'max_quantity' => (int) env('ONHOST_ORDER_MAX_QUANTITY', 10),
        'max_lines' => (int) env('ONHOST_ORDER_MAX_LINES', 50),
        // an order nobody paid is cancelled after this many days (proforma voided, transfer no longer matched)
        'unpaid_expire_days' => (int) env('ONHOST_ORDER_UNPAID_EXPIRE_DAYS', 14),
        // intake pre-check (audit §5f-8): signals add up to a score; at hold_score the paid order waits for a staff decision before provisioning
        'risk' => [
            'enabled' => (bool) env('ONHOST_ORDER_RISK', true),
            'hold_score' => (int) env('ONHOST_ORDER_RISK_HOLD', 60),
            'rapid_window_minutes' => 15,
            'rapid_orders' => 3,
            'feedback_step' => (int) env('ONHOST_ORDER_RISK_FEEDBACK_STEP', 5), // how much a staff decision moves the weight of each signal that held the order (release: down, reject: up)
            'geo' => ['endpoint' => env('ONHOST_ORDER_RISK_GEO_ENDPOINT', ''), 'timeout_seconds' => 2], // e.g. https://get.geojs.io/v1/ip/country/{ip}.json — a JSON answer with a country code; empty = no country signal
            'first_order_limit_minor' => ['CZK' => (int) env('ONHOST_ORDER_RISK_FIRST_CZK', 2000000), 'EUR' => (int) env('ONHOST_ORDER_RISK_FIRST_EUR', 80000)],
            'disposable_domains' => array_filter(array_map('trim', explode(',', (string) env('ONHOST_ORDER_RISK_DISPOSABLE', 'mailinator.com,guerrillamail.com,10minutemail.com,tempmail.com,temp-mail.org,yopmail.com,sharklasers.com,trashmail.com,dispostable.com,getnada.com,mohmal.com,throwawaymail.com')))),
            'free_mail_domains' => ['gmail.com', 'seznam.cz', 'email.cz', 'centrum.cz', 'post.cz', 'volny.cz', 'atlas.cz', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com', 'protonmail.com', 'proton.me', 'azet.sk', 'zoznam.sk'],
        ],
        // ── TASK-0021 (owner decision 20): credit is spent by the owner and the billing admin (`billing.wallet.spend`). On: a credit-paid
        // order (the modes below) of anybody else waits for their approval, and paying from credit at once (an invoice, a domain renewal,
        // a marketplace order, approving paid support work, an archive download) is refused to them. Off (default) = everything as before.
        'credit_approval' => [
            'enabled' => (bool) env('ONHOST_ORDER_CREDIT_APPROVAL', false),
            'modes' => ['wallet', 'postpaid'],
            'expire_days' => (int) env('ONHOST_ORDER_CREDIT_APPROVAL_EXPIRE_DAYS', 7), // an order nobody decided is cancelled after this many days
        ],
        // ── end TASK-0021 ──
    ],

    'wapi' => [
        'endpoint' => env('WEDOS_ENDPOINT', 'https://api.wedos.com/wapi/json'),
        'timezone' => 'Europe/Prague',
        'test_mode' => (bool) env('WEDOS_TEST_MODE', true),
        'egress_ip' => env('WEDOS_EGRESS_IP'),
        'public_pricelist_url' => env('WEDOS_PUBLIC_PRICELIST_URL', 'https://vedos.cz/domeny/cenik/'),
        'force_ip_resolve' => env('WEDOS_FORCE_IP_RESOLVE', 'v4'), // v4 | v6 | null; WAPI redirects IPv6 clients to a 404 page, so the IPv4 egress is the one to allow-list
        'limits' => ['all_per_hour' => 1000, 'domain_family_per_hour' => 100, 'reserve' => 0.15],
        'clock_max_offset_seconds' => (float) env('WEDOS_CLOCK_MAX_OFFSET_SECONDS', 5.0), // measured from HTTP Date headers (1 s granularity + latency); the hourly auth tolerates a few seconds
        'credit_min' => ['CZK' => 1500000], // minor units (15 000 Kč)
    ],

    'subreg' => [
        'endpoint' => env('SUBREG_ENDPOINT', 'https://subreg.cz/soap/cmd.php?soap_format=1'),
        'session_ttl_seconds' => 1500,
        'public_pricelist_url' => env('SUBREG_PUBLIC_PRICELIST_URL', 'https://subreg.cz/cz/cenik-domen/'),
        'limits' => ['per_hour' => 2000, 'reserve' => 0.1],
        'credit_min' => ['CZK' => 500000, 'EUR' => 20000],
    ],

    // events and transactional mails leave within seconds through queued jobs (the minute scheduler stays as the safety net)
    'outbox' => [
        'eager' => (bool) env('ONHOST_OUTBOX_EAGER', true),
    ],

    /*
    | Mail clients: what a mailbox is reached with. The ports are the node's (Dovecot and Postfix as ISPConfig sets
    | them up); the panel, the password page and the automatic client configuration all read them from here.
    */
    'mail' => [
        'imap_port' => (int) env('ONHOST_MAIL_IMAP_PORT', 993),
        'pop3_port' => (int) env('ONHOST_MAIL_POP3_PORT', 995),
        'smtp_port' => (int) env('ONHOST_MAIL_SMTP_PORT', 587),
        // the lists asked about every node's sending address (BlocklistCheck); zone => the name an operator knows it by
        'blocklists' => ['zen.spamhaus.org' => 'Spamhaus ZEN', 'bl.spamcop.net' => 'SpamCop', 'b.barracudacentral.org' => 'Barracuda'],
    ],

    'dns' => [
        'nameservers' => [
            'powerdns' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONHOST_DNS_NAMESERVERS', 'ns1.onhost.cz,ns2.onhost.cz'))))),
            'wedos_zone' => ['ns.wedos.com', 'ns.wedos.cz', 'ns.wedos.eu', 'ns.wedos.net'],
        ],
        'parking_ipv4' => env('ONHOST_PARKING_IPV4'),
        // zones the platform owns (adopted with `onhost:dns:adopt`): hosting subdomains such as <label>.web.onhost.cz get their
        // A/AAAA records here when a site is provisioned, and lose them when it is terminated
        'platform_zones' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONHOST_PLATFORM_ZONES', 'onhost.cz'))))),
        'platform_organization_slug' => env('ONHOST_PLATFORM_ORGANIZATION_SLUG', 'onhost-platform'),
        'mail_host' => env('ONHOST_MAIL_HOST', 'mail.onhost.cz'),
        'spf_include' => env('ONHOST_SPF_INCLUDE', '_spf.onhost.cz'),
        // a zone has a size, and so has its list of changes waiting to be published
        'max_records_per_zone' => (int) env('ONHOST_DNS_MAX_RECORDS', 500),
        'max_pending_changes' => (int) env('ONHOST_DNS_MAX_PENDING', 200),
        // zones compared with their provider per night (onhost:dns:drift), oldest comparison first
        'drift_batch' => (int) env('ONHOST_DNS_DRIFT_BATCH', 200),
    ],

    'domains' => [
        'nsset_tlds' => ['cz'],
        // after a create whose answer was lost, the name has to stay unknown to the registry this long before the create is sent again
        'recreate_after_seconds' => (int) env('ONHOST_DOMAIN_RECREATE_AFTER_SECONDS', 600),
        'shared_nsset_handle' => env('ONHOST_NSSET_HANDLE', 'NSSET-ONHOST'),
        'tech_contact_handle' => env('ONHOST_TECH_CONTACT_HANDLE', ''),
        'renew_lead_days' => (int) env('ONHOST_DOMAIN_RENEW_LEAD_DAYS', 14),
        // days after the expiry during which an unpaid renewal is still attempted daily (the registry renews at the ordinary price;
        // .cz keeps a domain 30 days, most gTLDs 30–45) — afterwards only a paid restore helps
        // a domain no registrar lists, and that the registrar says it does not have, is closed after it stayed away this long (DomainService::missingAtRegistrar)
        'missing_confirm_hours' => (int) env('ONHOST_DOMAIN_MISSING_CONFIRM_HOURS', 36),
        'grace_retry_days' => (int) env('ONHOST_DOMAIN_GRACE_RETRY_DAYS', 20),
        'notice_days' => [60, 30, 14, 7, 3, 1],
        'search_max' => 20,
        'search_cache_seconds' => 60,
        'transfer_secret_ttl_days' => 7,
        'critical' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONHOST_CRITICAL_DOMAINS', 'onhost.cz'))))),
        'poll_batch' => 50,
        'poll_max_attempts' => 5,
        // ONhost's own domain terms shown to customers; the registrar behind a domain is never named outside the console
        'terms_url' => env('ONHOST_DOMAIN_TERMS_URL', '/dokumenty/podminky-registrace-domen'),
        'registrar' => [
            // registrar keys in tie-break order; 'auto' in tld_policies.registrar_provider lets the cheapest cost price decide
            'preference' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONHOST_REGISTRAR_PREFERENCE', 'wedos,subreg'))))),
            'cost_ttl_hours' => (int) env('ONHOST_REGISTRAR_COST_TTL_HOURS', 24),
            // public (retail) price lists scraped into the price book for registrars without a wholesale price API
            'scrapers' => ['wedos' => WedosPublicPriceList::class, 'subreg' => SubregPublicPriceList::class],
            // exchange rates used only to compare registrar cost prices quoted in different currencies (CZK per unit)
            'fx_czk' => ['CZK' => 1.0, 'EUR' => (float) env('ONHOST_FX_EUR_CZK', 25.0), 'USD' => (float) env('ONHOST_FX_USD_CZK', 23.0)],
        ],
    ],

    'sla' => [
        'classes' => [
            'standard' => ['objective' => 99.9, 'contractual' => null, 'certified' => false],
            'business' => ['objective' => 99.95, 'contractual' => 99.9, 'certified' => false],
            'ha' => ['objective' => 99.995, 'contractual' => 99.99, 'certified' => false],
            'critical' => ['objective' => 99.999, 'contractual' => 99.99, 'certified' => false],
        ],
        'probe_quorum' => 2,
        'probe_locations_min' => 3,
        'burn_rate_windows' => [['5m', '1h', 14.4], ['30m', '6h', 6.0], ['6h', '3d', 1.0]],
        'error_budget_policy' => [25 => 'normal', 50 => 'review_high_risk', 75 => 'reliability_priority', 100 => 'freeze'],
        'auto_resolve_minutes' => 15,   // probe-sourced incidents in MONITORING auto-close after a stable period
        'credit_policies' => [          // versioned bands: credit % of monthly price when availability falls below the threshold
            'business' => ['bands' => [['below' => 99.9, 'credit_percent' => 10], ['below' => 99.0, 'credit_percent' => 25], ['below' => 95.0, 'credit_percent' => 50]], 'cap_percent' => 50],
            'ha' => ['bands' => [['below' => 99.99, 'credit_percent' => 10], ['below' => 99.9, 'credit_percent' => 25], ['below' => 99.0, 'credit_percent' => 50]], 'cap_percent' => 50],
            'critical' => ['bands' => [['below' => 99.99, 'credit_percent' => 25], ['below' => 99.9, 'credit_percent' => 50], ['below' => 99.0, 'credit_percent' => 100]], 'cap_percent' => 100],
        ],
    ],

    'compliance' => [
        'nis2_dns_warning_domains' => 8000,
        'nis2_dns_critical_domains' => 10000,
        'timers' => [
            'NIS2_EARLY_WARNING' => 24, 'NIS2_NOTIFICATION' => 72, 'NIS2_FINAL_REPORT' => 720,
            'GDPR_72H' => 72, 'DSA_ART18_PROMPT' => 24, 'DATA_ACT_SWITCHING' => 720,
        ],
        'data_export_grace_days' => 30,
        // an erasure of the whole account waits this long after the owner asked for it; anybody who manages the
        // organization can stop it meanwhile (it cannot be taken back once it has run)
        'deletion_grace_days' => (int) env('ONHOST_DELETION_GRACE_DAYS', 14),
        'retention_after_termination_days' => (int) env('ONHOST_RETENTION_AFTER_TERMINATION_DAYS', 30),
    ],

    'notifications' => [
        'mandatory_kinds' => ['security.login', 'security.mfa', 'invoice.issued', 'legal.notice', 'domain.expiry', 'api_token.created', 'incident.affecting'],
        'staff_digest_to' => env('ONHOST_STAFF_DIGEST_TO', ''), // comma-separated addresses for the daily staff digest (audit §5e-5)
    ],

    'partners' => [
        // [tier, monthly paid volume threshold (minor units), revenue-share %] — prototype Onhost-partner.dc.html
        'tiers' => [['bronze', 0, 15], ['silver', 2500000, 18], ['gold', 5000000, 22], ['platinum', 12000000, 26]],
        'min_payout_minor' => 100000,
        // term-specific finance rules (audit §5o-1): what the rule `partners.auto_approve` may approve without finance
        'auto_approve' => ['rate_lock_max_months' => (int) env('ONHOST_PARTNER_AUTO_RATE_LOCK_MONTHS', 6), 'clean_months' => (int) env('ONHOST_PARTNER_AUTO_CLEAN_MONTHS', 12)],
        'default_model' => env('ONHOST_PARTNER_DEFAULT_MODEL', 'share'), // the model a partner may return to by rule after a clean year (audit §5p-5)
        'rate_lock_months' => 3,
        'oneoff_bonus_months' => 3,
        'oneoff_tail_pct' => 5,
        'whitelabel_cname' => env('ONHOST_WHITELABEL_CNAME', 'panel.onhost.cz'),
        'public_tiers' => [
            'cs' => [['c' => '1–10', 'm' => '20 %', 'b' => 'panel a API'], ['c' => '11–50', 'm' => '28 %', 'b' => '+ vlastní doména panelu'], ['c' => '51–200', 'm' => '35 %', 'b' => '+ prioritní podpora'], ['c' => '200+', 'm' => 'individuálně', 'b' => '+ dedikovaný manager a SLA']],
            'en' => [['c' => '1–10', 'm' => '20 %', 'b' => 'panel and API'], ['c' => '11–50', 'm' => '28 %', 'b' => '+ own panel domain'], ['c' => '51–200', 'm' => '35 %', 'b' => '+ priority support'], ['c' => '200+', 'm' => 'custom', 'b' => '+ dedicated manager and SLA']],
        ],
        'assets' => [
            ['key' => 'logo-pack', 'name' => 'Logo pack (SVG/PNG)', 'url' => '/partner/assets/onhost-logo-pack.zip'],
            ['key' => 'price-list', 'name' => 'Ceník pro partnery (PDF)', 'url' => '/partner/assets/onhost-partner-pricelist.pdf'],
            ['key' => 'banners', 'name' => 'Bannery 300×250 / 728×90', 'url' => '/partner/assets/onhost-banners.zip'],
        ],
    ],

    'ui' => [
        'demo' => (bool) env('ONHOST_UI_DEMO', false),      // true: surfaces keep the prototype's local store and role switcher
        'surfaces_path' => (string) env('ONHOST_SURFACES_PATH', '') !== '' ? env('ONHOST_SURFACES_PATH') : base_path('apps/surfaces'), // an empty variable (the .env.example default) must not mean an empty root
    ],

    // game servers: the catalogue's template keys (Product.meta.eggs) and how each maps onto a game panel egg — matched by
    // nest/egg name when the panel is bootstrapped (onhost:game:sync-eggs), with safe environment defaults and the RAM floor
    // the template needs; `import` names the community egg to import when the panel lacks the template
    'game' => [
        'operator_rotation_days' => (int) env('ONHOST_GAME_OPERATOR_ROTATION_DAYS', 180), // remind operations to rotate the Steam account after this (audit §5u-5)
        'operator_variables_ref' => env('ONHOST_GAME_OPERATOR_VARIABLES_REF', 'db://game/operator-variables'), // read-only egg variables the operator holds, e.g. the Steam account DayZ downloads with (audit §5s)
        'upload_max_mb' => (int) env('ONHOST_GAME_UPLOAD_MAX_MB', 100), // binary uploads from the console through the panel's signed URL (audit §5r-3)
        'node_reserve_mb' => (int) env('ONHOST_GAME_NODE_RESERVE_MB', 1024), // RAM kept for the host when a node limit is detected from the daemon (audit §5q follow-up)
        'eggs' => [
            'minecraft-paper' => ['label' => 'Minecraft · Paper', 'note' => 'nejrozšířenější Minecraft server s pluginy', 'nest' => '/minecraft/i', 'egg' => '/^paper$/i', 'min_ram_mb' => 2048, 'min_vcpu' => 1, 'min_nvme_gb' => 10, 'slot_mb' => 150, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'Paper', 'variant_note' => 'Doporučeno · rychlý server s pluginy Bukkit/Paper', 'environment' => ['MINECRAFT_VERSION' => 'latest', 'BUILD_NUMBER' => 'latest'], 'versions' => ['1.21.8', '1.21.7', '1.21.4', '1.20.6'], 'import' => 'pelican-eggs/minecraft (paper)'],
            'minecraft-spigot' => ['label' => 'Minecraft · Spigot', 'note' => 'Bukkit/Spigot pluginy; verze podle objednávky (výchozí 1.21.8)', 'nest' => '/minecraft/i', 'egg' => '/^spigot/i', 'fallback_egg' => '/^paper$/i', 'min_ram_mb' => 2048, 'min_vcpu' => 1, 'min_nvme_gb' => 10, 'slot_mb' => 150, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'Spigot', 'variant_note' => 'klasické pluginy Bukkit/Spigot', 'environment' => ['MINECRAFT_VERSION' => '1.21.8', 'BUILD_NUMBER' => 'latest', 'SERVER_JARFILE' => 'spigot-{version}.jar'], 'versions' => ['1.21.8', '1.21.7', '1.21.4', '1.20.6'], 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'startup' => 'bash onhost-start.sh', 'startup_script' => '#!/bin/bash
# ONhost start script (written by the control plane through the panel API; audit §5q): Spigot has no jar downloads,
# so the first start builds it with BuildTools, every later start just runs the jar. Variables come from the panel.
cd /home/container || exit 1
JAR="${SERVER_JARFILE:-spigot.jar}"
VER="${MINECRAFT_VERSION:-latest}"
if [ ! -f "$JAR" ]; then
  echo "ONhost: building Spigot $VER with BuildTools (first start, several minutes)"
  curl -fsSL -o BuildTools.jar https://hub.spigotmc.org/jenkins/job/BuildTools/lastSuccessfulBuild/artifact/target/BuildTools.jar || { echo \'ONhost: BuildTools download failed\'; exit 1; }
  java -jar BuildTools.jar --rev "$VER" --output-dir . --final-name "$JAR" || { echo \'ONhost: BuildTools failed\'; exit 1; }
  rm -rf BuildTools.jar BuildData Bukkit CraftBukkit Spigot apache-maven-* work
  echo \'eula=true\' > eula.txt
fi
exec java -Xms128M -XX:MaxRAMPercentage=95.0 -Dterminal.jline=false -Dterminal.ansi=true -jar "$JAR" nogui
', 'import' => 'pelican-eggs/minecraft (spigot); without it the Paper egg hosts a self-built Spigot: the startup command runs BuildTools on the first start (SpigotMC ships no jar downloads)'], // §5o/§5q: the Paper egg runs Spigot when the panel has no Spigot egg
            'minecraft-forge' => ['label' => 'Minecraft · Forge', 'note' => 'modpacky a mody pro Forge', 'nest' => '/minecraft/i', 'egg' => '/forge/i', 'min_ram_mb' => 4096, 'min_vcpu' => 2, 'min_nvme_gb' => 20, 'slot_mb' => 300, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'Forge', 'variant_note' => 'modpacky a mody', 'environment' => [], 'import' => 'pelican-eggs/minecraft (forge)'],
            'minecraft-purpur' => ['label' => 'Minecraft · Purpur', 'note' => 'Paper s dalšími herními volbami, pluginy Bukkit/Paper', 'nest' => '/minecraft/i', 'egg' => '/^purpur$/i', 'min_ram_mb' => 2048, 'min_vcpu' => 1, 'min_nvme_gb' => 10, 'slot_mb' => 150, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'Purpur', 'variant_note' => 'Paper s dalšími herními volbami', 'environment' => ['MINECRAFT_VERSION' => 'latest'], 'versions' => ['1.21.8', '1.21.7', '1.21.4', '1.20.6'], 'import' => 'pelican-eggs/minecraft (purpur)'],
            'minecraft-vanilla' => ['label' => 'Minecraft · Vanilla', 'note' => 'oficiální server Mojang bez modů', 'nest' => '/minecraft/i', 'egg' => '/^vanilla minecraft$/i', 'min_ram_mb' => 2048, 'min_vcpu' => 1, 'min_nvme_gb' => 10, 'slot_mb' => 200, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'Vanilla', 'variant_note' => 'oficiální server bez modů', 'version_env' => 'VANILLA_VERSION', 'versions' => ['1.21.8', '1.21.7', '1.21.4', '1.20.6'], 'environment' => [], 'import' => 'pelican-eggs/minecraft (vanilla)'],
            'minecraft-bedrock' => ['label' => 'Minecraft · Bedrock', 'note' => 'pro konzole, mobily a Windows edici (UDP)', 'nest' => '/minecraft/i', 'egg' => '/^vanilla bedrock$/i', 'min_ram_mb' => 1024, 'min_vcpu' => 1, 'min_nvme_gb' => 5, 'slot_mb' => 100, 'group' => 'minecraft-bedrock', 'category' => 'minecraft', 'variant' => 'Bedrock', 'environment' => [], 'import' => 'pelican-eggs/minecraft (bedrock)'],
            'minecraft-sponge' => ['label' => 'Minecraft · Sponge', 'note' => 'SpongeVanilla s pluginy Sponge API', 'nest' => '/minecraft/i', 'egg' => '/^sponge/i', 'min_ram_mb' => 2048, 'min_vcpu' => 1, 'min_nvme_gb' => 10, 'slot_mb' => 200, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'Sponge', 'variant_note' => 'pluginy Sponge API', 'environment' => [], 'import' => 'pelican-eggs/minecraft (sponge)'],
            'minecraft-bungeecord' => ['label' => 'Minecraft · BungeeCord', 'note' => 'proxy spojující více Minecraft serverů', 'nest' => '/minecraft/i', 'egg' => '/^bungee ?cord$/i', 'min_ram_mb' => 1024, 'min_vcpu' => 1, 'min_nvme_gb' => 5, 'slot_mb' => 50, 'group' => 'minecraft-java', 'category' => 'minecraft', 'variant' => 'BungeeCord', 'variant_note' => 'proxy spojující více serverů do sítě', 'environment' => [], 'import' => 'pelican-eggs/minecraft (bungeecord)'],
            '7-days-to-die' => ['label' => '7 Days To Die', 'note' => 'survival horor; doporučeno 8 GB', 'nest' => '/onhost|steam/i', 'egg' => '/^7 days to die$/i', 'min_ram_mb' => 6144, 'min_vcpu' => 2, 'min_nvme_gb' => 30, 'slot_mb' => 512, 'group' => '7-days-to-die', 'category' => 'survival', 'steam_appid' => 251570, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (7_days_to_die)'],
            'arma-reforger' => ['label' => 'Arma Reforger', 'note' => 'scénář a mody v panelu', 'nest' => '/onhost|steam/i', 'egg' => '/^arma reforger$/i', 'min_ram_mb' => 6144, 'min_vcpu' => 2, 'min_nvme_gb' => 30, 'slot_mb' => 512, 'group' => 'arma-reforger', 'category' => 'action', 'steam_appid' => 1874880, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (arma_reforger)'],
            'dayz' => ['label' => 'DayZ', 'note' => 'vojenský survival s mody', 'nest' => '/onhost|steam/i', 'egg' => '/^dayz$/i', 'min_ram_mb' => 6144, 'min_vcpu' => 2, 'min_nvme_gb' => 30, 'slot_mb' => 256, 'group' => 'dayz', 'category' => 'survival', 'steam_appid' => 221100, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (dayz)'],
            'enshrouded' => ['label' => 'Enshrouded', 'note' => 'kooperace až 16 hráčů', 'nest' => '/onhost|steam/i', 'egg' => '/^enshrouded$/i', 'min_ram_mb' => 6144, 'min_vcpu' => 2, 'min_nvme_gb' => 20, 'slot_mb' => 384, 'group' => 'enshrouded', 'category' => 'survival', 'steam_appid' => 1203620, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (enshrouded)'],
            'factorio' => ['label' => 'Factorio', 'note' => 'mapa se vytvoří při prvním startu', 'nest' => '/onhost/i', 'egg' => '/^factorio$/i', 'min_ram_mb' => 2048, 'min_vcpu' => 1, 'min_nvme_gb' => 5, 'slot_mb' => 128, 'group' => 'factorio', 'category' => 'building', 'steam_appid' => 427520, 'environment' => [], 'import' => 'pelican-eggs/games-standalone (factorio)'],
            'hytale' => ['label' => 'Hytale', 'note' => 'dedikovaný server Hytale', 'nest' => '/onhost/i', 'egg' => '/^hytale$/i', 'min_ram_mb' => 4096, 'min_vcpu' => 1, 'min_nvme_gb' => 10, 'slot_mb' => 200, 'group' => 'hytale', 'category' => 'building', 'environment' => [], 'import' => 'pelican-eggs/games-standalone (hytale)'],
            'project-zomboid' => ['label' => 'Project Zomboid', 'note' => 'heslo správce v panelu', 'nest' => '/onhost|steam/i', 'egg' => '/^project zomboid$/i', 'min_ram_mb' => 4096, 'min_vcpu' => 2, 'min_nvme_gb' => 20, 'slot_mb' => 256, 'group' => 'project-zomboid', 'category' => 'survival', 'steam_appid' => 108600, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (project_zomboid)'],
            'rust-autowipe' => ['label' => 'Rust (autowipe)', 'note' => 'automatický wipe podle plánu; doporučeno 16 GB', 'nest' => '/onhost/i', 'egg' => '/^rust autowipe$/i', 'min_ram_mb' => 8192, 'min_vcpu' => 2, 'min_nvme_gb' => 40, 'slot_mb' => 128, 'group' => 'rust', 'category' => 'survival', 'steam_appid' => 252490, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (rust_autowipe)'],
            'satisfactory' => ['label' => 'Satisfactory', 'note' => 'server se nárokuje v klientovi hry', 'nest' => '/onhost|steam/i', 'egg' => '/^satisfactory$/i', 'min_ram_mb' => 8192, 'min_vcpu' => 2, 'min_nvme_gb' => 20, 'slot_mb' => 1024, 'group' => 'satisfactory', 'category' => 'building', 'steam_appid' => 526870, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (satisfactory)'],
            'terraria' => ['label' => 'Terraria', 'note' => 'vanilla server, svět se vytvoří při prvním startu', 'nest' => '/onhost/i', 'egg' => '/^terraria( vanilla)?$/i', 'min_ram_mb' => 1024, 'min_vcpu' => 1, 'min_nvme_gb' => 5, 'slot_mb' => 64, 'group' => 'terraria', 'category' => 'building', 'steam_appid' => 105600, 'environment' => [], 'import' => 'pelican-eggs/games-standalone (terraria vanilla)'],
            'v-rising' => ['label' => 'V Rising', 'note' => 'nastavení hry v panelu', 'nest' => '/onhost|steam/i', 'egg' => '/^v ?rising$/i', 'min_ram_mb' => 4096, 'min_vcpu' => 2, 'min_nvme_gb' => 20, 'slot_mb' => 256, 'group' => 'v-rising', 'category' => 'survival', 'steam_appid' => 1604030, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (v_rising)'],
            'cs2' => ['label' => 'Counter-Strike 2', 'note' => 'Steam token serveru zadáte přímo v objednávce', 'nest' => '/onhost|source|steam|counter/i', 'egg' => '/counter[- ]?strike(:)? ?2|^cs2$/i', 'min_ram_mb' => 4096, 'min_vcpu' => 2, 'min_nvme_gb' => 60, 'slot_mb' => 128, 'group' => 'cs2', 'category' => 'action', 'steam_appid' => 730, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (counter_strike_2)'],
            'rust' => ['label' => 'Rust', 'note' => 'mapa se generuje při prvním startu; doporučeno 16 GB', 'nest' => '/rust|steam/i', 'egg' => '/^rust$/i', 'min_ram_mb' => 8192, 'min_vcpu' => 2, 'min_nvme_gb' => 40, 'slot_mb' => 128, 'group' => 'rust', 'category' => 'survival', 'steam_appid' => 252490, 'environment' => ['WORLD_SIZE' => '3000', 'MAX_PLAYERS' => '50'], 'import' => 'pelican-eggs/games-steamcmd (rust)'],
            'ark' => ['label' => 'ARK: Survival Evolved', 'note' => 'mapa TheIsland, hesla v panelu', 'nest' => '/source|steam|ark/i', 'egg' => '/ark/i', 'min_ram_mb' => 8192, 'min_vcpu' => 2, 'min_nvme_gb' => 60, 'slot_mb' => 256, 'group' => 'ark', 'category' => 'survival', 'steam_appid' => 346110, 'environment' => ['SERVER_MAP' => 'TheIsland'], 'import' => 'pelican-eggs/games-steamcmd (ark_survival_evolved)'],
            'valheim' => ['label' => 'Valheim', 'note' => 'název světa a heslo v panelu', 'nest' => '/onhost|valheim|steam/i', 'egg' => '/valheim/i', 'min_ram_mb' => 4096, 'min_vcpu' => 2, 'min_nvme_gb' => 10, 'slot_mb' => 384, 'group' => 'valheim', 'category' => 'survival', 'steam_appid' => 892970, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (valheim)'],
            'palworld' => ['label' => 'Palworld', 'note' => 'doporučeno 16 GB pro 8+ hráčů', 'nest' => '/onhost|palworld|steam/i', 'egg' => '/palworld/i', 'min_ram_mb' => 8192, 'min_vcpu' => 2, 'min_nvme_gb' => 30, 'slot_mb' => 512, 'group' => 'palworld', 'category' => 'survival', 'steam_appid' => 1623730, 'environment' => [], 'import' => 'pelican-eggs/games-steamcmd (palworld)'],
        ],
        'default_ports' => ['25565-25599'], // the port range a bootstrapped node gets when it has no free allocation
        // the public configurator (audit §5v): the customer picks a game, then RAM / vCPU / NVMe / backups / ports / databases on
        // sliders; every game carries its floors above (`min_ram_mb`, `min_vcpu`, `min_nvme_gb`) and `slot_mb`, the RAM one player
        // needs, from which the slot count is derived. Prices come from the catalogue's per-unit options of the `game` product.
        // how the public offer groups the templates (audit §5w): every Minecraft Java flavour is one "Minecraft Java Edition" card with a server type choice
        'groups' => [
            'minecraft-java' => ['label' => 'Minecraft Java Edition', 'note' => 'Paper, Purpur, Spigot, Vanilla, Forge, Sponge i BungeeCord síť', 'note_en' => 'Paper, Purpur, Spigot, Vanilla, Forge, Sponge or a BungeeCord network'],
            'minecraft-bedrock' => ['label' => 'Minecraft Bedrock Edition', 'note' => 'pro konzole, mobily a Windows edici', 'note_en' => 'for consoles, phones and the Windows edition'],
            'rust' => ['label' => 'Rust'],
        ],
        'categories' => ['minecraft' => ['Minecraft', 'Minecraft'], 'survival' => ['Survival', 'Survival'], 'action' => ['Akční', 'Action'], 'building' => ['Budování a sandbox', 'Building and sandbox']],
        'art_url' => env('ONHOST_GAME_ART_URL', 'https://cdn.cloudflare.steamstatic.com/steam/apps/{appid}/header.jpg'), // header art fetched once by the control plane and served from our origin (no third-party requests from visitors)
        'configurator' => ['plan' => env('ONHOST_GAME_CONFIGURATOR_PLAN', 'game-custom'), 'max_ram_gb' => (int) env('ONHOST_GAME_MAX_RAM_GB', 64), 'max_vcpu' => (int) env('ONHOST_GAME_MAX_VCPU', 8)],
    ],

    'console' => [
        'relay_key' => env('ONHOST_CONSOLE_RELAY_KEY', ''),  // shared secret of the websocket console relay (GET /console/ws/{token})
        'relay_url' => env('ONHOST_CONSOLE_RELAY_URL', ''),  // wss://relay.onhost.cz — added to connect-src of the CSP
        'token_ttl_seconds' => 120,
    ],

    'oncall' => [ // on-call escalation behind the operational events (audit §5q-1)
        'provider' => env('ONHOST_ONCALL_PROVIDER', ''),                        // pagerduty | opsgenie | webhook | '' = console only
        'secret_ref' => env('ONHOST_ONCALL_SECRET_REF', 'env://ONHOST_ONCALL'), // {routing_key} | {api_key, base_url?} | {url, secret}
        'inbound_secret' => env('ONHOST_ONCALL_INBOUND_SECRET', ''),            // PagerDuty webhook signature secret, or X-ONhost-Oncall-Token for the others
        'escalate_after_minutes' => (int) env('ONHOST_ONCALL_ESCALATE_MINUTES', 15),
        'max_escalations' => (int) env('ONHOST_ONCALL_MAX_ESCALATIONS', 2),
        'events' => ['platform.queue.stalled' => 'hot', 'platform.mail.failing' => 'hot', 'integration.down' => 'hot', 'node.bmc.alert' => 'hot', 'sla.burn_rate' => 'hot', 'capacity.forecast.low' => 'warn', 'incident.opened' => 'warn', 'integration.prereqs.regressed' => 'warn', 'platform.queue.backlog' => 'warn'],
        'resolves' => ['platform.mail.recovered' => 'platform.mail.failing', 'integration.recovered' => 'integration.down', 'integration.prereqs.recovered' => 'integration.prereqs.regressed', 'incident.resolved' => 'incident.opened'],
    ],

    'observability' => [ // error tracking and traces (audit §5q-2); both off without an endpoint
        'sentry_dsn' => env('SENTRY_DSN', ''),
        'otlp_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', ''),     // http://otel-collector:4318 — spans go to {endpoint}/v1/traces
        'otlp_headers' => env('OTEL_EXPORTER_OTLP_HEADERS', ''),       // "Authorization=Bearer x,X-Scope-OrgID=onhost"
        'service_name' => env('OTEL_SERVICE_NAME', 'onhost-control-plane'),
        'trace_url' => env('ONHOST_TRACE_URL', ''), // https://grafana.example/explore?left={"datasource":"tempo","queries":[{"query":"{trace_id}"}]} — console links (audit §5r-2)
        'environment' => env('APP_ENV', 'production'),
    ],

    'storage' => [ // files behind evidence and data exports (audit §5q-4)
        'disk' => env('ONHOST_FILES_DISK', 'local'),                                 // local | s3 (config/filesystems.php)
        'signed_ttl_minutes' => (int) env('ONHOST_FILES_SIGNED_TTL', 15),          // life of a signed download link
        'clamav' => ['host' => env('ONHOST_CLAMAV_HOST', ''), 'port' => (int) env('ONHOST_CLAMAV_PORT', 3310), 'enforce' => (bool) env('ONHOST_CLAMAV_ENFORCE', true), 'timeout_seconds' => (int) env('ONHOST_CLAMAV_TIMEOUT', 30)], // virus scan of uploads (audit §5r-4); off without a host
        'evidence_retention_months' => (int) env('ONHOST_EVIDENCE_RETENTION_MONTHS', 36),
    ],

    'turnstile' => [ // Cloudflare Turnstile on registration and checkout (audit §5q-6); off without keys
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret' => env('TURNSTILE_SECRET_KEY', ''),
        'enforce_register' => (bool) env('ONHOST_TURNSTILE_ENFORCE_REGISTER', true), // a missing/failed check refuses registration; checkout only scores it
        'enforce_forms' => (bool) env('ONHOST_TURNSTILE_ENFORCE_FORMS', true),       // public contact/support, tender and partner application forms of guests (audit §5r-6)
        'timeout_seconds' => 3,
    ],

    'metrics' => [
        'token' => env('ONHOST_METRICS_TOKEN', ''),            // bearer/query token for GET /metrics (Prometheus)
        'allow_ips' => array_filter(explode(',', (string) env('ONHOST_METRICS_ALLOW_IPS', '127.0.0.1,::1'))),
    ],

    'content' => [
        'changelog_year' => 2026,
        'domain_page_tlds' => ['cz', 'com', 'eu'], // the TLDs the domain page leads with
        'game_product' => 'game',                 // the product the game configurator sells
        'plans_product' => 'web-hosting',
        // marketing comparison table (prototype onhost-data.js compare(cs)); columns: parameter, start, pro, scale
        'compare' => [
            'cs' => [['vCPU', '2', '6', '16'], ['RAM', '4 GB', '16 GB', '64 GB'], ['NVMe disk', '80 GB', '320 GB', '1 TB'], ['Přenos dat', 'neomezeně', 'neomezeně', 'neomezeně'], ['Zálohy', '7 dní', '30 dní', '30 dní + replika'], ['CI/CD runner', '—', '1× shared', '2× dedikovaný'], ['Anti-DDoS', '100 Gbps', '600 Gbps', '1,2 Tbps'], ['SLA', '99,9 %', '99,95 %', '99,99 %'], ['Reakce podpory', '8 h', '30 min', '10 min / telefon']],
            'en' => [['vCPU', '2', '6', '16'], ['RAM', '4 GB', '16 GB', '64 GB'], ['NVMe disk', '80 GB', '320 GB', '1 TB'], ['Transfer', 'unlimited', 'unlimited', 'unlimited'], ['Backups', '7 days', '30 days', '30 days + replica'], ['CI/CD runner', '—', '1× shared', '2× dedicated'], ['Anti-DDoS', '100 Gbps', '600 Gbps', '1.2 Tbps'], ['SLA', '99.9%', '99.95%', '99.99%'], ['Support response', '8 h', '30 min', '10 min / phone']],
        ],
        'tender_docs' => [
            'cs' => [
                ['Technická specifikace', 'Konfigurace, lokality, parametry sítě a zálohování v podobě, kterou lze vložit do zadávací dokumentace.'],
                ['Smlouva a SLA příloha', 'Návrh smlouvy s dostupností, sankcemi a výpovědními podmínkami. SLA příloha je bez výjimek, a proto je kratší než obvykle.'],
                ['Doklady o zpracování dat', 'Jmenovitý seznam zpracovatelů, umístění dat a doba jejich uchování. Prodej dat třetím stranám nemáme a nikdy mít nebudeme.'],
                ['Reference s kontaktem', 'Reference, u kterých vám dáme jméno a telefon na člověka, který u nás skutečně hostuje — po jeho souhlasu, ne bez něj.'],
                ['Cenová nabídka bez skrytých položek', 'Zřízení, migrace i ukončení za nula. Co bude stát víc, je v nabídce vypsané zvlášť, ne v poznámce pod čarou.'],
                ['Seznam toho, co nesplňujeme', 'Ten posíláme taky. Dodavatel, který v tendru tvrdí, že splňuje všechno, to buď nečetl, nebo to neplní.'],
            ],
            'en' => [
                ['Technical specification', 'Configuration, locations, network and backup parameters in a form you can paste into the tender documents.'],
                ['Contract and SLA annex', 'A draft contract with uptime, penalties and termination terms. The SLA annex has no exceptions, which is why it is shorter than usual.'],
                ['Data processing evidence', 'A named list of processors, where data sits and how long it is kept. We do not sell data to third parties and never will.'],
                ['References with a contact', 'References where we give you the name and phone number of somebody who actually hosts with us — with their consent, not without it.'],
                ['A quote with no hidden lines', 'Setup, migration and termination at zero. Anything that costs more is listed separately, not in a footnote.'],
                ['The list of what we do not meet', 'We send that too. A supplier claiming to meet everything either did not read the tender or will not deliver it.'],
            ],
        ],
    ],

    'status' => [
        'maintenance_lead_hours' => 48,
        'custom_cname' => env('ONHOST_STATUS_CNAME'), // the CNAME target of the customers' own status hosts (audit §5k-3); empty = the portal host
        'components' => [
            'web-cz1' => 'Web Hosting CZ1', 'managed' => 'Managed Hosting', 'apps-cz1' => 'Apps CZ1', 'cloud-cz1' => 'Cloud CZ1',
            'games-cz1' => 'Games CZ1', 'dns' => 'DNS', 'domains' => 'Domains/Registrar', 'mail' => 'Mail',
            'payments' => 'Payments', 'portal' => 'Customer Portal/API', 'ai' => 'AI',
        ],
    ],
];
