<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Domain\WalletLedger\Commands\AutoTopupCommand;
use Onhost\Domain\WalletLedger\Commands\RemovePaymentMethodCommand;
use Onhost\Domain\WalletLedger\Commands\TopUpWalletCommand;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandScope;

final class WalletController extends ApiController
{
    public function show(Request $request, WalletService $wallets, WalletForecast $forecast, AutoTopup $autoTopup): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.wallet.read', CommandScope::organization($organization->id));
        $currency = strtoupper((string) $request->query('currency', $organization->currency));
        $balances = $wallets->balances($organization, $currency);
        $holds = WalletHold::query()->where('organization_id', $organization->id)->where('state', 'active')->orderByDesc('created_at')->limit(50)->get()->map(fn (WalletHold $h) => ['id' => $h->id, 'amount' => $h->amount(), 'purpose' => $h->purpose, 'priority' => $h->priority, 'reference' => [$h->reference_type, $h->reference_id], 'expires_at' => $h->expires_at?->toIso8601String()])->all();

        return response()->json(['data' => ['currency' => $currency, 'balances' => $balances, 'spendable' => $wallets->spendable($organization, $currency), 'forecast' => $forecast->forecast($organization, $currency), 'auto_topup' => $autoTopup->settings($organization), 'holds' => $holds, 'min_topup' => config("onhost.billing.min_topup.{$currency}")]]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.wallet.read', CommandScope::organization($organization->id));
        $query = LedgerTransaction::query()->where('organization_id', $organization->id);

        return $this->api->paginate($request, $query, fn (LedgerTransaction $t) => ['id' => $t->id, 'kind' => $t->kind, 'currency' => $t->currency, 'description' => $t->description, 'reference' => [$t->reference_type, $t->reference_id], 'at' => $t->created_at?->toIso8601String(), 'reversed_by' => $t->reversed_by_id ?? null]);
    }

    /** The automatic top-up policy (audit §5e-2): threshold, amount, daily cap, monthly limit — charged only through a provider that keeps payment methods. */
    public function autoTopup(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'threshold' => ['nullable', 'numeric', 'min:0'], 'amount' => ['nullable', 'numeric', 'min:1'], 'max_per_day' => ['nullable', 'integer', 'min:1', 'max:10'], 'monthly_limit' => ['nullable', 'numeric', 'min:1'], 'payment_method_id' => ['nullable', 'string', 'max:40']]);

        return $this->dispatch(new AutoTopupCommand($organization->id, $this->idempotencyKey($request, 'wallet.auto_topup'), $data), $this->api->context($request, $organization));
    }

    public function topup(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1'], 'currency' => ['nullable', 'in:CZK,EUR'], 'provider' => ['nullable', 'string', 'max:20'], 'method' => ['nullable', 'string', 'max:40'], 'return_urls' => ['nullable', 'array'], 'save_method' => ['nullable', 'boolean']]);

        return $this->dispatch(new TopUpWalletCommand($organization->id, $this->idempotencyKey($request, 'wallet.topup'), $data), $this->api->context($request, $organization), 201);
    }

    /** Stored payment methods (audit §5f-1): the cards kept for automatic top-ups — never their tokens. */
    public function paymentMethods(Request $request, PaymentService $payments, AutoTopup $autoTopup): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'billing.wallet.read', CommandScope::organization($organization->id));

        return response()->json(['data' => $payments->methods($organization), 'auto_topup' => $autoTopup->settings($organization)]);
    }

    public function removePaymentMethod(Request $request, string $method): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new RemovePaymentMethodCommand($organization->id, $this->idempotencyKey($request, "wallet.payment_method.remove:{$method}"), ['payment_method_id' => $method]), $this->api->context($request, $organization));
    }
}
