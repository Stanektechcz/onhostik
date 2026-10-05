<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Tax\VatPayerMode;

/**
 * The seller's VAT mode (G2): shows the mode in force — the declared one (ONHOST_VAT_PAYER), the legal entity's and the tax rules'.
 * Exit 1 while the declaration and the legal entity disagree.
 *
 * It never switches the mode (security review of #106): the switch decides whether every following document states VAT, so it is
 * a CRITICAL action of a person — a member of finance with a fresh step-up and a second person who approves it —
 * `POST /v1/staff/tax/vat-payer-mode`. The command line ran it as the system, with neither; `--apply` is refused now.
 */
final class VatPayerModeCommand extends Command
{
    protected $signature = 'onhost:vat:payer-mode {--apply : Refused — the mode is switched by finance with a step-up and four eyes}';

    protected $description = 'Show the VAT payer mode in force (the switch is a staff action with four eyes: POST /v1/staff/tax/vat-payer-mode)';

    public function handle(VatPayerMode $mode): int
    {
        if ($this->option('apply')) {
            $this->error('The VAT mode is not switched from the command line: a member of finance switches it with a step-up and a second person — POST /v1/staff/tax/vat-payer-mode {payer, reason} (docs/runbooks/vat-payer-mode.md).');

            return self::FAILURE;
        }
        $report = $mode->report();
        $this->line($report['detail']);
        if (! $report['consistent']) {
            $this->warn($report['remedy']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
