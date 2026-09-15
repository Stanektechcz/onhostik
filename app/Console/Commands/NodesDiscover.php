<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Imports the servers of a panel as scheduler nodes through the panel's API (audit §5z) — ISPConfig servers, Proxmox
 * cluster nodes, game-panel (Wings) nodes, the aaPanel host. A service is only placed on an active node of the right
 * role in the service's region: web hosting on ISPConfig needs role `web`, on aaPanel `managed`, game servers `game`.
 * --region sets the instance's region when it has none; --role corrects the role of the imported nodes.
 */
final class NodesDiscover extends Command
{
    protected $signature = 'onhost:nodes:discover {instance : provider instance key, e.g. ispconfig-shared01} {--region= : region code for an instance without one, e.g. cz1} {--role= : set this role on the nodes of the instance (web, managed, game, compute, mail, dns)}';

    protected $description = 'Import the nodes of a provider instance through its API so the scheduler can place services on them';

    public function handle(ProviderInstanceService $instances): int
    {
        $instance = ProviderInstance::query()->where('key', (string) $this->argument('instance'))->first();
        if ($instance === null) {
            $this->error('Unknown instance. Available: '.ProviderInstance::query()->orderBy('key')->pluck('key')->implode(', '));

            return self::FAILURE;
        }
        $context = CommandContext::system('cli:nodes:discover');
        $region = (string) $this->option('region');
        if ($instance->region_code === null) {
            if ($region === '' || ! Region::query()->where('code', $region)->exists()) {
                $this->error('The instance has no region. Pass --region=<code>; regions: '.Region::query()->orderBy('code')->pluck('code')->implode(', '));

                return self::FAILURE;
            }
            $instance->forceFill(['region_code' => $region])->save();
            $this->line("Region of {$instance->key} set to {$region}.");
        }
        try {
            $result = $instances->discoverNodes($instance, $context);
        } catch (DomainError $e) {
            $this->error("{$e->error}: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error(class_basename($e).': '.$e->getMessage());

            return self::FAILURE;
        }
        $role = (string) $this->option('role');
        if ($role !== '') {
            if (! in_array($role, ['compute', 'web', 'managed', 'game', 'mail', 'dns', 'apps', 'backup'], true)) {
                $this->error('Unknown role.');

                return self::FAILURE;
            }
            Node::query()->where('provider_instance_id', $instance->id)->update(['role' => $role]);
        }
        $nodes = Node::query()->where('provider_instance_id', $instance->id)->orderBy('name')->get();
        $this->table(['node', 'role', 'region', 'state', 'RAM MB', 'disk GB'], $nodes->map(fn (Node $n) => [$n->name, $n->role, $n->region_code, $n->state, (int) data_get($n->capacity, 'ram_mb', 0), (int) data_get($n->capacity, 'disk_gb', 0)])->all());
        $needed = match ($instance->provider) {
            'ispconfig' => 'web', 'aapanel' => 'managed', 'pterodactyl' => 'game', 'proxmox' => 'compute', default => null
        };
        if ($needed !== null && ! $nodes->contains(fn (Node $n) => $n->role === $needed && $n->state === 'active')) {
            $this->warn("No active node with role {$needed}: web/game orders on {$instance->key} cannot be placed. Run again with --role={$needed}.");
        } else {
            $this->info(count($result['nodes']).' node(s) imported from '.$instance->key.'.');
        }

        return self::SUCCESS;
    }
}
