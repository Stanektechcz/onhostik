<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\LedgerAccount;
use Onhost\Platform\Money\Money;

/**
 * H3 (phase H, TASK-0121): money owed to a customer that nobody is sending. A bank payout finance cancelled (returned by the bank,
 * a wrong account — PaymentService::cancelRefund) leaves its credit note and the amount in `liability:refund_payable:<provider>`;
 * the next payout of the same amount uses that note (OrderPaymentRefunds::unpaidCreditNote). Until it comes, the only trace was a
 * ledger balance. This lists every credit note whose last payout was cancelled and that no pending or paid refund carries, and the
 * payable's balance per account (which also holds the payouts that are on their way). Read-only: paying it out stays the G6 flow.
 */
final class RefundsNotPaidOut
{
    private const PAYABLE_PREFIX = 'liability:refund_payable:';

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return array{rows:list<array<string,mixed>>, total:array<string,int>, payable:array<string,int>}
     */
    public function report(int $limit = 500): array
    {
        $cancelled = PaymentRefund::query()->where('state', 'cancelled')->whereNotNull('credit_note_id')->orderByDesc('updated_at')->get();
        $carried = PaymentRefund::query()->whereIn('credit_note_id', $cancelled->pluck('credit_note_id')->unique()->values()->all())
            ->whereIn('state', ['pending', 'succeeded'])->pluck('credit_note_id')->map(fn ($id) => (string) $id)->flip();
        $stranded = $cancelled->reject(fn (PaymentRefund $r) => $carried->has((string) $r->credit_note_id))->unique('credit_note_id')->take(max(1, $limit))->values();

        $intents = PaymentIntent::query()->whereIn('id', $stranded->pluck('payment_intent_id')->all())->get()->keyBy('id');
        $notes = Invoice::query()->whereIn('id', $stranded->pluck('credit_note_id')->all())->get(['id', 'number'])->keyBy('id');
        $orders = Order::query()->whereIn('id', $intents->where('reference_type', 'order')->pluck('reference_id')->filter()->all())->pluck('number', 'id');

        $rows = [];
        $total = [];
        foreach ($stranded as $refund) {
            $intent = $intents->get($refund->payment_intent_id);
            $currency = strtoupper((string) $refund->getRawOriginal('currency'));
            $total[$currency] = ($total[$currency] ?? 0) + (int) $refund->amount_minor;
            $rows[] = [
                'refund_id' => $refund->id, 'payment_id' => $refund->payment_intent_id, 'organization_id' => $intent?->organization_id, 'provider' => $intent?->provider,
                'order' => $intent !== null && $intent->reference_type === 'order' ? $orders->get((string) $intent->reference_id) : null,
                'credit_note' => $notes->get((string) $refund->credit_note_id)?->number, 'amount' => Money::minor((int) $refund->amount_minor, $currency),
                'cancelled_at' => $refund->updated_at?->toIso8601String(), 'days' => $refund->updated_at === null ? null : (int) floor($refund->updated_at->diffInDays(now(), true)),
            ];
        }
        ksort($total);

        return ['rows' => $rows, 'total' => $total, 'payable' => $this->payable()];
    }

    /** The balance of every refund payable account that holds something. @return array<string,int> */
    private function payable(): array
    {
        $out = [];
        foreach (LedgerAccount::query()->where('code', 'like', self::PAYABLE_PREFIX.'%')->orderBy('code')->pluck('code') as $code) {
            $currency = substr((string) $code, (int) strrpos((string) $code, ':') + 1);
            $balance = $this->ledger->balance((string) $code, $currency)->minor;
            if ($balance !== 0) {
                $out[(string) $code] = $balance;
            }
        }

        return $out;
    }
}
