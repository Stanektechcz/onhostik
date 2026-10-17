<?php

declare(strict_types=1);

use App\Console\Commands\Doctor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/*
 * W7-6 — the doctor names the PHP it runs on: 8.4 and newer is OK, older a non-blocking WARN pointing at the
 * cutover runbook. The aaPanel scripts keep /www/server/php/83/bin/php when PHP_VERSION is not set.
 */

it('warns below PHP 8.4 and points at the cutover runbook', function (string $version) {
    $row = Doctor::phpVersionRow($version);

    expect($row['ok'])->toBeFalse()
        ->and($row['detail'])->toBe($version)
        ->and($row['remedy'])->toBe('see docs/runbooks/php85-cutover.md');
})->with(['8.3.0', '8.3.14', '8.2.29']);

it('is ok from PHP 8.4', function (string $version) {
    $row = Doctor::phpVersionRow($version);

    expect($row['ok'])->toBeTrue()->and($row['remedy'])->toBe('');
})->with(['8.4.0', '8.4.13', '8.5.0']);

it('reports the runtime PHP version as a non-blocking row', function () {
    Http::preventStrayRequests();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    $rows = array_values(array_filter($report['checks'], fn (array $r) => $r['area'] === 'runtime' && $r['check'] === 'PHP version'));
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['detail'])->toBe(PHP_VERSION)
        ->and($rows[0]['blocking'])->toBeFalse()
        ->and($rows[0]['status'])->toBe(version_compare(PHP_VERSION, '8.4.0', '>=') ? 'OK' : 'WARN');
});

it('keeps the aaPanel PHP path at 83 when PHP_VERSION is not set', function (string $script) {
    $source = (string) file_get_contents(base_path($script));

    preg_match('/^PHP_VERSION="\$\{PHP_VERSION:-([0-9]+)\}"/m', $source, $version);
    preg_match('/^PHP="\$\{PHP:-(.+)\}"/m', $source, $path);
    expect($version[1] ?? null)->toBe('83')
        ->and($path[1] ?? null)->toBe('/www/server/php/${PHP_VERSION}/bin/php')
        ->and(str_replace('${PHP_VERSION}', $version[1], $path[1]))->toBe('/www/server/php/83/bin/php');
})->with(['infra/aapanel/deploy.sh', 'infra/aapanel/install.sh']);
