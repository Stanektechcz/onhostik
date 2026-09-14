<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * The environment template is the staging/production contract: every ONhost variable config/onhost.php reads has a line
 * in .env.example (an operator filling the file never has to grep the config), the file names no secret value, and the
 * platform secrets the template references (pager, Discord, AI, …) can be filled with onhost:secrets:set.
 */

it('lists every ONhost variable the configuration reads and carries no secret values', function () {
    $example = (string) file_get_contents(base_path('.env.example'));
    preg_match_all('/^([A-Z0-9_]+)=([^#\n]*)/m', $example, $m); // inline comments are not values
    $have = array_map('trim', array_combine($m[1], $m[2]));
    preg_match_all("/env\\('((?:ONHOST|COMGATE|GOPAY|STRIPE|WEDOS|TURNSTILE|OTEL|PEPPOL|OIDC|EINVOICE|AI_)[A-Z0-9_]+)'/", (string) file_get_contents(base_path('config/onhost.php')), $keys);
    $missing = array_values(array_diff(array_unique($keys[1]), array_keys($have), ['ONHOST_DEMO_MODE', 'ONHOST_OUTBOX_EAGER', 'ONHOST_DEFAULT_LOCALE', 'ONHOST_PORTAL_URL', 'ONHOST_PLATFORM_BACKUP_FILES_ROOT', 'ONHOST_PG_BIN', 'ONHOST_ORDER_RISK_DISPOSABLE', 'ONHOST_ORDER_RISK_FIRST_CZK', 'ONHOST_ORDER_RISK_FIRST_EUR', 'WEDOS_ENDPOINT', 'WEDOS_PUBLIC_PRICELIST_URL', 'WEDOS_CLOCK_MAX_OFFSET_SECONDS', 'COMGATE_BASE_URL', 'COMGATE_SECRET_REF', 'GOPAY_BASE_URL', 'PEPPOL_BASE_URL', 'AI_OPENAI_EMBEDDING_MODEL', 'AI_ANTHROPIC_BASE_URL']));
    expect($missing)->toBe([]);
    expect($have)->toMatchArray(['APP_ENV' => 'staging', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'ONHOST_SECRETS_DRIVER' => 'db', 'ONHOST_STAFF_MFA_REQUIRED' => 'true', 'COMGATE_TEST' => 'true', 'WEDOS_TEST_MODE' => 'true', 'ONHOST_UI_DEMO' => 'false']);
    foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'MAIL_PASSWORD', 'COMGATE_SECRET', 'AWS_SECRET_ACCESS_KEY', 'ONHOST_METRICS_TOKEN', 'ONHOST_CONSOLE_RELAY_KEY', 'ONHOST_ONCALL_INBOUND_SECRET', 'TURNSTILE_SECRET_KEY', 'ONHOST_BANK_FIO_TOKEN'] as $secret) {
        expect(trim((string) ($have[$secret] ?? 'MISSING')))->toBe('', "{$secret} must be present and empty");
    }
    expect($example)->not->toMatch('/ptl[ac]_[A-Za-z0-9]{20,}/')->not->toContain('DISCORD_BOT_TOKEN=');
});

it('stores a platform secret behind a db:// reference from the command line and never prints the value', function () {
    config()->set('onhost.secrets.driver', 'db');
    $this->artisan('onhost:secrets:set env://ONHOST_ONCALL routing_key')->expectsOutputToContain('Only db:// references')->assertExitCode(1);
    $this->artisan('onhost:secrets:set db://oncall/pager Routing-Key')->expectsOutputToContain('must look like')->assertExitCode(1);
    $this->artisan('onhost:secrets:set db://oncall/pager routing_key')->expectsQuestion('Value for routing_key', 'pd-routing-key-123')->expectsOutputToContain('Stored routing_key in db://oncall/pager; keys now: routing_key')->assertExitCode(0);
    $this->artisan('onhost:secrets:set db://oncall/pager base_url')->expectsQuestion('Value for base_url', 'https://events.pagerduty.com')->expectsOutputToContain('keys now: routing_key, base_url')->assertExitCode(0);
    expect(app(SecretStore::class)->read(SecretRef::parse('db://oncall/pager')))->toBe(['routing_key' => 'pd-routing-key-123', 'base_url' => 'https://events.pagerduty.com']);
    expect((string) DB::table('secrets')->where('name', 'oncall/pager')->value('payload'))->not->toContain('pd-routing-key-123');
    expect(json_encode(DB::table('audit_events')->where('action', 'secret.set')->get()->toArray()))->not->toContain('pd-routing-key-123')->toContain('routing_key');
    $this->artisan('onhost:secrets:set db://oncall/pager base_url --remove')->expectsOutputToContain('Removed base_url')->assertExitCode(0);
    expect(app(SecretStore::class)->read(SecretRef::parse('db://oncall/pager')))->toBe(['routing_key' => 'pd-routing-key-123']);
});
