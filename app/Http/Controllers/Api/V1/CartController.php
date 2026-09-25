<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\WithdrawalPolicy;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Platform\Errors\DomainError;

/**
 * Server-side cart (handoff §4.3): anonymous carts are keyed by an opaque `X-Cart-Token`,
 * signed-in users get one cart per user; `quote` locks prices/tax into a Quote the order references.
 */
final class CartController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($this->cart($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array', 'max:50'], 'items.*.product_key' => ['required', 'string', 'max:60'], 'items.*.plan_key' => ['nullable', 'string', 'max:60'], 'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:100'], 'items.*.config' => ['nullable', 'array'],
            'items.*.line_id' => ['nullable', 'string', 'max:20'], 'items.*.period' => ['nullable', 'in:month,year'], // an explicit period on a plan-change line switches the billing period
            'commit_months' => ['nullable', 'integer', 'in:1,12,24'], 'currency' => ['nullable', 'in:CZK,EUR'], 'promo_code' => ['nullable', 'string', 'max:40'],
        ]);
        $cart = $this->cart($request);
        $cart->forceFill(['items' => array_values($data['items']), 'commit_months' => (int) ($data['commit_months'] ?? $cart->commit_months ?? 1), 'currency' => $data['currency'] ?? $cart->currency, 'promo_code' => array_key_exists('promo_code', $data) ? $data['promo_code'] : $cart->promo_code, 'expires_at' => now()->addDays(30)])->save();

        return response()->json(['data' => $this->present($cart)]);
    }

    public function promo(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'max:40']]);
        $cart = $this->cart($request);
        $code = isset($data['code']) && trim((string) $data['code']) !== '' ? strtoupper(trim((string) $data['code'])) : null;
        if ($code !== null) {
            $promo = PromoCode::query()->where('code', $code)->first();
            if ($promo === null || ! $promo->isUsable()) {
                throw new DomainError('promo_invalid', 'Slevový kód neplatí.', 422, ['field' => 'promo']);
            }
        }
        $cart->forceFill(['promo_code' => $code])->save();

        return response()->json(['data' => $this->present($cart)]);
    }

    /** Lock prices, tax and document versions into a quote (valid for a limited time). */
    public function quote(Request $request, QuoteService $quotes): JsonResponse
    {
        $cart = $this->cart($request);
        if (($cart->items ?? []) === []) {
            throw new DomainError('cart_empty', 'Košík je prázdný.', 422, ['field' => 'items']);
        }
        $organization = $request->user() ? $this->api->organization($request, false) : null;
        $customer = $request->validate(['country' => ['nullable', 'string', 'size:2'], 'customer_class' => ['nullable', 'in:b2c,b2b'], 'vat_status' => ['nullable', 'string', 'max:20']]);
        // a guest may say where they are and whether they buy as a business, for an estimate; a signed-in organization's tax
        // treatment comes from the organization alone (QuoteService enforces the same), and nobody claims a verified VAT number here
        $customer = $organization !== null
            ? ['country' => $organization->country ?? 'CZ', 'customer_class' => $organization->customer_class ?? 'b2c', 'vat_status' => $organization->vat_status ?? 'unknown', 'ip_country' => null]
            : ['country' => strtoupper((string) ($customer['country'] ?? 'CZ')), 'customer_class' => $customer['customer_class'] ?? 'b2c', 'vat_status' => 'unknown', 'ip_country' => null];
        $quote = $quotes->quote((array) $cart->items, $cart->currency ?? 'CZK', $customer, (int) ($cart->commit_months ?? 1), $cart->promo_code, $organization, (string) $request->query('locale', 'cs'));

        return response()->json(['data' => [
            'quote_id' => $quote->id, 'valid_until' => $quote->valid_until?->toIso8601String(), 'currency' => $quote->currency, 'lines' => $quote->lines, 'subtotal' => $quote->subtotal_minor, 'discount' => $quote->discount_minor, 'tax' => $quote->tax_minor, 'total' => $quote->total_minor, 'renewal_total' => $quote->renewal_total_minor, 'versions' => $quote->versions,
            'required_documents' => $organization ? app(CheckoutService::class)->requiredDocuments($quote, $organization) : null,
            'withdrawal_notice' => WithdrawalPolicy::checkoutNotice((array) $quote->lines, (string) $customer['customer_class']), // TASK-0025: a consumer hears before the order that a registered domain cannot be withdrawn
        ]]);
    }

    private function cart(Request $request): Cart
    {
        $user = $request->user();
        if ($user !== null) {
            return Cart::query()->firstOrCreate(['user_id' => $user->getAuthIdentifier(), 'state' => 'open'], ['currency' => 'CZK', 'commit_months' => 1, 'items' => [], 'expires_at' => now()->addDays(30)]);
        }
        $token = $request->headers->get('X-Cart-Token');
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $token)) {
            $token = Str::random(40);
        }

        return Cart::query()->firstOrCreate(['session_token' => $token, 'state' => 'open'], ['currency' => 'CZK', 'commit_months' => 1, 'items' => [], 'expires_at' => now()->addDays(30)]);
    }

    private function present(Cart $cart): array
    {
        return ['id' => $cart->id, 'token' => $cart->session_token, 'items' => $cart->items ?? [], 'commit_months' => $cart->commit_months, 'currency' => $cart->currency, 'promo_code' => $cart->promo_code, 'expires_at' => $cart->expires_at?->toIso8601String()];
    }
}
