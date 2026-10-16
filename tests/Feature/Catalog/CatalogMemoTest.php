<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
});

/** @return array<string, mixed>|null */
function catalogMemoEntry(string $key): ?array
{
    return collect(app(CatalogService::class)->publicCatalog('cs', 'CZK'))->first(fn ($p) => $p['key'] === $key);
}

function catalogMemoQueries(Closure $read): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });
    $read();

    return $count;
}

it('reads the catalog once per request scope and serves repeats from the memo', function () {
    expect(app(CatalogService::class))->toBe(app(CatalogService::class));
    catalogMemoEntry('database');
    app(CatalogService::class)->product('database');

    expect(catalogMemoQueries(function (): void {
        catalogMemoEntry('database');
        app(CatalogService::class)->product('database');
    }))->toBe(0);
});

it('drops the memo when a catalog command changes the catalog mid-request', function () {
    expect(catalogMemoEntry('database')['description'] ?? null)->not->toBe('Nový popis databáze.');
    app(CatalogService::class)->product('database');

    app(CommandBus::class)->dispatch(new CatalogCommand('memo-'.uniqid(), ['op' => 'product.describe', 'product_key' => 'database', 'description' => ['cs' => 'Nový popis databáze.', 'en' => 'A new database description.']]), CommandContext::system('test'));

    expect(catalogMemoEntry('database')['description'] ?? null)->toBe('Nový popis databáze.')
        ->and(app(CatalogService::class)->product('database')->description['cs'] ?? null)->toBe('Nový popis databáze.');
});

it('drops the memo when a catalog model is saved outside the bus', function () {
    expect(catalogMemoEntry('database'))->not->toBeNull();

    Product::query()->where('key', 'database')->firstOrFail()->forceFill(['state' => 'draft'])->save();

    expect(catalogMemoEntry('database'))->toBeNull();
});
