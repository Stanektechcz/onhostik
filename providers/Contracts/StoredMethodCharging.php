<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

use Onhost\Platform\Money\Money;

/**
 * A payment provider that can charge a payment method the customer stored earlier (card token, mandate) without the
 * customer present — what automatic top-ups need. Providers without it (bank, gateways not yet enabled for
 * recurring) simply do not implement the interface and the platform asks the customer to top up instead.
 *
 * The platform never sees a card: the token is whatever the gateway hands back for the settled initial payment
 * (`storedMethodFrom`), stored encrypted, and handed straight back to the same gateway (`chargeStoredMethod`).
 */
interface StoredMethodCharging
{
    /** Can this provider keep and charge methods right now (merchant configured, recurring enabled)? */
    public function storedMethodsAvailable(): bool;

    /**
     * Charges the stored method; returns the provider's payment id and state so the platform can create the intent
     * and settle it through the usual webhook/status path.
     *
     * @param  array{description?:string, reference?:string, idempotency_key?:string, email?:string}  $options
     * @return array{provider_id:string, state:string, raw?:array<string,mixed>}
     */
    public function chargeStoredMethod(string $methodId, Money $amount, array $options = []): array;

    /**
     * The reusable token and the card facts to show the customer, read from the provider's payloads of the settled
     * initial payment (its creation response merged with its last status). Null when the payment cannot be charged
     * again — the gateway did not tokenise it — in which case nothing is stored.
     *
     * @param  array<string,mixed>  $raw
     * @return array{token:string, brand?:?string, last4?:?string, expires?:?string}|null
     */
    public function storedMethodFrom(string $providerId, array $raw): ?array;
}
