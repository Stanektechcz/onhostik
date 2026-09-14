<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/**
 * The last steps before the first customer (go-live checklist): removes the development accounts the
 * `DevAccountSeeder` created, writes the legal entity from the production environment (`ONHOST_LEGAL_NAME`,
 * `ONHOST_ICO`, `ONHOST_BANK_IBAN`, …), caches configuration and routes, and ends with the doctor — every remaining
 * FAIL is printed so nothing is forgotten. Nothing here touches customer data.
 */
final class ProductionPrepare extends Command
{
    public const DEV_ACCOUNTS = ['demo@onhost.cz', 'agentura@onhost.cz', 'admin@onhost.cz', 'noc@onhost.cz', 'finance@onhost.cz', 'support@onhost.cz'];

    protected $signature = 'onhost:production:prepare {--purge-dev-accounts : Delete the DevAccountSeeder accounts and their role bindings} {--legal : Write the legal entity from ONHOST_LEGAL_* / ONHOST_BANK_* variables} {--cache : php artisan config:cache, route:cache, event:cache} {--yes : Do not ask}';

    protected $description = 'Prepare the installation for production: purge development accounts, seed the legal entity from the environment, cache, and run the doctor';

    public function handle(AuditRecorder $audit): int
    {
        $context = CommandContext::system('cli:production:prepare');
        if ($this->option('purge-dev-accounts')) {
            $users = User::query()->whereIn('email', self::DEV_ACCOUNTS)->get();
            if ($users->isEmpty()) {
                $this->info('No development accounts present.');
            } elseif ($this->option('yes') || $this->confirm('Delete '.$users->count().' development account(s): '.$users->pluck('email')->implode(', ').'?')) {
                foreach ($users as $user) {
                    DB::table('policy_bindings')->where('principal_type', 'user')->where('principal_id', $user->id)->delete();
                    DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();
                    $user->delete();
                    $audit->record($context, 'identity.dev_account.purged', 'succeeded', ['email' => $user->email], 'user', $user->id);
                }
                $this->info('Deleted '.$users->count().' development account(s); their organizations stay (delete them from the console once nothing references them).');
            }
        }
        if ($this->option('legal')) {
            $placeholders = array_keys(array_filter(['ONHOST_LEGAL_NAME' => 'name', 'ONHOST_ICO' => 'ico', 'ONHOST_DIC' => 'dic', 'ONHOST_BANK_IBAN' => 'iban', 'ONHOST_BANK_ACCOUNT' => 'bank_account'], fn ($k) => trim((string) config("onhost.legal_entity.{$k}", '')) === ''));
            if ($placeholders !== []) {
                $this->error('Legal entity variables missing: '.implode(', ', $placeholders).' — the seeder would write placeholders.');

                return self::FAILURE;
            }
            $this->call('db:seed', ['--class' => LegalEntitySeeder::class, '--force' => true]);
            $this->info('Legal entity written from the environment.');
        }
        if ($this->option('cache')) {
            foreach (['config:cache', 'route:cache', 'event:cache'] as $cmd) {
                Artisan::call($cmd);
                $this->line("{$cmd}: ".trim(Artisan::output()));
            }
        }
        $this->line('');
        $this->line('Doctor:');
        $exit = $this->call('onhost:doctor');
        if ($exit !== self::SUCCESS) {
            $this->error('The doctor reports blocking problems — fix them before the first customer.');
        }

        return $exit;
    }
}
