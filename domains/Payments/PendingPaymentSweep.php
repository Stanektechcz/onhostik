<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Illuminate\Support\Facades\Log;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Platform\Commands\CommandContext;
use Throwable;

/**
 * W6 (TASK-0228): a card payment is settled by the gateway's notification or by the payer's return; when both are lost, the
 * money was taken and the intent stayed pending for good — nothing asked the provider again. This sweep re-reads the status
 * of card intents still open and applies it through `PaymentService::syncFromProvider`, which is idempotent and settles, fails
 * or cancels exactly as the webhook does.
 *
 * Only intents older than the grace period (the webhook and the return go first) and younger than the cut-off (older ones are
 * finance's, through the merchant portal); at most one batch per run, the intent asked about longest ago first. One intent the provider cannot answer
 * does not stop the others. A gateway without credentials (staging has none for Comgate) is skipped with a warning — a
 * missing secret is a plain RuntimeException of the store and must not fail the scheduled job every run (TASK-0222).
 */
final class PendingPaymentSweep
{
    /** A payment the provider may still report paid, failed or cancelled. */
    public const OPEN_STATES = [S::CREATED, S::PENDING_CUSTOMER, S::AUTHENTICATION_REQUIRED, S::PROCESSING];

    /** Providers without a status API: bank transfers are paired by statement lines (BankStatementImporter). */
    private const NEVER = ['bank'];

    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentProviderRegistry $providers,
        private readonly GatewayCredentials $credentials,
    ) {}

    /** @return array{checked:int, settled:int, failed:int, open:int, errors:int, skipped:list<string>} */
    public function run(?int $limit = null): array
    {
        $summary = ['checked' => 0, 'settled' => 0, 'failed' => 0, 'open' => 0, 'errors' => 0, 'skipped' => []];
        $ready = [];
        foreach ($this->providerKeys() as $key) {
            $missing = $this->credentials->missing($key);
            if ($missing === null) {
                $ready[] = $key;

                continue;
            }
            $summary['skipped'][] = $key;
            Log::warning('payments sweep: provider skipped, no credentials', ['provider' => $key, 'error' => $missing]);
        }
        if ($ready === []) {
            return $summary;
        }
        $context = CommandContext::system('sweep:payments');
        foreach ($this->due($ready, $limit) as $intent) {
            $summary['checked']++;
            try {
                $result = $this->payments->syncFromProvider($intent, $context);
            } catch (Throwable $e) {
                $summary['errors']++;
                Log::warning('payments sweep: intent not re-read', ['intent' => $intent->id, 'provider' => $intent->provider, 'error' => get_class($e)]);

                continue;
            } finally {
                // the rotation: an intent asked about goes to the back of the queue, answered or not (see due())
                PaymentIntent::query()->whereKey($intent->id)->update(['updated_at' => now()]);
            }
            $bucket = match ($result) {
                'settled', 'already_settled', 'succeeded', 'partially_refunded', 'refunded' => 'settled', // paid now, here or concurrently
                'failed', 'canceled' => 'failed',
                default => 'open', // still pending at the provider, or an amount mismatch handed to finance
            };
            $summary[$bucket]++;
        }

        return $summary;
    }

    /**
     * Open card intents between the grace period and the cut-off, the one asked about longest ago first. Every intent asked about is
     * touched (`updated_at`), so the batch rotates: payments the payer abandoned (they stay pending at the gateway until it expires
     * them) can never fill every batch and keep a newer paid one waiting until the cut-off (W6 review). An intent whose paid amount
     * differed is left out: syncFromProvider opened a reconciliation item for it already, and asking again would open one every run;
     * finance resolves the item and re-reads it by hand (`POST /v1/payments/{intent}/sync`).
     *
     * @param  list<string>  $providers
     * @return iterable<PaymentIntent>
     */
    private function due(array $providers, ?int $limit): iterable
    {
        $limit = max(1, $limit ?? (int) config('onhost.payments.sweep.batch', 50));
        $grace = max(0, (int) config('onhost.payments.sweep.grace_minutes', 10));
        $maxAge = max(1, (int) config('onhost.payments.sweep.max_age_hours', 72));

        return PaymentIntent::query()
            ->whereIn('provider', $providers)
            ->whereIn('state', self::OPEN_STATES)
            ->whereNotNull('provider_id')->where('provider_id', '!=', '')
            ->where('created_at', '<=', now()->subMinutes($grace))
            ->where('created_at', '>=', now()->subHours($maxAge))
            ->where(fn ($q) => $q->whereNull('raw')->orWhereNull('raw->amount_mismatch'))
            ->orderBy('updated_at')->orderBy('created_at')->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @return list<string> */
    private function providerKeys(): array
    {
        $known = $this->providers->keys();
        $wanted = array_map('strval', (array) config('onhost.payments.sweep.providers', []));

        return array_values(array_filter(array_unique($wanted), fn (string $key) => in_array($key, $known, true) && ! in_array($key, self::NEVER, true)));
    }
}
