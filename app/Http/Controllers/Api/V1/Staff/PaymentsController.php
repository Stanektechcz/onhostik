<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Payments\BankStatementImporter;
use Onhost\Domain\Payments\Commands\BankCommand;
use Onhost\Domain\Payments\Commands\PaymentRefundCommand;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Money\Money;

/**
 * Finance → bank transfers: pending bank-transfer intents (proformas and top-ups waiting for money), the statement
 * lines received so far, manual recording of a line from another bank's statement and the Fio API download.
 *
 * G6: the refund of an order payment to its source on a consumer's withdrawal (the consumer did not agree to credit), the list
 * of refunds (`state=pending`: bank payouts still to send) and the confirmation that a bank payout was sent.
 */
final class PaymentsController extends ApiController
{
    public function bank(Request $request, BankStatementImporter $importer): JsonResponse
    {
        $this->api->authorize($request, 'billing.reconcile', CommandScope::global());

        return $this->ok($importer->overview((int) min(200, max(1, (int) $request->query('limit', 50)))));
    }

    public function recordBankLine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'], 'currency' => ['nullable', 'in:CZK,EUR'], 'variable_symbol' => ['nullable', 'string', 'max:20'],
            'external_id' => ['nullable', 'string', 'max:190'], 'counterparty' => ['nullable', 'string', 'max:190'], 'message' => ['nullable', 'string', 'max:250'],
            'booked_at' => ['nullable', 'date'], 'account' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->dispatch(new BankCommand($this->idempotencyKey($request, 'bank.line.record'), ['op' => 'bank.line.record', 'line' => $data]), $this->api->context($request), 201);
    }

    public function syncBank(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return $this->dispatch(new BankCommand($this->idempotencyKey($request, 'bank.sync'), ['op' => 'bank.sync', 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null]), $this->api->context($request));
    }

    /** G6: refunds of payments, newest first; `state=pending` are the bank payouts finance still has to send and confirm. */
    public function refunds(Request $request, PaymentService $payments): JsonResponse
    {
        $this->api->authorize($request, 'staff.billing.read', CommandScope::global());
        $state = (string) $request->query('state', '');
        if ($state !== '' && ! in_array($state, ['pending', 'succeeded', 'failed'], true)) {
            $state = 'pending';
        }

        return $this->ok(['rows' => $payments->refunds($state, (int) min(200, max(1, (int) $request->query('limit', 100)))),
            'pending' => PaymentRefund::query()->where('state', 'pending')->count(), 'can_refund' => $this->api->can($request, 'billing.refund.execute', CommandScope::global())]);
    }

    /** G6: the payment of an order back to its source on a consumer's withdrawal, with a credit note of the order's document. */
    public function refund(Request $request, string $payment): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'], 'sent_at' => ['required', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:250']]);
        $intent = PaymentIntent::query()->findOrFail($payment);
        $amount = Money::decimal((string) $data['amount'], (string) $intent->currency);

        return $this->dispatch(new PaymentRefundCommand($this->idempotencyKey($request, 'payments.refund:'.$intent->id), [
            'op' => 'refund.withdrawal', 'payment_id' => $intent->id, 'organization_id' => $intent->organization_id, 'amount_minor' => $amount->minor, 'currency' => $amount->currency->value,
            'sent_at' => (string) $data['sent_at'], 'reason' => $data['reason'],
        ]), $this->api->context($request, null, $data['reason']));
    }

    /** G6: finance sent the bank payout of a pending refund (`reference`: the bank's payment reference); the customer is told. */
    public function confirmRefund(Request $request, string $refund): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'min:2', 'max:120'], 'reason' => ['required', 'string', 'min:5', 'max:250']]);
        $row = PaymentRefund::query()->findOrFail($refund);

        return $this->dispatch(new PaymentRefundCommand($this->idempotencyKey($request, 'payments.refund.confirm:'.$row->id), ['op' => 'refund.confirm', 'refund_id' => $row->id, 'reference' => $data['reference']]), $this->api->context($request, null, $data['reason']));
    }
}
