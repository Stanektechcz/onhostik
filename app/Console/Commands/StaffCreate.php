<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Commands\StaffAccountCommand;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * The first staff account of an installation (go-live checklist): a user with a global role, the password from a
 * hidden prompt. Further staff are invited from the console; MFA is enrolled by the person on first sign-in.
 *
 * TASK-0041 (P0-16 red team, owning task TASK-0037): the account is made through the command bus (`identity.staff.create`).
 * A further approver — a role holding `iam.approval.decide` while somebody already decides approvals — waits the time lock:
 * the first run opens it and makes nothing, the approvers are told and may cancel it, a repeat after the lock makes the account.
 */
final class StaffCreate extends Command
{
    protected $signature = 'onhost:staff:create {email} {--name=} {--role=platform_owner : A global role key from RoleCatalog (platform_owner, sre, support_manager, …)} {--stdin : Read the password from standard input}';

    protected $description = 'Create a staff account with a global role (hidden password prompt; a further approver waits the time lock)';

    public function handle(CommandBus $bus): int
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
        try {
            $result = $bus->dispatch(new StaffAccountCommand('staff.create:'.Str::ulid(), ['email' => $email, 'name' => $this->option('name') ?: null, 'role' => $role, 'password' => $password]), CommandContext::system('cli:staff:create'));
        } catch (DomainError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (! (bool) data_get($result, 'created')) {
            $this->warn("{$role} decides approvals, and somebody already does: a further approver is not made at once (time lock, TASK-0037).");
            $this->line('Request '.data_get($result, 'approval_id').' is on the approvals page; the approvers were told and may cancel it.');
            $this->line('Repeat this command with the same e-mail, name and role after '.data_get($result, 'not_before').' to make the account.');

            return self::FAILURE;
        }
        $this->info("Staff account {$email} created with the role {$role}; sign in at ".rtrim((string) config('app.url'), '/').'/sprava and enrol MFA.');

        return self::SUCCESS;
    }
}
