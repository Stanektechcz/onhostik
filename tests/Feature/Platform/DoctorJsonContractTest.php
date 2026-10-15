<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * W5-7 (.ai/contracts/doctor-json.md): `onhost:doctor --json` keeps one shape, and the deploy gate
 * (infra/aapanel/deploy-gate.php) reads that same shape. The test changes no output; it pins it.
 */

/** @return array<string, mixed> */
function djcW57Report(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
}

it('prints the documented top-level keys and a list of rows', function () {
    $report = djcW57Report();

    expect(array_keys($report))->toBe(['environment', 'fail', 'warn', 'checks'])
        ->and($report['environment'])->toBeString()
        ->and($report['fail'])->toBeInt()
        ->and($report['warn'])->toBeInt()
        ->and($report['checks'])->toBeArray()->not->toBeEmpty()
        ->and(array_is_list($report['checks']))->toBeTrue();
});

it('gives every row exactly area, check, status, detail, remedy and a boolean blocking', function () {
    $report = djcW57Report();
    foreach ($report['checks'] as $row) {
        $label = (string) ($row['area'] ?? '?').'|'.(string) ($row['check'] ?? '?');
        expect(array_keys($row))->toBe(['area', 'check', 'status', 'detail', 'remedy', 'blocking'], $label)
            ->and($row['area'])->toBeString()
            ->and($row['check'])->toBeString()
            ->and($row['status'])->toBeIn(['OK', 'WARN', 'FAIL'], $label)
            ->and($row['detail'])->toBeString()
            ->and($row['remedy'])->toBeString()
            ->and($row['blocking'])->toBeBool($label);
    }
});

it('is read by the deploy gate through the same keys', function () {
    $source = (string) file_get_contents(base_path('infra/aapanel/deploy-gate.php'));

    expect($source)->toContain('$data[\'checks\']')
        ->toContain('$row[\'area\']')
        ->toContain('$row[\'check\']')
        ->toContain('$row[\'status\']');
});
