<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Tax\Commands\SetVatPayerModeCommand;
use Onhost\Domain\Tax\VatPayerMode;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * The seller's VAT mode (G2): shows the mode in force — the declared one (ONHOST_VAT_PAYER), the legal entity's and the tax rules'
 * — and with `--apply` writes the declared mode to the legal entity through the CommandBus. Documents already issued keep the
 * mode they were issued in (docs/runbooks/vat-payer-mode.md). Exit 1 while the declared mode and the legal entity disagree.
 */
final class VatPayerModeCommand extends Command
{
    protected $signature = 'onhost:vat:payer-mode {--apply : Write the mode ONHOST_VAT_PAYER declares to the legal entity} {--reason= : Why the mode changes (kept in the legal entity\'s history)}';

    protected $description = 'Show the VAT payer mode, or write the declared mode (ONHOST_VAT_PAYER) to the legal entity';

    public function handle(CommandBus $bus, VatPayerMode $mode): int
    {
        if ($this->option('apply')) {
            $declared = (bool) config('vat.payer', true);
            try {
                $result = $bus->dispatch(new SetVatPayerModeCommand('vat.payer-mode:'.($declared ? 'payer' : 'non-payer').':'.now()->format('YmdHis'), [
                    'payer' => $declared, 'reason' => (string) ($this->option('reason') ?: 'ONHOST_VAT_PAYER='.($declared ? 'true' : 'false')),
                ]), CommandContext::system('cli:vat:payer-mode'));
            } catch (DomainError $e) {
                $this->error("{$e->error}: {$e->getMessage()}");

                return self::FAILURE;
            }
            $this->info(($result['changed'] ? 'Legal entity '.$result['legal_entity'].' is now ' : 'Legal entity '.$result['legal_entity'].' already is ').($result['payer'] ? 'a VAT payer.' : 'not a VAT payer.'));
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
