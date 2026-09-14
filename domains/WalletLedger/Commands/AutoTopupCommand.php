<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** payload: enabled, threshold (decimal), amount (decimal), max_per_day, monthly_limit (decimal), payment_method_id? — the customer's automatic top-up policy */
final class AutoTopupCommand extends OrganizationCommand
{
    public function permission(): ?string
    {
        return 'billing.wallet.topup';
    }

    public function name(): string
    {
        return 'wallet.auto_topup.set';
    }
}
