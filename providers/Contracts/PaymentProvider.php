<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

use Illuminate\Http\Request;
use Onhost\Platform\Money\Money;

/**
 * Blueprint §63.1. Verified provider status/webhook is payment truth, never a
 * success-page redirect. Comgate is first-class for CZ/SK; GoPay/Stripe/bank are
 * pluggable.
 */
interface PaymentProvider
{
    public static function providerKey(): string;

    /** @return list<string> e.g. card, apple_pay, google_pay, bank_transfer, qr */
    public function supportedMethods(): array;

    /**
     * @param  array{reference:string,description:string,email:string,return_url:string,cancel_url:string,pending_url:string,method?:string,locale?:string,country?:string,idempotency_key:string,metadata?:array<string,mixed>}  $input
     * @return array{provider_id:string,redirect_url:string|null,state:string,raw:array<string,mixed>}
     */
    public function createPaymentIntent(Money $amount, array $input): array;

    /** @return array{state:string,amount:Money|null,paid_at:string|null,method:string|null,raw:array<string,mixed>} */
    public function getPaymentStatus(string $providerId): array;

    public function capture(string $providerId, ?Money $amount = null): array;

    public function cancel(string $providerId): array;

    /** @return array{provider_refund_id:string|null,state:string,raw:array<string,mixed>} */
    public function refund(string $providerId, Money $amount, string $idempotencyKey, ?string $reason = null): array;

    /**
     * Verify and parse a callback. MUST throw on invalid signature / unverifiable
     * source; MUST return a stable event id for deduplication.
     *
     * @return array{event_id:string,provider_id:string,state:string,amount:Money|null,raw:array<string,mixed>}
     */
    public function verifyWebhook(Request $request): array;

    /** @return list<array{provider_id:string,amount:Money,fee:Money|null,settled_at:string,state:string,reference:string|null}> */
    public function reconcile(string $periodStart, string $periodEnd): array;
}
