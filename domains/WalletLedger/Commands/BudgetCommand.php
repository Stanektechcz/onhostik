<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** payload: limit (decimal; 0 removes it), hard?, alert_thresholds?[], max_single_service? — the organization's monthly spending limit (H30) */
final class BudgetCommand extends OrganizationCommand
{
    public function permission(): string
    {
        return 'billing.budget.manage';
    }

    public function name(): string
    {
        return 'billing.budget.set';
    }
}
