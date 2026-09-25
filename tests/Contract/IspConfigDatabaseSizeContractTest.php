<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\DatabaseSizeCapable;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

/*
 * The size of a site's databases (TASK-0023 web-disk-total): a web plan sells its space as "files, databases and mail
 * together", and only the files were ever measured. ISPConfig answers database sizes per CLIENT (`databasequota_get_by_user`),
 * and one client may own sites the platform never made — so only the databases ISPConfig lists under THIS site
 * (`sites_database_get` by `parent_domain_id`) are counted. A refusal is thrown, never turned into 0.
 */

function dbSizeAdapter(): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web', 'mail'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance);
}

/** @param  array<int, array{0:string, 1:array<string,mixed>}>  $calls */
function dbSizeFake(array &$calls, mixed $quotaAnswer, string $quotaCode = 'ok'): void
{
    Http::fake(function ($request) use (&$calls, $quotaAnswer, $quotaCode) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = [$function, $request->data()];
        $answer = match ($function) {
            'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-db-size'],
            // the site's own databases: ISPConfig filters them by the web domain they belong to
            'sites_database_get' => ['code' => 'ok', 'message' => '', 'response' => (int) data_get($request->data(), 'primary_id.parent_domain_id') === 7
                ? [['database_id' => 11, 'database_name' => 'c3shop', 'parent_domain_id' => 7], ['database_id' => 12, 'database_name' => 'c3shop_log', 'parent_domain_id' => 7], ['database_id' => 13, 'database_name' => 'c3new', 'parent_domain_id' => 7]]
                : []],
            'databasequota_get_by_user' => ['code' => $quotaCode, 'message' => $quotaCode === 'ok' ? '' : 'You do not have the permissions to access this function.', 'response' => $quotaAnswer],
            default => ['code' => 'remote_fault', 'message' => "unexpected {$function}", 'response' => false],
        };

        return Http::response($answer);
    });
}

it('reads the sizes of the site\'s own databases only, raw bytes first, and a database not yet measured as null', function () {
    $calls = [];
    dbSizeFake($calls, [
        ['database_name' => 'c3shop', 'used' => '1.5 GB', 'used_raw' => 1610612736, 'database_quota' => -1],
        ['database_name' => 'c3shop_log', 'used' => 52428800],
        // another site of the same ISPConfig client — historical, not the platform's: never counted
        ['database_name' => 'c3legacy', 'used_raw' => 9 * 1024 ** 3],
    ]);
    $adapter = dbSizeAdapter();

    expect($adapter)->toBeInstanceOf(DatabaseSizeCapable::class);
    $sizes = $adapter->databaseSizes(new ResourceRef('web_domain', '7', '1', ['client_id' => 3]));

    expect($sizes)->toBe([
        ['name' => 'c3shop', 'used_bytes' => 1610612736],
        ['name' => 'c3shop_log', 'used_bytes' => 52428800],
        ['name' => 'c3new', 'used_bytes' => null], // the monitor has not measured it yet: not 0
    ]);
    $quotaCall = collect($calls)->first(fn (array $c) => $c[0] === 'databasequota_get_by_user');
    expect($quotaCall[1]['client_id'])->toBe(3)
        ->and(collect($calls)->first(fn (array $c) => $c[0] === 'sites_database_get')[1]['primary_id'])->toBe(['parent_domain_id' => 7]);
});

it('throws when the panel refuses the database sizes, instead of answering 0', function () {
    $calls = [];
    dbSizeFake($calls, false, 'remote_fault');

    expect(fn () => dbSizeAdapter()->databaseSizes(new ResourceRef('web_domain', '7', '1', ['client_id' => 3])))->toThrow(ProviderException::class);
});

it('refuses to read database sizes for a mail domain or without a client', function () {
    $calls = [];
    dbSizeFake($calls, []);
    $adapter = dbSizeAdapter();

    expect(fn () => $adapter->databaseSizes(new ResourceRef('mail_domain', '7', '1', ['client_id' => 3])))->toThrow(ProviderException::class)
        ->and(fn () => $adapter->databaseSizes(new ResourceRef('web_domain', '7', '1', [])))->toThrow(ProviderException::class)
        ->and(collect($calls)->pluck(0)->all())->not->toContain('databasequota_get_by_user');
});
