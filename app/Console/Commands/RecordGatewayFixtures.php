<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Redaction\Redactor;

/**
 * Records sandbox payloads as contract fixtures (audit §5i-6): creates one small payment on the configured gateway
 * (sandbox credentials, never production) and stores the creation and status payloads under tests/Contract/fixtures,
 * redacted like the provider call log, so the recorded contract tests never drift from what the gateway really
 * answers. Nothing is charged: the payment is left unpaid and expires on the gateway.
 */
final class RecordGatewayFixtures extends Command
{
    protected $signature = 'onhost:fixtures:record {gateway : comgate|stripe|gopay} {--amount=100 : Amount in the currency\'s major units} {--currency=CZK} {--out= : Fixture directory (default tests/Contract/fixtures)}';

    protected $description = 'Record a gateway sandbox exchange (create + status) as redacted contract fixtures';

    public function handle(PaymentProviderRegistry $providers, Redactor $redactor): int
    {
        $gateway = (string) $this->argument('gateway');
        if (app()->environment('production')) {
            $this->error('Fixtures are recorded from sandboxes, never from a production installation.');

            return self::FAILURE;
        }
        try {
            $provider = $providers->get($gateway);
        } catch (\Throwable $e) {
            $this->error("Gateway {$gateway} is not configured: ".$e->getMessage());

            return self::FAILURE;
        }
        $amount = Money::decimal((string) $this->option('amount'), (string) $this->option('currency'));
        $reference = 'fixture-'.now()->format('YmdHis');
        $base = rtrim((string) config('onhost.portal_url'), '/');
        $created = $provider->createPaymentIntent($amount, ['reference' => $reference, 'description' => 'ONhost fixture', 'email' => 'fixture@example.test', 'return_url' => "{$base}/fixture/ok", 'cancel_url' => "{$base}/fixture/cancel", 'pending_url' => "{$base}/fixture/pending", 'idempotency_key' => $reference, 'method' => 'card', 'save_method' => true, 'locale' => 'cs', 'country' => 'CZ']);
        $status = $provider->getPaymentStatus((string) $created['provider_id']);
        $dir = rtrim((string) ($this->option('out') ?: base_path('tests/Contract/fixtures')), '/\\').DIRECTORY_SEPARATOR.$gateway;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Cannot create {$dir}");

            return self::FAILURE;
        }
        $write = function (string $name, array $payload) use ($dir, $redactor): string {
            $path = $dir.DIRECTORY_SEPARATOR.$name.'.json';
            file_put_contents($path, json_encode(['recorded_at' => now()->toIso8601String(), 'payload' => $redactor->redact($payload)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

            return $path;
        };
        $this->line('recorded '.$write('recorded_create', (array) ($created['raw'] ?? [])));
        $this->line('recorded '.$write('recorded_status', (array) ($status['raw'] ?? [])));
        $this->info("{$gateway}: provider id {$created['provider_id']} · state {$status['state']} — the payment stays unpaid and expires on the gateway");

        return self::SUCCESS;
    }
}
