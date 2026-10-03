<?php

declare(strict_types=1);

use App\Http\Navigation\NavItem;
use App\Http\Navigation\StaffNavigation;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Models\User;
use Tests\TestCase;

require_once __DIR__.'/NavigationHelpers.php';

/*
 * Audit 2026-10 B2: the navigation never offers a read the API refuses, and the API never serves it to somebody the navigation
 * would not show it to. Every GET entry of every item: a member of staff holding only its permission is not refused (403); one
 * holding every other staff permission is.
 */

/** A concrete URL for an API entry: placeholders get an id that exists nowhere. */
function navApiUrl(array $api): string
{
    return '/v1/'.preg_replace('~\{[^}]+\}~', '01JNAVTEST0000000000000000', $api['path']);
}

function navApiStatus(TestCase $test, User $user, array $api): int
{
    app(Authorizer::class)->flush();

    return $test->actingAs($user, 'sanctum')->getJson(navApiUrl($api))->status();
}

/** @return array<string, array{0: string, 1: array}> */
function navApiReads(): array
{
    $out = [];
    foreach (StaffNavigation::items() as $item) {
        foreach ($item->api as $api) {
            if ($api['method'] === 'GET' && NavItem::accepts($api) !== []) {
                $out[$item->key.' '.$api['path']] ??= [$item->key, $api];
            }
        }
    }

    return $out;
}

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake();
});

it('serves a navigation read to whoever holds its permission and refuses everybody else', function (string $item, array $api) {
    $accepts = NavItem::accepts($api);
    foreach ($accepts as $permission) {
        $status = navApiStatus($this, navApiStaff([$permission]), $api);
        expect($status)->not->toBe(403, "{$api['path']} with only {$permission}")->and($status)->toBeLessThan(500, "{$api['path']} with only {$permission}");
    }

    $staffKeys = array_keys(array_filter(PermissionCatalog::all(), fn (array $p) => $p['audience'] === 'staff'));
    expect(navApiStatus($this, navApiStaff(array_values(array_diff($staffKeys, $accepts))), $api))->toBe(403, "{$api['path']} without ".implode('|', $accepts));
})->with(navApiReads());

it('opens every read of an item to a member of staff holding only the item\'s required permissions', function () {
    foreach (StaffNavigation::items() as $item) {
        $held = [...$item->all, ...array_slice($item->any, 0, 1)];
        if ($held === []) {
            continue;
        }
        $user = navApiStaff($held);
        $nav = collect(app(StaffNavigation::class)->for($user))->keyBy('key');
        expect($nav->has($item->key))->toBeTrue("{$item->key} is shown with ".implode(', ', $held));
        foreach ($nav[$item->key]['api'] as $api) {
            if ($api['method'] !== 'GET') {
                continue;
            }
            expect(navApiStatus($this, $user, $api))->not->toBe(403, "{$item->key}: {$api['path']}");
        }
    }
});
