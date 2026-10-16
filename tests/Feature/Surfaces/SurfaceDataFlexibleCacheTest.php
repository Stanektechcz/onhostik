<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;

/*
 * W7-0: /surfaces/onhost-data.js is cached with Cache::flexible([60, 600]). A warm request runs no query, a catalogue change
 * through the command bus (CatalogCommandHandler's Cache::forget) is visible on the next request, and after the fresh window
 * the stale script is still served without an error.
 */
beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
    Cache::flush();
});

function sdfGeneratedAt(string $js): string
{
    expect(preg_match('/generatedAt:("[^"]+")/', $js, $m))->toBe(1);

    return (string) json_decode($m[1]);
}

it('serves a warm data script without a query', function () {
    $this->get('/surfaces/onhost-data.js')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $this->get('/surfaces/onhost-data.js')->assertOk();
        $queries = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
    }
    expect($queries)->toBe(0);
});

it('rebuilds the script on the next request after a catalogue change through the bus', function () {
    $first = sdfGeneratedAt((string) $this->get('/surfaces/onhost-data.js')->assertOk()->getContent());

    $this->travel(5)->seconds();
    expect(sdfGeneratedAt((string) $this->get('/surfaces/onhost-data.js')->getContent()))->toBe($first, 'within the fresh window the cached script is served');

    app(CommandBus::class)->dispatch(new CatalogCommand('sdf-'.uniqid(), ['op' => 'product.state', 'state' => 'draft', 'products' => ['web-hosting']]), CommandContext::system('test'));

    expect(sdfGeneratedAt((string) $this->get('/surfaces/onhost-data.js')->assertOk()->getContent()))->not->toBe($first, 'Cache::forget of the key forces a rebuild under flexible too');
});

it('still serves the script without an error after the fresh window', function () {
    $this->get('/surfaces/onhost-data.js')->assertOk();

    $this->travel(61)->seconds();

    expect((string) $this->get('/surfaces/onhost-data.js')->assertOk()->getContent())->toContain('window.ONHOST_DATA');
});
