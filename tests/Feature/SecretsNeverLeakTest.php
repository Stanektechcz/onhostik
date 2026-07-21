<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Models\DomainTransferRequest;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Log;

/**
 * Standing constraint (audit H121): gateway secrets, API keys and passwords
 * must NEVER reach a log file, an activity record, a serialized model or a
 * stored gateway response.
 *
 * This is a guard test, not a demonstration — it exists so a future change
 * that starts logging a payload fails here instead of in production.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Distinctive values that must never show up anywhere. */
const LEAK_CANARIES = [
    'comgate_secret' => 'CANARY-COMGATE-SECRET-9f3a',
    'api_key'        => 'CANARY-APIKEY-7b21',
    'password'       => 'CANARY-PASSWORD-4d0e',
    'auth_code'      => 'CANARY-AUTHCODE-1c55',
];

/** Capture everything written to the log during the callback. */
function captureLog(callable $callback): string
{
    $captured = '';

    Log::listen(function ($message) use (&$captured): void {
        $captured .= $message->message . ' ' . json_encode($message->context);
    });

    $callback();

    return $captured;
}

// ── Integration clients ───────────────────────────────────────────────────────

it('never logs a mailbox password from the aaPanel client', function (): void {
    $setting = IntegrationSetting::firstOrCreate(
        ['provider' => 'aapanel'],
        ['label' => 'AAPanel', 'mock_mode' => true, 'dry_run' => true],
    );

    $log = captureLog(function () use ($setting): void {
        (new AapanelClient($setting))->createMailbox('x.cz', 'info', LEAK_CANARIES['password']);
    });

    expect($log)->not->toContain(LEAK_CANARIES['password']);
});

it('never logs a domain transfer auth code from the WEDOS client', function (): void {
    $setting = IntegrationSetting::firstOrCreate(
        ['provider' => 'wedos'],
        ['label' => 'WEDOS', 'mock_mode' => true, 'dry_run' => true],
    );

    $log = captureLog(function () use ($setting): void {
        (new WedosWapiClient($setting))->transferDomain('x.cz', LEAK_CANARIES['auth_code']);
    });

    expect($log)->not->toContain(LEAK_CANARIES['auth_code']);
});

// ── Persisted models ──────────────────────────────────────────────────────────

it('never exposes integration credentials through serialization', function (): void {
    $setting = IntegrationSetting::firstOrCreate(
        ['provider' => 'comgate'],
        ['label' => 'Comgate', 'mock_mode' => true, 'dry_run' => true],
    );

    $setting->forceFill(['credentials' => [
        'secret'  => LEAK_CANARIES['comgate_secret'],
        'api_key' => LEAK_CANARIES['api_key'],
    ]])->save();

    $serialized = json_encode($setting->fresh()->toArray()) ?: '';

    expect($serialized)->not->toContain(LEAK_CANARIES['comgate_secret'])
        ->and($serialized)->not->toContain(LEAK_CANARIES['api_key']);
});

it('stores integration credentials encrypted at rest', function (): void {
    $setting = IntegrationSetting::firstOrCreate(
        ['provider' => 'comgate'],
        ['label' => 'Comgate', 'mock_mode' => true, 'dry_run' => true],
    );

    $setting->forceFill(['credentials' => ['secret' => LEAK_CANARIES['comgate_secret']]])->save();

    $raw = \Illuminate\Support\Facades\DB::table('integration_settings')
        ->where('id', $setting->id)
        ->value('credentials');

    expect((string) $raw)->not->toContain(LEAK_CANARIES['comgate_secret']);
});

it('never exposes a domain transfer auth code through serialization', function (): void {
    $transfer = DomainTransferRequest::create([
        'customer_id' => customerUser()->customer->id,
        'domain_name' => 'x.cz',
        'auth_code'   => LEAK_CANARIES['auth_code'],
        'status'      => 'pending',
    ]);

    expect(json_encode($transfer->toArray()) ?: '')->not->toContain(LEAK_CANARIES['auth_code']);
});

// ── Source-level guard ────────────────────────────────────────────────────────

it('never logs a raw credential value anywhere in the codebase', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        // Blank out string literals first: a log MESSAGE may legitimately
        // mention "credentials" (e.g. "credentials undecryptable"); what must
        // never happen is passing the credential VALUE itself.
        $withoutStrings = (string) preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/s', "''", $source);

        // Now only variable/property reads remain: ->credentials, $credentials,
        // ->api_key, $password …
        $pattern = '/Log::(info|debug|warning|error|critical)\s*\([^;]*'
            . '(->\s*(credentials|api_key|secret|auth_code|password)|\$(credentials|apiKey|secret|authCode|password))/i';

        if (preg_match($pattern, $withoutStrings)) {
            $offenders[] = str_replace(app_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([], "Credential values are logged in:\n" . implode("\n", $offenders));
});

it('keeps the aaPanel real-writes gate closed by default', function (): void {
    // Standing constraint: real writes stay OFF unless deliberately enabled.
    expect((bool) config('provisioning.aapanel.allow_real_writes', false))->toBeFalse();
});
