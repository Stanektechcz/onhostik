<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\AaPanel\AaPanelTenancyGate;

/**
 * aaPanel nodes several customers share (TASK-0034, permission program IF-7 / D11 / P0-03, owner default §10 O1).
 *
 * aaPanel's file API and scheduler run as root and every site runs as the same `www`, so on a node that serves more
 * than one organization a tenant can steer a root write of the in-panel file manager, an upload, an unpack or a shell
 * job through a link it planted into a neighbour's site. That is closed per node rather than engineered around.
 *
 * Without options (a dry run) it lists every such node, the services on it, what closing takes away and whom to tell,
 * and changes nothing. `--apply` closes the listed nodes — to be run after the dry run was reviewed and the customers
 * were told (O1) — through the command bus (`instance.upsert`, HIGH, audited) as the instance option `tenancy.closed`,
 * which the aaPanel adapter reads on every call. `--reopen --instance=<key>` is the way back, one node at a time.
 *
 * "Several customers" is what the platform knows: its own services' organizations. Sites on the node the platform did
 * not create are not counted (historical sites are never touched, and never read here).
 */
final class AaPanelTenancy extends Command
{
    protected $signature = 'onhost:aapanel:tenancy
        {--apply : close the listed shared nodes (after the dry run was reviewed and the customers were told)}
        {--reopen : reopen one closed node (needs --instance)}
        {--instance= : only this aaPanel instance key}
        {--force : with --apply and --instance, close that node although the platform sees one organization on it (historical sites share it)}
        {--dry-run : list only (the default)}';

    /** The name the permission program uses for it (P0-03). */
    protected $aliases = ['operator:aapanel:tenancy'];

    protected $description = 'List (default) or close the aaPanel nodes several customers share to in-panel file writing and shell cron; --reopen reverts one node';

    private const LOSES = 'Closing takes away on the node: the in-panel file manager (browse, read, edit, upload, archive, unpack), site import, PHP settings, one-click apps, git deployments (deploy and rollback), staging copies (create, refresh, push), new or changed shell cron commands and running a job on demand.';

    private const KEEPS = 'Stays: SFTP/FTP, backups, databases, jobs that already run (as the site\'s own user — list old root jobs with onhost:services:cron-confine), pausing and deleting jobs. Restores and migrations onto the node are uploaded and unpacked as the site\'s own shell user (files the backup lacks are kept, not deleted); the final archive on termination is the panel\'s own backup.';

    /** Work accepted while the node was open reaches the adapter after --apply; the adapter refuses it then, but say so. */
    private const QUEUED = 'Before --apply: let the web operations already queued for these nodes finish, or cancel them — after --apply the file API refuses them on the node (they fail with the reason, nothing is written).';

    /**
     * What closing does NOT do, said to the operator every time (TASK-0034 review round 1): these run code as the shared
     * `www` user, which on the command line is not held by open_basedir and can read and write every site on the node.
     * Whether they close too is an owner decision that must be taken before the first --apply; the notice to customers
     * must not call the node isolated while they are open.
     */
    private const STILL_OPEN = 'NOT closed by this (owner decision before --apply): the terminal, Node.js projects and existing cron jobs run as the shared www user and can still read and write the other sites on the node. Do not tell customers the node is isolated.';

    public function handle(CommandBus $bus): int
    {
        $only = trim((string) ($this->option('instance') ?? ''));
        if ((bool) $this->option('apply') && (bool) $this->option('reopen')) {
            $this->error('--apply closes, --reopen opens: pick one.');

            return self::FAILURE;
        }
        $instances = ProviderInstance::query()->platform()->where('provider', 'aapanel')
            ->when($only !== '', fn ($q) => $q->where('key', $only))->orderBy('key')->get();
        if ($only !== '' && $instances->isEmpty()) {
            $this->error("{$only} is not a platform aaPanel instance.");

            return self::FAILURE;
        }
        if ((bool) $this->option('reopen')) {
            return $this->reopen($bus, $only, $instances->first());
        }
        // one platform organization beside historical sites the platform did not create is shared too, but the platform
        // cannot count those sites (it never reads them): the operator who knows closes that node by name (review round 1)
        $force = (bool) $this->option('force');
        if ($force && (! (bool) $this->option('apply') || $only === '')) {
            $this->error('--force closes one named node: use it with --apply --instance=<key>, never in bulk.');

            return self::FAILURE;
        }

        $shared = $instances->map(fn (ProviderInstance $i) => ['instance' => $i, 'services' => $this->services($i)])
            ->filter(fn (array $row) => $force || $row['services']->pluck('organization_id')->unique()->count() > 1)->values();
        if ($shared->isEmpty()) {
            $this->info('No aaPanel node serves more than one organization'.($only !== '' ? " ({$only})" : '').'. Nothing to close.');

            return self::SUCCESS;
        }

        return (bool) $this->option('apply') ? $this->apply($bus, $shared) : $this->preview($shared);
    }

    /** @return Collection<int, Service> */
    private function services(ProviderInstance $instance): Collection
    {
        return Service::query()->where('provider_instance_id', $instance->id)->where('state', '!=', ServiceStateMachine::TERMINATED)
            ->orderBy('organization_id')->orderBy('created_at')->get();
    }

    /** @param Collection<int, array{instance: ProviderInstance, services: Collection<int, Service>}> $shared */
    private function preview(Collection $shared): int
    {
        foreach ($shared as ['instance' => $instance, 'services' => $services]) {
            $organizations = $services->pluck('organization_id')->unique()->values();
            $names = Organization::query()->whereIn('id', $organizations->all())->pluck('name', 'id');
            $this->line('');
            $this->line(sprintf('%s (%s) — %d organizations, %d services — %s', $instance->key, $instance->name, $organizations->count(), $services->count(),
                AaPanelTenancyGate::closed($instance) ? 'already closed' : 'open'));
            $this->table(['service', 'organization', 'site', 'state'], $services->map(fn (Service $s) => [$s->id, (string) ($names[$s->organization_id] ?? $s->organization_id), (string) ($s->hostname ?: $s->name), $s->state])->all());
            $this->line('Tell before --apply:');
            $this->table(['organization', 'name'], $organizations->map(fn (string $id) => [$id, (string) ($names[$id] ?? '')])->all());
        }
        $this->line('');
        $this->line(self::LOSES);
        $this->line(self::KEEPS);
        $this->line(self::QUEUED);
        $this->warn(self::STILL_OPEN);
        $this->info('Nothing was changed. After the customers were told, close these nodes with --apply; --reopen --instance=<key> reverts one.');

        return self::SUCCESS;
    }

    /** @param Collection<int, array{instance: ProviderInstance, services: Collection<int, Service>}> $shared */
    private function apply(CommandBus $bus, Collection $shared): int
    {
        $failed = 0;
        foreach ($shared as ['instance' => $instance, 'services' => $services]) {
            if (AaPanelTenancyGate::closed($instance)) {
                $this->line("{$instance->key}: already closed");

                continue;
            }
            $organizations = $services->pluck('organization_id')->unique()->count();
            try {
                $written = $this->write($bus, $instance, 'close', [
                    'closed' => true, 'closed_at' => now()->toIso8601String(), 'organizations' => $organizations, 'services' => $services->count(), 'by' => 'cli:aapanel:tenancy',
                    'forced' => (bool) $this->option('force'), // closed by name although the platform counted one organization
                ]);
            } catch (DomainError $e) {
                $failed++;
                $this->error("{$instance->key}: not closed — {$e->getMessage()}");

                continue;
            }
            if (! $written) {
                $this->line("{$instance->key}: already closed (by another run meanwhile)");

                continue;
            }
            $this->info("{$instance->key}: closed ({$organizations} organizations, {$services->count()} services)");
        }
        $this->line(self::LOSES);
        $this->warn(self::STILL_OPEN);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function reopen(CommandBus $bus, string $only, ?ProviderInstance $instance): int
    {
        if ($only === '' || $instance === null) {
            $this->error('--reopen needs --instance=<key>: a node is reopened one at a time, never in bulk.');

            return self::FAILURE;
        }
        if (! AaPanelTenancyGate::closed($instance)) {
            $this->line("{$instance->key}: not closed; nothing to reopen");

            return self::SUCCESS;
        }
        if (! $this->write($bus, $instance, 'reopen', ['closed' => false, 'reopened_at' => now()->toIso8601String(), 'by' => 'cli:aapanel:tenancy'])) {
            $this->line("{$instance->key}: not closed (reopened by another run meanwhile); nothing to reopen");

            return self::SUCCESS;
        }
        $this->info("{$instance->key}: reopened — the in-panel file manager and shell cron are available again on it");

        return self::SUCCESS;
    }

    /**
     * Through the bus like any change of a provider instance (HIGH, audited), with every other option of the instance
     * kept as it is: `instance.upsert` replaces the options it is given.
     *
     * The check that the node still needs it and the write happen under one row lock (TASK-0034 review round 2): two runs
     * at once (cron and a terminal, two terminals) both saw the node open and both wrote — two audit rows, and the later
     * one wrote back the options it had read, over whatever changed in between. Now the second waits, sees the first
     * one's closure and writes nothing (false). A refusal of the bus is recorded by the bus and thrown after the lock.
     *
     * @param  array<string, mixed>  $tenancy
     */
    private function write(CommandBus $bus, ProviderInstance $instance, string $what, array $tenancy): bool
    {
        $refused = null;
        $written = DB::transaction(function () use ($bus, $instance, $what, $tenancy, &$refused): bool {
            $fresh = ProviderInstance::query()->whereKey($instance->id)->lockForUpdate()->firstOrFail();
            if ((bool) data_get($fresh->options, AaPanelTenancyGate::OPTION.'.closed', false) === (bool) $tenancy['closed']) {
                return false;
            }
            $options = array_merge((array) $fresh->options, [AaPanelTenancyGate::OPTION => $tenancy]);
            try {
                $bus->dispatch(new ProvisioningCommand("aapanel-tenancy:{$fresh->key}:{$what}:".now()->format('U.u'), [
                    'op' => 'instance.upsert', 'key' => $fresh->key, 'provider' => $fresh->provider, 'base_url' => $fresh->base_url, 'options' => $options,
                ]), CommandContext::system('cli:aapanel:tenancy'));
            } catch (DomainError $e) {
                $refused = $e; // thrown below: inside, it would roll back the bus's own record of the refusal

                return false;
            }

            return true;
        });
        if ($refused !== null) {
            throw $refused;
        }

        return $written;
    }
}
