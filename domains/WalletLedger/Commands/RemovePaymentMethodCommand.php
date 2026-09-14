<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** payload: payment_method_id — forget a stored card (audit §5f-1); the automatic top-up falls back to a notice */
final class RemovePaymentMethodCommand extends OrganizationCommand
{
    public function permission(): ?string
    {
        return 'billing.wallet.topup';
    }

    public function name(): string
    {
        return 'wallet.payment_method.remove';
    }
}
