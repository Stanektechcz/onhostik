<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Throwable;

/**
 * Brings a game panel into service in one go (audit §5g-1): connection test, node discovery into the scheduler,
 * the prerequisites record, the catalogue templates mapped onto the panel's eggs by name (`config/onhost.php` →
 * `game.eggs`), a port range where a node has no free allocation, and the plan placement so the scheduler puts
 * every game order on this panel. Every step is idempotent and reported; nothing needs the operator to click
 * through the panel except importing eggs the panel does not ship with (the report names them).
 */
final class GamePanelBootstrap
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ProviderInstanceService $instances,
        private readonly NodePrerequisites $prerequisites,
        private readonly PlacementService $placements,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Maps catalogue template keys onto the panel's nests/eggs by name; keys already mapped by hand keep their mapping.
     *
     * @return array{mapped:array<string,array{nest:int,egg:int,name:string}>, kept:list<string>, unmapped:array<string,string>, eggs:int}
     */
    public function syncEggs(ProviderInstance $instance, CommandContext $context, bool $force = false): array
    {
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof GameToolsProvider) {
            throw new DomainError('instance_not_game_panel', 'Template mapping works on game panel instances only.', 422);
        }
        $eggs = $adapter->listEggs();
        $current = (array) $instance->option('eggs', []);
        $out = ['mapped' => [], 'kept' => [], 'unmapped' => [], 'eggs' => count($eggs)];
        foreach ((array) config('onhost.game.eggs', []) as $key => $preset) {
            if (! $force && isset($current[$key]['nest'], $current[$key]['egg']) && collect($eggs)->contains(fn ($e) => $e['nest_id'] === (int) $current[$key]['nest'] && $e['id'] === (int) $current[$key]['egg'])) {
                $out['kept'][] = (string) $key;
                $required = GameTemplates::readRequirements($adapter, (int) $current[$key]['nest'], (int) $current[$key]['egg']); // §5s: kept mappings learn their requirements too
                if ($required !== null) {
                    $current[$key]['required'] = $required;
                }

                continue;
            }
            $match = collect($eggs)->first(fn (array $e) => ! $e['privileged'] && preg_match((string) $preset['egg'], $e['name']) === 1 && (empty($preset['nest']) || preg_match((string) $preset['nest'], $e['nest']) === 1))
                ?? collect($eggs)->first(fn (array $e) => ! $e['privileged'] && preg_match((string) $preset['egg'], $e['name']) === 1);
            $fallback = false;
            if ($match === null && ! empty($preset['fallback_egg'])) { // §5o: a template the panel lacks may run on a sibling egg (Spigot on Paper through DL_PATH)
                $match = collect($eggs)->first(fn (array $e) => ! $e['privileged'] && preg_match((string) $preset['fallback_egg'], $e['name']) === 1 && (empty($preset['nest']) || preg_match((string) $preset['nest'], $e['nest']) === 1));
                $fallback = $match !== null;
            }
            if ($match === null) {
                $out['unmapped'][(string) $key] = (string) ($preset['import'] ?? 'import the egg into the panel');

                continue;
            }
            $current[$key] = array_filter(['nest' => $match['nest_id'], 'egg' => $match['id'], 'environment' => (array) ($preset['environment'] ?? []) ?: null, 'name' => $match['name'], 'via_fallback' => $fallback ?: null, 'required' => GameTemplates::readRequirements($adapter, (int) $match['nest_id'], (int) $match['id'])], fn ($v) => $v !== null); // §5s: required variables without a default
            $out['mapped'][(string) $key] = ['nest' => $match['nest_id'], 'egg' => $match['id'], 'name' => $match['name']] + ($fallback ? ['via_fallback' => true] : []);
        }
        $instance->forceFill(['options' => array_merge((array) $instance->options, ['eggs' => $current])])->save();
        $this->providers->forget($instance);
        $this->instances->auditOptions($instance, 'eggs', ['mapped' => array_keys($out['mapped']), 'unmapped' => array_keys($out['unmapped'])], $context);

        return $out;
    }

    /**
     * The whole sequence; every part reports and a failing part does not stop the others.
     *
     * @return array<string,mixed>
     */
    public function bootstrap(ProviderInstance $instance, CommandContext $context, string $productKey = 'game'): array
    {
        $report = ['instance' => $instance->key, 'probe' => null, 'nodes' => [], 'prerequisites' => null, 'eggs' => null, 'allocations' => [], 'placement' => null, 'errors' => []];
        $status = $this->instances->credentialStatus($instance);
        if ($status['missing'] !== []) {
            $report['errors'][] = 'missing credentials: '.implode(', ', $status['missing']).' — store them with onhost:integrations:secret '.$instance->key.' <key>';

            return $report;
        }
        $report['probe'] = $this->step($report, 'probe', fn () => $this->instances->probe($instance, $context));
        if (! ($report['probe']['up'] ?? false)) {
            return $report; // nothing else can work; the probe error says why
        }
        $report['nodes'] = $this->step($report, 'nodes', fn () => $this->instances->discoverNodes($instance->fresh(), $context)['nodes']);
        $report['eggs'] = $this->step($report, 'eggs', fn () => $this->syncEggs($instance->fresh(), $context));
        $report['prerequisites'] = $this->step($report, 'prerequisites', fn () => $this->prerequisites->check($instance->fresh(), $context));
        $report['allocations'] = $this->step($report, 'allocations', fn () => $this->ensureAllocations($instance->fresh()));
        $report['placement'] = $this->step($report, 'placement', function () use ($instance, $context, $productKey) {
            if (Product::query()->where('key', $productKey)->doesntExist()) {
                return null;
            }

            return PlacementService::present($this->placements->upsert(['product_key' => $productKey, 'provider_instance_key' => $instance->key, 'priority' => 10, 'note' => 'game panel bootstrap'], $context));
        });
        $this->audit->record($context, 'provider.instance.bootstrap', $report['errors'] === [] ? 'succeeded' : 'failed', ['key' => $instance->key, 'nodes' => $report['nodes'], 'eggs' => is_array($report['eggs']) ? array_keys($report['eggs']['mapped'] ?? []) : null, 'errors' => $report['errors']], 'provider_instance', $instance->id);

        return $report;
    }

    /** Every panel node with at least one free allocation; a node without one gets the default port range on its primary address. @return list<array{node:int,name:string,free:int,created:list<string>}> */
    private function ensureAllocations(ProviderInstance $instance): array
    {
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof GameProvider || ! $adapter instanceof GameToolsProvider) {
            return [];
        }
        $out = [];
        foreach ($adapter->listNodes() as $node) {
            $allocations = $adapter->nodeAllocations((int) $node['id']);
            $free = count(array_filter($allocations, fn ($a) => ! $a['assigned']));
            $created = [];
            if ($free === 0) {
                $scheduler = Node::query()->where('provider_instance_id', $instance->id)->where('remote_id', (int) $node['id'])->first();
                $ip = (string) ($allocations[0]['ip'] ?? data_get($scheduler?->tags, 'public_ipv4', ''));
                if ($ip !== '') {
                    $ports = array_values((array) config('onhost.game.default_ports', ['25565-25599']));
                    $adapter->createAllocations((int) $node['id'], $ip, $ports);
                    $created = $ports;
                    $free = count(array_filter($adapter->nodeAllocations((int) $node['id']), fn ($a) => ! $a['assigned']));
                }
            }
            $out[] = ['node' => (int) $node['id'], 'name' => (string) $node['name'], 'free' => $free, 'created' => $created];
        }

        return $out;
    }

    /** @param  array<string,mixed>  $report */
    private function step(array &$report, string $name, callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            $report['errors'][] = "{$name}: ".mb_substr($e->getMessage(), 0, 200);

            return null;
        }
    }
}
