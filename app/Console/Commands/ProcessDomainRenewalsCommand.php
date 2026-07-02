<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Provisioning\Jobs\RenewDomainJob;
use App\Domains\Provisioning\Models\DomainRegistration;
use Illuminate\Console\Command;

/**
 * Auto-renews domains that expire within 7 days and have auto_renew=true.
 * Safe to re-run daily — the job itself is idempotent (90d dedup window).
 */
class ProcessDomainRenewalsCommand extends Command
{
    protected $signature   = 'domains:process-renewals';
    protected $description = 'Dispatch renewal jobs for domains expiring within 7 days with auto_renew=true';

    public function handle(): int
    {
        $cutoff     = now()->addDays(7)->toDateString();
        $dispatched = 0;

        DomainRegistration::query()
            ->where('auto_renew', true)
            ->whereDate('expires_at', '<=', $cutoff)
            ->each(function (DomainRegistration $domain) use (&$dispatched): void {
                RenewDomainJob::dispatch($domain->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} domain renewal job(s).");

        return self::SUCCESS;
    }
}
