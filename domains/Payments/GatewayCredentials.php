<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Throwable;

/**
 * Whether a payment provider holds what it needs to talk to its gateway, asked before a scheduled job calls it: a missing secret
 * is a plain RuntimeException of the store and must not fail the job every run (TASK-0222). The same check as the W6 sweep's
 * (`PendingPaymentSweep::missingCredentials`): Comgate says itself which of its two credentials it holds (`credentialState`, H-R8),
 * the others are asked of the store. The bank has no gateway: its settlements are the statement lines imported here.
 */
final class GatewayCredentials
{
    /** Providers that need no credentials: their reconciliation reads only this platform's own records. */
    private const NONE_NEEDED = ['bank'];

    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly SecretStore $secrets,
    ) {}

    /** Null when the provider can be called; otherwise a reason fit for a log line (never a value or a message of the store). */
    public function missing(string $key): ?string
    {
        if (in_array($key, self::NONE_NEEDED, true)) {
            return null;
        }
        try {
            $provider = $this->providers->get($key);
            if (method_exists($provider, 'credentialState')) {
                $state = (array) $provider->credentialState();

                return ! empty($state['merchant']) && ! empty($state['secret']) ? null : 'incomplete';
            }

            return $this->secrets->exists(SecretRef::parse((string) config("onhost.payments.{$key}.secret_ref"))) ? null : 'absent';
        } catch (Throwable $e) {
            return get_class($e);
        }
    }
}
