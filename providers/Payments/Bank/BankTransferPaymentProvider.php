<?php

declare(strict_types=1);

namespace Onhost\Providers\Payments\Bank;

use Illuminate\Http\Request;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Contracts\PaymentProvider;

/**
 * First-class internal provider (§63.2): the customer transfers money with a
 * variable symbol; statement lines are imported (CSV/API) and matched by
 * PaymentService::matchBankLine. There is no redirect and no webhook.
 */
final class BankTransferPaymentProvider implements PaymentProvider
{
    public static function providerKey(): string
    {
        return 'bank';
    }

    public function supportedMethods(): array
    {
        return ['bank_transfer', 'qr'];
    }

    public function createPaymentIntent(Money $amount, array $input): array
    {
        $reference = preg_replace('/\D/', '', (string) $input['reference']) ?: (string) crc32((string) $input['reference']);
        $vs = substr($reference, 0, 10);
        $bank = (array) config('onhost.payments.bank');
        $instructions = [
            'iban' => $bank['iban'] ?? '', 'bic' => $bank['bic'] ?? '', 'account_number' => $bank['account_number'] ?? '',
            'variable_symbol' => $vs, 'amount' => $amount->toDecimal(), 'currency' => $amount->currency->value,
            'message' => (string) $input['description'],
            'qr_spd' => sprintf('SPD*1.0*ACC:%s*AM:%s*CC:%s*X-VS:%s*MSG:%s', $bank['iban'] ?? '', $amount->toDecimal(), $amount->currency->value, $vs, rawurlencode(mb_substr((string) $input['description'], 0, 60))),
        ];

        return ['provider_id' => 'vs:'.$vs.':'.$amount->minor, 'redirect_url' => null, 'state' => 'PENDING', 'raw' => ['instructions' => $instructions]];
    }

    public function getPaymentStatus(string $providerId): array
    {
        $intent = PaymentIntent::query()->where('provider', 'bank')->where('provider_id', $providerId)->first();
        if ($intent === null) {
            return ['state' => 'PENDING', 'amount' => null, 'paid_at' => null, 'method' => 'bank', 'raw' => []];
        }
        $matched = BankStatementLine::query()->where('matched_payment_intent_id', $intent->id)->first();

        return ['state' => $matched ? 'PAID' : 'PENDING', 'amount' => $matched ? Money::minor($matched->amount_minor, $matched->currency) : null, 'paid_at' => $matched?->booked_at?->toISOString(), 'method' => 'bank', 'raw' => ['bank_line' => $matched?->external_id]];
    }

    public function capture(string $providerId, ?Money $amount = null): array
    {
        return ['state' => 'not_applicable'];
    }

    public function cancel(string $providerId): array
    {
        return ['state' => 'CANCELLED'];
    }

    public function refund(string $providerId, Money $amount, string $idempotencyKey, ?string $reason = null): array
    {
        // Refunds to bank accounts are executed manually by finance (payout order); the record stays pending until confirmed.
        return ['provider_refund_id' => null, 'state' => 'pending', 'raw' => ['manual_payout_required' => true, 'reason' => $reason]];
    }

    public function verifyWebhook(Request $request): array
    {
        throw new DomainError('webhook_unsupported', 'Bank transfers have no webhook; import statements instead.', 404);
    }

    public function reconcile(string $periodStart, string $periodEnd): array
    {
        return BankStatementLine::query()->where('state', 'matched')->whereBetween('booked_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])->get()
            ->map(fn (BankStatementLine $line) => [
                'provider_id' => (string) PaymentIntent::query()->find($line->matched_payment_intent_id)?->provider_id,
                'amount' => Money::minor($line->amount_minor, $line->currency), 'fee' => null, 'settled_at' => $line->booked_at->toDateString(), 'state' => 'settled', 'reference' => $line->variable_symbol,
            ])->all();
    }
}
