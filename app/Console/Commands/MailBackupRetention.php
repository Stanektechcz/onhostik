<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Services\Mail\MailboxBackupPolicy;
use Onhost\Domain\Services\Mail\MailboxBackupRetentionPlan;
use Throwable;

/**
 * Owner decision 3 (TASK-0024): the only way a mail plan's mailbox backup retention reaches mailboxes that existed before
 * the rule `mail.backup_retention` was switched on. Without `--apply` it only reads the panels and lists every mailbox of
 * every live mail service with what it keeps now and what its plan sells. `--apply` (rule on) asks for one operation per
 * service that is behind; a downgrade — the panel would delete the extra copies — needs `--allow-prune` as well.
 * Mailboxes that are not provably the platform's are listed as `not_ours` and never written.
 */
final class MailBackupRetention extends Command
{
    protected $signature = 'onhost:mail:backup-retention
        {--apply : ask for the change; without it only the list}
        {--allow-prune : also apply fewer copies than a mailbox keeps now (the panel deletes the difference)}
        {--service=* : only these service ids}
        {--chunk=200 : services read per chunk; every one is listed}';

    protected $description = 'List (default) or apply the mail plans\' mailbox backup retention (backup_days) on the platform\'s own mailboxes';

    public function handle(MailboxBackupRetentionPlan $plan): int
    {
        $apply = (bool) $this->option('apply');
        $allowPrune = (bool) $this->option('allow-prune');
        $ruleOn = MailboxBackupPolicy::ruleOn();
        if ($apply && ! $ruleOn) {
            $this->error('The rule '.MailboxBackupPolicy::RULE.' is off: switch it on in Automations first (new mailboxes then get the retention too). Nothing was changed.');

            return self::FAILURE;
        }
        $review = $plan->review(array_values(array_filter(array_map('strval', (array) $this->option('service')))), (int) $this->option('chunk'), $allowPrune);
        $this->table(['service', 'mailbox', 'now', 'plan', 'status'], array_map(fn (array $r) => array_values($r), $review['rows']));

        $behind = array_values(array_filter($review['services'], fn (array $s) => $s['apply']));
        $requested = 0;
        foreach ($apply ? $behind : [] as $service) {
            try {
                $plan->apply($service['service'], (int) $service['target'], $allowPrune);
                $requested++;
            } catch (Throwable $e) { // a busy service is asked again on the next run
                $this->warn('service '.$service['service']->id.': '.mb_substr($e->getMessage(), 0, 120));
            }
        }
        $sum = fn (string $key) => array_sum(array_column($review['services'], $key));
        $held = $allowPrune ? 0 : $sum('prune');
        $this->info(sprintf('%d service(s) behind · %d mailbox(es) to set · %d downgrade(s) held%s · %d not ours · %d unreadable · rule %s: %s · %s',
            count($behind), $sum('set'), $held, $held > 0 ? ' (they would prune '.$sum('pruning').' existing copies; --allow-prune applies them)' : '',
            $sum('foreign'), $sum('unreadable'), MailboxBackupPolicy::RULE, $ruleOn ? 'on' : 'off',
            $apply ? $requested.' operation(s) requested' : 'nothing was changed'));

        return self::SUCCESS;
    }
}
