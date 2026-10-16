<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/*
 * W5-7 — the contract between `onhost:doctor --json` and infra/aapanel/deploy-gate.php (.ai/contracts/doctor-json.md):
 * the envelope and row shape, and a real doctor report accepted by the deploy gate's parser. DeployGateTest checks
 * that the gated row names exist; this file does not repeat that.
 */

/** @return array{raw: string, report: array<string, mixed>} */
function djcDoctorReport(): array
{
    Http::preventStrayRequests();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $raw = trim(Artisan::output());

    return ['raw' => $raw, 'report' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

function djcVerdict(string $content, string $environment): Process
{
    $file = (string) tempnam(sys_get_temp_dir(), 'onhost-djc-');
    file_put_contents($file, $content);
    $process = new Process([PHP_BINARY, base_path('infra/aapanel/deploy-gate.php'), 'verdict', '--report', $file, '--doctor-rc', '0', '--env', $environment, '--production', '0', '--sha', str_repeat('cd', 20), '--override', '', '--accept-file', '']);
    $process->setTimeout(30);
    $process->run();
    @unlink($file);

    return $process;
}

it('emits the envelope and rows the contract names, with exact keys and types', function () {
    $report = djcDoctorReport()['report'];

    expect(array_keys($report))->toEqualCanonicalizing(['environment', 'fail', 'warn', 'checks'])
        ->and($report['environment'])->toBeString()
        ->and($report['fail'])->toBeInt()
        ->and($report['warn'])->toBeInt()
        ->and($report['checks'])->toBeArray()->not->toBeEmpty()
        ->and(array_is_list($report['checks']))->toBeTrue();

    foreach ($report['checks'] as $row) {
        $name = ($row['area'] ?? '?').'|'.($row['check'] ?? '?');
        expect(array_keys($row))->toEqualCanonicalizing(['area', 'check', 'status', 'detail', 'remedy', 'blocking'], $name)
            ->and($row['area'])->toBeString()
            ->and($row['check'])->toBeString()
            ->and($row['status'])->toBeIn(['OK', 'WARN', 'FAIL'])
            ->and($row['detail'])->toBeString()
            ->and($row['remedy'])->toBeString()
            ->and($row['blocking'])->toBeBool();
        if ($row['status'] === 'FAIL') {
            expect($row['blocking'])->toBeTrue($name);
        }
    }

    expect($report['fail'])->toBe(count(array_filter($report['checks'], fn (array $r) => $r['status'] === 'FAIL')))
        ->and($report['warn'])->toBe(count(array_filter($report['checks'], fn (array $r) => $r['status'] === 'WARN')));
});

it('feeds a real doctor report through the deploy gate parser, also with a notice printed before the JSON', function () {
    ['raw' => $raw, 'report' => $report] = djcDoctorReport();
    $names = array_map(fn (array $row) => $row['area'].'|'.$row['check'], $report['checks']);

    // the parser keys rows by area|check: a duplicate name would silently drop a row
    expect(count(array_unique($names)))->toBe(count($report['checks']));

    foreach ([$raw, "PHP Notice: something printed before the report\n".$raw] as $content) {
        $output = djcVerdict($content, (string) $report['environment'])->getOutput();

        expect($output)->not->toContain('not JSON with a checks list')
            ->not->toContain('the doctor did not finish')
            ->not->toContain('the doctor ran as environment')
            ->toMatch('/^VERDICT /m');
    }
});
