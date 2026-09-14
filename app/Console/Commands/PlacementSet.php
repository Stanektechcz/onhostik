<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Where a product is provisioned (the console's *Umístění tarifů* from the terminal): a product (or one of its plans)
 * → a provider instance, so an installation without ISPConfig can sell web hosting on its aaPanel, or pin a plan to a
 * panel. Compatibility (ispconfig ↔ aapanel, proxmox, pterodactyl, kubernetes) is checked by the service.
 */
final class PlacementSet extends Command
{
    protected $signature = 'onhost:placements:set {product : Catalogue product key, e.g. web-hosting} {instance : Provider instance key, e.g. aapanel-managed01} {--plan= : Only this plan of the product} {--priority=10} {--note=}';

    protected $description = 'Place a catalogue product (or plan) on a provider instance';

    public function handle(PlacementService $placements): int
    {
        try {
            $placement = $placements->upsert(array_filter([
                'product_key' => (string) $this->argument('product'), 'plan_key' => $this->option('plan') ?: null, 'provider_instance_key' => (string) $this->argument('instance'),
                'priority' => (int) $this->option('priority'), 'note' => $this->option('note') ?: 'cli',
            ], fn ($v) => $v !== null), CommandContext::system('cli:placements:set'));
        } catch (DomainError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $row = PlacementService::present($placement);
        $this->info("{$row['product_key']}".(! empty($row['plan_key']) ? " / {$row['plan_key']}" : '')." → {$row['provider_instance_key']} (priority {$row['priority']})");

        return self::SUCCESS;
    }
}
