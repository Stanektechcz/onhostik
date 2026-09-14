<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** payload: amount (decimal string), currency, provider?, method?, return_urls{success,pending,failed} */
final class TopUpWalletCommand extends OrganizationCommand
{
    public function permission(): ?string
    {
        return 'billing.wallet.topup';
    }

    public function name(): string
    {
        return 'wallet.topup.init';
    }
}
