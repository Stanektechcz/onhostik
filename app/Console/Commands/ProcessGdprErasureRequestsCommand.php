<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GDPR Article 17 — Right to Erasure.
 *
 * Anonymises accounts that requested deletion more than 30 days ago
 * (configurable via --grace-days). Invoices and audit logs are retained
 * for legal / accounting obligations (Art. 17(3)(b)).
 *
 * What is anonymised:
 *   - users.name        → "[Deleted]"
 *   - users.email       → "deleted_{id}@deleted.invalid"
 *   - users.password    → bcrypt(random)
 *   - customers.email/phone/company_name/registration_number/vat_number/company_name
 *   - customer billing addresses: street → "[GDPR]", city → "[GDPR]", zip → "00000"
 *
 * What is retained:
 *   - Invoices (legal obligation, 10 years)
 *   - Order amounts (accounting)
 *   - Activity log (anonymised user reference)
 */
final class ProcessGdprErasureRequestsCommand extends Command
{
    protected $signature = 'gdpr:erase-requested
        {--grace-days=30 : Days after deletion_requested_at before erasure runs}
        {--dry-run : Show accounts that would be erased without modifying data}';

    protected $description = 'Anonymise accounts that requested GDPR deletion after the grace period';

    public function handle(): int
    {
        $graceDays = (int) ($this->option('grace-days') ?? 30);
        $dryRun    = (bool) $this->option('dry-run');
        $cutoff    = Carbon::now()->subDays($graceDays);

        $users = User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', $cutoff)
            ->where('name', '!=', '[Deleted]')
            ->with('customer')
            ->get();

        if ($users->isEmpty()) {
            $this->info('GDPR: no accounts pending erasure.');
            return self::SUCCESS;
        }

        $erased = 0;

        foreach ($users as $user) {
            if ($dryRun) {
                $this->line("[dry-run] Would erase user #{$user->id} ({$user->email}), requested at {$user->deletion_requested_at}");
                $erased++;
                continue;
            }

            DB::transaction(function () use ($user): void {
                $anonymisedEmail = "deleted_{$user->id}@deleted.invalid";

                $user->update([
                    'name'                    => '[Deleted]',
                    'email'                   => $anonymisedEmail,
                    'password'                => bcrypt(bin2hex(random_bytes(16))),
                    'remember_token'          => null,
                    'two_factor_secret'       => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                    'deletion_requested_at'   => null,
                ]);

                // Revoke Sanctum tokens
                $user->tokens()->delete();

                $customer = $user->customer;

                if ($customer !== null) {
                    $customer->update([
                        'email'               => $anonymisedEmail,
                        'phone'               => null,
                        'company_name'        => null,
                        'registration_number' => null,
                        'vat_number'          => null,
                    ]);

                    $customer->addresses()->update([
                        'street' => '[GDPR]',
                        'city'   => '[GDPR]',
                        'zip'    => '00000',
                    ]);
                }

                activity('gdpr')
                    ->withProperties(['user_id' => $user->id, 'erased_at' => now()->toISOString()])
                    ->log('gdpr.erasure_completed');
            });

            $erased++;
            $this->info("Erased user #{$user->id}");
        }

        $this->info("GDPR erasure: {$erased} " . ($dryRun ? 'would be erased (dry-run)' : 'accounts erased') . '.');

        return self::SUCCESS;
    }
}
