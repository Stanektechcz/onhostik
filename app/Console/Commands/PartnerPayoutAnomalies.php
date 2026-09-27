<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Partners\Commands\PartnerCommand;
use Onhost\Domain\Partners\PayoutAnomalies;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * The payouts still open when TASK-0040 lands, looked at for what the two holes may have made (permission program IF-14,
 * D13, §8 row 41): a payout standing for more than it allocated (the balance race, audit P1) and a bank transfer to an IBAN
 * that was not the partner's confirmed account when it was asked for (the request wrote it, audit P2).
 *
 * The dry run (the default) lists them, prints the digest of that list and changes nothing. `--apply --digest=<it>` freezes
 * exactly those — after the owner said go, and only while a new scan still finds that very list (review round 1) — through
 * the command bus (`partner.payout.freeze`): a frozen payout is neither approved nor paid until finance rejects it or releases
 * it with a reason. Nothing else is frozen: a partner's first bank transfer has nothing to compare with and is only listed,
 * and the payouts of every other partner go on as before (freezing all of them was rejected, program §8 row 41).
 */
final class PartnerPayoutAnomalies extends Command
{
    protected $signature = 'onhost:partners:payout-anomalies
        {--apply : freeze the listed anomalous payouts (after the dry run was reviewed and the owner said go)}
        {--digest= : with --apply, the list digest the reviewed dry run printed; nothing is frozen when the list changed since}
        {--dry-run : list only (the default)}';

    protected $description = 'List open partner payouts above their allocated commissions or to an unconfirmed IBAN; --apply freezes only those (TASK-0040)';

    private const WHAT = [
        'amount_above_allocated' => 'stands for more than the commissions allocated to it',
        'iban_changed' => 'goes to an IBAN that was not the confirmed account when it was asked for',
    ];

    public function handle(PayoutAnomalies $anomalies, CommandBus $bus): int
    {
        $found = $anomalies->scan();
        $this->line("Open payouts checked: {$found['checked']}.");
        if ($found['anomalies'] === []) {
            $this->info('No anomalous open payout.');
        } else {
            $this->table(['payout', 'number', 'state', 'amount', 'allocated', 'account', 'confirmed before', 'why'], array_map(fn (array $row) => [
                $row['payout_id'], $row['number'], $row['state'], $row['amount']->format(), isset($row['allocated']) ? $row['allocated']->format() : '—',
                $row['account'] ?? '—', implode(', ', $row['confirmed'] ?? []) ?: '—', implode(', ', $row['kinds']),
            ], $found['anomalies']));
            foreach (self::WHAT as $kind => $sentence) {
                $this->line("{$kind}: {$sentence}.");
            }
        }
        if ($found['unconfirmed'] !== []) {
            $this->line('');
            $this->line('Listed for a look, NOT frozen — the partner\'s first bank transfer, nothing earlier to compare its IBAN with. It is not paid as it is: finance rejects it (the owner sets the account, the partner asks again), or holds it, confirms the account with the partner and releases it with a reason and confirms_account (a second person signs that release):');
            $this->table(['payout', 'number', 'state', 'amount', 'account'], array_map(fn (array $row) => [$row['payout_id'], $row['number'], $row['state'], $row['amount']->format(), $row['account'] ?? '—'], $found['unconfirmed']));
        }
        if ($found['released'] !== []) {
            $this->line('Released by finance with a reason, not frozen again: '.implode(', ', array_column($found['released'], 'number')).'.');
        }
        if ($found['unknown'] !== []) {
            $this->warn(count($found['unknown']).' payout(s) mix currencies with their commissions and cannot be compared: '.implode(', ', array_column($found['unknown'], 'number')).'.');
        }
        $digest = PayoutAnomalies::digest($found['anomalies']);
        $this->line("List digest: {$digest}");
        if (! (bool) $this->option('apply')) {
            $this->info("Nothing was changed. After the list was reviewed and the owner said go, freeze exactly these with --apply --digest={$digest}.");

            return self::SUCCESS;
        }
        // review round 1: the owner's go covered the dry-run list, so --apply freezes that list and nothing a later scan added
        $seen = trim((string) $this->option('digest'));
        if ($seen === '') {
            $this->error('Nothing was frozen: --apply needs --digest=<the digest the reviewed dry run printed>.');

            return self::FAILURE;
        }
        if (! hash_equals($digest, $seen)) {
            $this->error("Nothing was frozen: the list changed since the dry run (digest {$seen}, now {$digest}). Review the list above and run again with its digest.");

            return self::FAILURE;
        }

        return $this->freeze($bus, $found['anomalies']);
    }

    /** @param list<array<string,mixed>> $rows */
    private function freeze(CommandBus $bus, array $rows): int
    {
        $failed = 0;
        foreach ($rows as $row) {
            $reason = 'Anomalie výplaty (onhost:partners:payout-anomalies): '.implode(', ', $row['kinds']);
            try {
                $bus->dispatch(new PartnerCommand('payout.freeze:anomalies:'.$row['payout_id'], ['op' => 'payout.freeze', 'payout_id' => $row['payout_id'], 'reason' => $reason]), CommandContext::system('cli:partners:payout-anomalies'));
                $this->info("{$row['number']}: frozen");
            } catch (DomainError $e) {
                $failed++;
                $this->error("{$row['number']}: not frozen — {$e->getMessage()}");
            }
        }
        $total = array_reduce($rows, fn (int $sum, array $row) => $sum + ($row['amount'] instanceof Money ? $row['amount']->minor : 0), 0);
        $this->line(count($rows).' payout(s) frozen or already frozen'.($rows !== [] ? ' ('.Money::minor($total, $rows[0]['amount']->currency)->format().')' : '').'. Finance rejects each or releases it with a reason (POST /v1/staff/partners/payouts/{id}/unfreeze).');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
