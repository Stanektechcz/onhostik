<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Illuminate\Contracts\Container\Container;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\PaymentProvider;
use Onhost\Providers\Payments\Bank\BankTransferPaymentProvider;
use Onhost\Providers\Payments\Comgate\ComgatePaymentProvider;
use Onhost\Providers\Payments\GoPay\GoPayPaymentProvider;
use Onhost\Providers\Payments\Stripe\StripePaymentProvider;

/** Resolves payment adapters by key; ONhost never depends on one gateway (§63.2). */
final class PaymentProviderRegistry
{
    /** @var array<string, class-string<PaymentProvider>> */
    private array $adapters = [
        'comgate' => ComgatePaymentProvider::class,
        'gopay' => GoPayPaymentProvider::class,
        'stripe' => StripePaymentProvider::class,
        'bank' => BankTransferPaymentProvider::class,
    ];

    /** @var array<string, PaymentProvider> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    public function get(?string $key = null): PaymentProvider
    {
        $key ??= (string) config('onhost.payments.default', 'comgate');
        if (! isset($this->adapters[$key])) {
            throw new DomainError('payment_provider_unknown', "Payment provider {$key} is not configured.", 422);
        }

        return $this->instances[$key] ??= $this->container->make($this->adapters[$key]);
    }

    /** @param class-string<PaymentProvider> $class */
    public function register(string $key, string $class): void
    {
        $this->adapters[$key] = $class;
        unset($this->instances[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->adapters);
    }
}
