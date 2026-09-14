<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/**
 * The first staff account of an installation (go-live checklist): a user with a global role, the password from a
 * hidden prompt. Further staff are invited from the console; MFA is enrolled by the person on first sign-in.
 */
final class StaffCreate extends Command
{
    protected $signature = 'onhost:staff:create {email} {--name=} {--role=platform_owner : A global role key from RoleCatalog (platform_owner, sre, support_manager, …)} {--stdin : Read the password from standard input}';

    protected $description = 'Create a staff account with a global role (hidden password prompt)';

    public function handle(AuditRecorder $audit): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->option('role');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid e-mail address is required.');

            return self::FAILURE;
        }
        if (! array_key_exists($role, RoleCatalog::all())) {
            $this->error("Unknown role {$role}; global roles: ".implode(', ', array_keys(array_filter(RoleCatalog::all(), fn ($r) => ($r['scope'] ?? 'global') === 'global'))));

            return self::FAILURE;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error("{$email} already exists.");

            return self::FAILURE;
        }
        $password = $this->option('stdin') ? trim((string) stream_get_contents(STDIN)) : (string) $this->secret('Password (at least 12 characters)');
        if (strlen($password) < 12) {
            $this->error('The password must have at least 12 characters.');

            return self::FAILURE;
        }
        $user = User::query()->create(['name' => (string) ($this->option('name') ?: explode('@', $email)[0]), 'email' => $email, 'password' => $password, 'locale' => 'cs', 'timezone' => 'Europe/Prague', 'is_staff' => true, 'state' => 'active', 'email_verified_at' => now()]);
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
        $audit->record(CommandContext::system('cli:staff:create'), 'identity.staff.created', 'succeeded', ['email' => $email, 'role' => $role], 'user', $user->id);
        $this->info("Staff account {$email} created with the role {$role}; sign in at ".rtrim((string) config('app.url'), '/').'/sprava and enrol MFA.');

        return self::SUCCESS;
    }
}
