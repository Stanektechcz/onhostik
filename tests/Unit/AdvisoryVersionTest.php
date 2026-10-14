<?php

declare(strict_types=1);

use Onhost\Domain\Advisories\AdvisoryVersion;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Domain\Advisories\RemediationPlanner;
use Onhost\Providers\Contracts\AdvisoryBatch;

/* SN-2: is a version inside an advisory's range, and which kind of fix does the planner offer for it. */

it('compares a version with introduced, fixed, last affected and exact versions', function (string $version, array $range, ?bool $expected) {
    expect(AdvisoryVersion::affects($version, $range))->toBe($expected);
})->with([
    'below the fix' => ['5.9.7', ['fixed' => '5.9.8'], true],
    'the fix itself' => ['5.9.8', ['fixed' => '5.9.8'], false],
    'above the fix' => ['6.0', ['fixed' => '5.9.8'], false],
    'before introduced' => ['6.5.9', ['introduced' => '6.6', 'fixed' => '6.6.3'], false],
    'inside the range' => ['6.6.1', ['introduced' => '6.6', 'fixed' => '6.6.3'], true],
    'last affected inclusive' => ['5.9.7', ['last_affected' => '5.9.7'], true],
    'after last affected' => ['5.9.7.1', ['last_affected' => '5.9.7'], false],
    'exact list' => ['2.1.0', ['versions' => ['2.1.0', '2.1.1']], true],
    'not in exact list' => ['2.2.0', ['versions' => ['2.1.0']], false],
    'no bounds says nothing' => ['1.0', ['product' => 'x'], null],
    'unreadable version' => ['trunk', ['fixed' => '1.0'], null],
    'debian epoch and build' => ['1:8.2.7+deb12u1', ['fixed' => '8.2.8'], true],
    'leading v' => ['v7.9.2', ['fixed' => '7.9.9'], true],
]);

it('plans an in-line plugin and core fix as A, a new major, a theme or another core branch as B, and the rest as D or P', function () {
    $item = fn (string $ecosystem, string $product, string $version, string $type = InventoryItem::SERVICE) => new InventoryItem(['subject_type' => $type, 'subject_id' => 'srv_x', 'ecosystem' => $ecosystem, 'product' => $product, 'version' => $version]);

    expect(RemediationPlanner::plan($item('wordpress-plugin', 'contact-form-7', '5.9.7'), '5.9.8', 'exact'))->toBe(['class' => 'A', 'action' => ['action' => 'wp.plugin', 'params' => ['slug' => 'contact-form-7', 'op' => 'update']]])
        ->and(RemediationPlanner::plan($item('wordpress-plugin', 'contact-form-7', '5.9.7'), '6.0.0', 'exact')['class'])->toBe('B')
        ->and(RemediationPlanner::plan($item('wordpress-plugin', 'my_plugin.v2', '1.0'), '1.1', 'exact'))->toBe(['class' => 'D', 'action' => null])
        ->and(RemediationPlanner::plan($item('wordpress-core', 'wordpress', '6.6.1'), '6.6.3', 'exact'))->toBe(['class' => 'A', 'action' => ['action' => 'wp.update', 'params' => ['what' => 'core', 'staged' => true]]])
        ->and(RemediationPlanner::plan($item('wordpress-core', 'wordpress', '6.5.4'), '6.6.3', 'exact')['class'])->toBe('B')
        ->and(RemediationPlanner::plan($item('wordpress-theme', 'astra', '4.0'), '4.1', 'exact')['class'])->toBe('B')
        ->and(RemediationPlanner::plan($item('wordpress-plugin', 'akismet', '5.0'), null, 'exact')['class'])->toBe('D')
        ->and(RemediationPlanner::plan($item('wordpress-plugin', 'akismet', '5.0'), '5.1', 'probable')['class'])->toBe('D')
        ->and(RemediationPlanner::plan($item('cpe', 'proxmox:virtual_environment', '8.2', InventoryItem::INSTANCE), '8.3', 'exact'))->toBe(['class' => 'P', 'action' => null]);
});

it('keeps feed text plain: tags go, a range written with < stays, control characters go and the length is capped', function () {
    expect(AdvisoryBatch::text('<script>x</script>Plugin <b>X</b> <= 1.2 - XSS'."\x07", 300))->toBe('xPlugin X <= 1.2 - XSS')
        ->and(AdvisoryBatch::text(str_repeat('a', 50), 10))->toBe('aaaaaaaaaa')
        ->and(AdvisoryBatch::severity(9.8))->toBe('critical')->and(AdvisoryBatch::severity(null, 'Moderate'))->toBe('medium')
        ->and(AdvisoryBatch::references(['https://ok.example/a', 'http://no.example', 'javascript:alert(1)']))->toBe(['https://ok.example/a']);
});
