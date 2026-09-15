<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Takes catalogue products off sale or puts them back (audit §5z): a product without a connected panel (VPS on Proxmox,
 * backups on PBS) is set to `draft` so the web, the panel and the cart stop offering it; existing services stay managed.
 */
final class CatalogProductState extends Command
{
    protected $signature = 'onhost:catalog:state {state : active or draft} {products* : product keys, e.g. vps vds database}';

    protected $description = 'Put catalogue products on sale (active) or take them off sale (draft)';

    public function handle(CommandBus $bus): int
    {
        $state = (string) $this->argument('state');
        try {
            $result = $bus->dispatch(new CatalogCommand('catalog.state:'.$state.':'.implode(',', (array) $this->argument('products')).':'.now()->format('YmdHis'), ['op' => 'product.state', 'state' => $state, 'products' => array_values((array) $this->argument('products'))]), CommandContext::system('cli:catalog:state'));
        } catch (DomainError $e) {
            $this->error("{$e->error}: {$e->getMessage()}");

            return self::FAILURE;
        }
        foreach ((array) ($result['products'] ?? []) as $key => $newState) {
            $this->line("{$key} → {$newState}");
        }

        return self::SUCCESS;
    }
}
