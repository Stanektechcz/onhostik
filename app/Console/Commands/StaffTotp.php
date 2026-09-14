<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Identity\StepUp\Totp;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/**
 * TOTP enrolment for a staff account from the terminal: with `ONHOST_STAFF_MFA_REQUIRED=true` a staff user cannot sign
 * in before MFA exists, and the in-app enrolment needs a session — the first account on a fresh installation would be
 * locked out. Step 1 prints the secret and the otpauth URI (scan or type it into the authenticator), step 2 confirms
 * with a code and prints the recovery codes once.
 */
final class StaffTotp extends Command
{
    protected $signature = 'onhost:staff:totp {email} {--code= : The 6-digit code from the authenticator to confirm the enrolment} {--reset : Start over with a new secret}';

    protected $description = 'Enrol (and confirm) the authenticator of a staff account from the terminal';

    public function handle(StepUpService $stepUp, Totp $totp, AuditRecorder $audit): int
    {
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null || ! $user->is_staff) {
            $this->error('No staff account with that e-mail.');

            return self::FAILURE;
        }
        $context = CommandContext::system('cli:staff:totp');
        if ($this->option('code') === null) {
            if ($user->hasTotp() && ! $this->option('reset')) {
                $this->info('The authenticator is already confirmed; use --reset to replace it.');

                return self::SUCCESS;
            }
            $secret = Totp::generateSecret();
            $user->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => null])->save();
            $audit->record($context, 'me.totp.enroll', 'succeeded', ['via' => 'cli'], 'user', $user->id);
            $this->line('Secret (type it into the authenticator, or scan the URI as a QR code):');
            $this->line('  '.$secret);
            $this->line('  '.$totp->provisioningUri($secret, $user->email, (string) config('app.name', 'ONhost')));
            $this->info("Then confirm: php artisan onhost:staff:totp {$user->email} --code=<6 digits>");

            return self::SUCCESS;
        }
        if ($user->totp_secret === null) {
            $this->error('Start the enrolment first (run without --code).');

            return self::FAILURE;
        }
        if (! $stepUp->verifyTotp($user, (string) $this->option('code'))) {
            $this->error('The code does not match — check the device clock and try the next code.');

            return self::FAILURE;
        }
        $user->forceFill(['totp_confirmed_at' => now()])->save();
        $codes = $stepUp->issueRecoveryCodes($user);
        $audit->record($context, 'me.totp.confirm', 'succeeded', ['via' => 'cli'], 'user', $user->id);
        $this->info("Authenticator confirmed for {$user->email}. Recovery codes (shown once, keep them safe):");
        foreach ($codes as $code) {
            $this->line('  '.$code);
        }

        return self::SUCCESS;
    }
}
