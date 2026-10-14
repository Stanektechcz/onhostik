<?php

declare(strict_types=1);

use App\Console\Commands\GenerateRoleMatrix;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Identity\Authorization\RoleCatalog;

/*
 * docs/manual-tests/09-role.md is generated from the code (php artisan onhost:docs:roles); this test keeps the committed
 * file equal to what the code generates, so the per-role manual test sheet cannot drift from RoleCatalog.
 */

function rmdCommitted(): string
{
    return str_replace("\r\n", "\n", (string) file_get_contents(base_path('docs/manual-tests/09-role.md')));
}

it('commits exactly what the generator writes', function () {
    expect(rmdCommitted())->toBe(app(GenerateRoleMatrix::class)->render());
});

it('names every role of the catalogue', function () {
    $doc = rmdCommitted();
    foreach (array_keys(RoleCatalog::all()) as $key) {
        expect($doc)->toContain('### `'.$key.'`');
    }
});

it('check mode succeeds on the committed file and fails on a drifted one', function () {
    expect(Artisan::call('onhost:docs:roles', ['--check' => true]))->toBe(0);

    $relative = 'storage/framework/testing/role-matrix-drift-'.bin2hex(random_bytes(4)).'.md';
    $drifted = base_path($relative);
    if (! is_dir(dirname($drifted))) {
        mkdir(dirname($drifted), 0775, true);
    }
    file_put_contents($drifted, rmdCommitted()."\nextra line\n");
    try {
        expect(Artisan::call('onhost:docs:roles', ['--check' => true, '--out' => $relative]))->toBe(1);
    } finally {
        @unlink($drifted);
    }
});

it('lists the sidebar and both kinds of refusal for the customer roles', function () {
    $doc = rmdCommitted();
    expect($doc)->toContain('**403**')->and($doc)->toContain('**404**')->and($doc)->toContain('Očekávané menu');
});
