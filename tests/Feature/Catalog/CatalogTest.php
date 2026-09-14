<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
});

it('resolves plan versions and prices for both currencies and periods', function () {
    $catalog = app(CatalogService::class);
    $web = $catalog->resolve('web-hosting', 'standard', 'CZK', 'month');
    expect($web['price']->amount()->minor)->toBe(18900)
        ->and($web['version']->entitlement('php_workers'))->toBe(6)
        ->and($web['plan']->sla_class)->toBe('standard');

    $vps = $catalog->resolve('vps', 'compute-4', 'EUR', 'hour');
    expect($vps['price']->monthlyCap()?->minor)->toBe(1790)
        ->and($vps['price']->amount()->minor)->toBe((int) ceil(1790 / 720));
});

it('refuses draft products and unknown plans', function () {
    $catalog = app(CatalogService::class);
    expect(fn () => $catalog->resolve('object-storage', 'x', 'CZK'))->toThrow(DomainError::class)
        ->and(fn () => $catalog->resolve('web-hosting', 'ultra', 'CZK'))->toThrow(DomainError::class);
});

it('prices configurator options from catalog coefficients', function () {
    $catalog = app(CatalogService::class);
    $vps = $catalog->product('vps');
    $r = $catalog->configure($vps, Money::decimal('449', 'CZK'), ['vcpu' => 2, 'ram_gb' => 4, 'ipv4' => true, 'backup' => 'backup-7', 'nvme_gb' => 0]);
    expect($r['net']->minor)->toBe(44900 + 2 * 9900 + 4 * 4900 + 4900 + 4900)
        ->and(count($r['lines']))->toBe(4);
});

it('exposes TLD policies and side-by-side domain prices', function () {
    $catalog = app(CatalogService::class);
    $cz = $catalog->tld('cz');
    expect($cz->nsset_required)->toBeTrue()->and($cz->transfer_mode)->toBe('sync');
    $price = $catalog->domainPrice('cz', 'CZK');
    expect($price->register()->minor)->toBe(17900)->and($price->renew()->minor)->toBe(17900);
    expect(fn () => $catalog->tld('xyz'))->toThrow(DomainError::class);
});

it('builds the public catalog shape with renewal prices', function () {
    $public = app(CatalogService::class)->publicCatalog('cs', 'CZK');
    $web = collect($public)->firstWhere('key', 'web-hosting');
    expect($web['plans'][1]['price']['month_renewal']->minor)->toBe(18900)
        ->and($web['plans'][1]['entitlements']['nvme_gb'])->toBe(50)
        ->and(collect($public)->firstWhere('key', 'object-storage'))->toBeNull();
});
