<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\NodeUsageSync;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\AaPanel\AaPanelWebProvider;

/*
 * How full a shared web node is. The placement rule that keeps a node from being filled up has always been there —
 * a node whose used disk plus what the new service needs would pass 85 % of it takes nothing more — but for ISPConfig
 * and aaPanel nodes it was measuring nothing: `nodes.usage.disk_used_gb` is written when a Proxmox cluster or a game
 * panel is synchronised and never for a web node, so the number stayed 0 and the rule never fired. The acceptance
 * check read the same zero and passed every node. Both panels can say it; the adapters had `nodeLoad()` ready for it
 * with nobody calling it.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The panel of a node whose disk is `$pct` full. */
function nodeDiskPanel(int $pct, string $total = '1.8T', string $used = '1.6T'): void
{
    Http::fake(function ($request) use ($pct, $total, $used) {
        if (! str_contains($request->url(), 'managed01.mgmt.test')) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);

        return match (true) {
            str_contains($q, 'GetSystemTotal') => Http::response(['cpuRealUsed' => 12.5, 'memRealUsed' => 4096, 'memTotal' => 16384, 'load' => ['one' => 0.8]]),
            str_contains($q, 'GetDiskInfo') => Http::response([
                ['path' => '/', 'size' => ['40G', '10G', '30G', '25%']],
                ['path' => '/www', 'size' => [$total, $used, '200G', $pct.'%']],
            ]),
            str_contains($q, 'table=sites') => Http::response(['data' => [['id' => 41, 'name' => 'shop.cz']], 'page' => '共1']),
            default => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']),
        };
    });
}

it('writes down how full a web node is, so the placement rule has a number to work with', function () {
    nodeDiskPanel(62);
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel'); // brings the instance and the node
    $node = Node::query()->where('name', 'aapanel-web01')->firstOrFail();
    expect($node->use('disk_used_gb'))->toBe(0.0); // nobody ever measured it

    $stats = app(NodeUsageSync::class)->run();

    $node->refresh();
    expect($stats)->toMatchArray(['checked' => 1, 'updated' => 1, 'low' => 0, 'errors' => 0])
        ->and($node->cap('disk_gb'))->toBe(1843.0)            // 1.8 T, as the panel says, instead of a guess
        ->and($node->use('disk_used_gb'))->toBe(1638.0)       // 1.6 T used
        ->and((float) data_get($node->usage, 'disk_pct'))->toBe(62.0)
        ->and(data_get($node->usage, 'sites'))->toBe(1)
        ->and(NodeUsageSync::freePct($node))->toBe(38.0);
});

it('tells the operators when a node has less room left than it keeps for itself, once a day', function () {
    nodeDiskPanel(93, '1T', '930G');
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');

    expect(app(NodeUsageSync::class)->run()['low'])->toBe(1);
    $alerts = OutboxMessage::query()->where('name', 'node.disk.low')->get();
    expect($alerts)->toHaveCount(1)->and((float) data_get($alerts->first()->payload, 'free_pct'))->toBe(7.0);

    app(NodeUsageSync::class)->run(); // the same node, the same day: said once
    expect(OutboxMessage::query()->where('name', 'node.disk.low')->count())->toBe(1);
});

it('reads the panel\'s human sizes, and says nothing when it cannot', function () {
    expect(AaPanelWebProvider::rootDisk([['path' => '/www', 'size' => ['1.8T', '1.6T', '200G', '89%']]]))
        ->toMatchArray(['pct' => 89.0])
        ->and(AaPanelWebProvider::rootDisk([['path' => '/www', 'size' => ['500G', '250G', '250G', '50%']]])['total_gb'])->toBe(500.0)
        ->and(AaPanelWebProvider::rootDisk([['path' => '/', 'size' => ['40960M', '20480M', '20480M', '50%']]])['used_gb'])->toBe(20.0)
        ->and(AaPanelWebProvider::rootDisk('nonsense'))->toBe(['pct' => null, 'total_gb' => null, 'used_gb' => null])
        ->and(AaPanelWebProvider::rootDisk([['path' => '/www', 'size' => []]]))->toBe(['pct' => null, 'total_gb' => null, 'used_gb' => null]);
});
