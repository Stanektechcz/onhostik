<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Whether a card payment can be started at all (C-R10e, J4 / TASK-0155): the card gateway is configured with its credentials.
 * Before the owner stores the Comgate merchant account (I-R10: Comgate issues it only after reviewing the site), a card order
 * must not reach the gateway with empty credentials and come back as a "provider error": the public cart does not offer the card
 * (`ONHOST_DATA.cardPayments()`), and an order that asks for it anyway is refused up front with a plain sentence that names the
 * way that works — a bank transfer. No gateway name, code or detail reaches the customer.
 */
final class CardPayments
{
    public function __construct(private readonly PaymentProviderRegistry $providers) {}

    public function available(?string $provider = null): bool
    {
        try {
            $gateway = $this->providers->get($provider);
        } catch (Throwable) {
            return false;
        }
        if (! method_exists($gateway, 'credentialState')) {
            return true; // a provider without stored credentials (a test double, the bank) needs none
        }
        $state = (array) $gateway->credentialState();

        return ($state['merchant'] ?? false) === true && ($state['secret'] ?? false) === true && ($state['error'] ?? null) === null;
    }

    /** Refuses a card payment that cannot be started; a no-op when it can. */
    public function assertAvailable(?string $provider = null): void
    {
        if (! $this->available($provider)) {
            throw self::refusal();
        }
    }

    public static function refusal(): DomainError
    {
        $cs = ! str_starts_with((string) app()->getLocale(), 'en');

        return new DomainError('payment_card_unavailable', $cs
            ? 'Platba kartou teď není k dispozici. Objednávku dokončete bankovním převodem: zálohovou fakturu s QR platbou dostanete hned.'
            : 'Card payment is not available right now. Finish the order by bank transfer: you get the pro forma invoice with a QR payment at once.', 409, ['field' => 'payment.mode']);
    }
}
