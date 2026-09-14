<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\GamePanelBootstrap;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Commands\CommandContext;

/**
 * `onhost:game:bootstrap <instance>` — connection test, node discovery, template mapping, prerequisites, port ranges and
 * the plan placement in one run (audit §5g-1); `--eggs-only` maps the templates and nothing else. Run it after the
 * keys are stored (`onhost:integrations:secret`) and again whenever the panel gains eggs or nodes.
 */
final class GamePanelCommands extends Command
{
    protected $signature = 'onhost:game:bootstrap {instance : Game panel instance key (e.g. pterodactyl-gamepanel)} {--product=game : Catalogue product to place on the panel} {--eggs-only : Only map the catalogue templates onto the panel eggs} {--force : Re-map templates that are already mapped}';

    protected $description = 'Bring a game panel into service: probe, discover nodes, map catalogue templates onto eggs, prerequisites, port ranges, plan placement';

    public function handle(GamePanelBootstrap $bootstrap): int
    {
        $instance = ProviderInstance::query()->where('key', (string) $this->argument('instance'))->first();
        if ($instance === null) {
            $this->error('No such instance.');

            return self::FAILURE;
        }
        $context = CommandContext::system('cli:game:bootstrap');
        if ($this->option('eggs-only')) {
            $eggs = $bootstrap->syncEggs($instance, $context, (bool) $this->option('force'));
            $this->printEggs($eggs);

            return $eggs['unmapped'] === [] ? self::SUCCESS : self::FAILURE;
        }
        $report = $bootstrap->bootstrap($instance, $context, (string) $this->option('product'));
        $this->line('Instance: '.$report['instance']);
        if ($report['probe'] !== null) {
            $this->line('Probe: '.(($report['probe']['up'] ?? false) ? 'up' : 'down'.(isset($report['probe']['error']) ? ' — '.$report['probe']['error'] : '')));
        }
        if ($report['nodes'] !== []) {
            $this->line('Nodes: '.implode(', ', (array) $report['nodes']));
        }
        if (is_array($report['eggs'])) {
            $this->printEggs($report['eggs']);
        }
        if (is_array($report['prerequisites'])) {
            $this->line('Client API: '.($report['prerequisites']['client_api'] ?? 'n/a'));
            foreach ((array) ($report['prerequisites']['game']['nodes'] ?? []) as $node) {
                $this->line("  node {$node['name']} (#{$node['id']}): {$node['servers']} servers · daemon {$node['daemon']}".($node['maintenance'] ? ' · maintenance' : ''));
            }
        }
        foreach ((array) $report['allocations'] as $row) {
            $this->line("Allocations {$row['name']}: {$row['free']} free".($row['created'] !== [] ? ' (created '.implode(', ', $row['created']).')' : ''));
        }
        if (is_array($report['placement'])) {
            $this->line("Placement: {$report['placement']['product_key']} → {$report['placement']['provider_instance_key']}");
        }
        foreach ($report['errors'] as $error) {
            $this->warn($error);
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array{mapped:array<string,array{nest:int,egg:int,name:string}>, kept:list<string>, unmapped:array<string,string>, eggs:int}  $eggs */
    private function printEggs(array $eggs): void
    {
        $this->line("Templates on the panel: {$eggs['eggs']}");
        foreach ($eggs['mapped'] as $key => $m) {
            $this->line("  mapped {$key} → {$m['name']} (nest {$m['nest']}, egg {$m['egg']})");
        }
        foreach ($eggs['kept'] as $key) {
            $this->line("  kept   {$key}");
        }
        foreach ($eggs['unmapped'] as $key => $hint) {
            $this->warn("  missing {$key}: import {$hint}, then run again");
        }
    }
}
