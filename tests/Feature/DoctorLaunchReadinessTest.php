<?php

declare(strict_types=1);

use App\Console\Commands\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * W3-3: Doctor::LAUNCH_CHECKS names the rows a launch needs green (A4, A2, platform backup, C1); one non-blocking
 * `launch readiness` row summarises them in the table and in --json without changing the exit code.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(function () {
    $this->travelBack();
});

/** @return array{exit:int, checks:list<array<string, mixed>>} */
function dlrDoctorJson(): array
{
    $exit = Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    return ['exit' => $exit, 'checks' => $report['checks']];
}

/**
 * @param  list<array<string, mixed>>  $checks
 * @return array<string, mixed>
 */
function dlrSummaryRow(array $checks): array
{
    $rows = array_values(array_filter($checks, fn (array $row): bool => $row['area'] === 'launch'));
    expect($rows)->toHaveCount(1);

    return $rows[0];
}

it('matches every launch check to exactly one doctor row', function () {
    $checks = dlrDoctorJson()['checks'];

    foreach (Doctor::LAUNCH_CHECKS as [$area, $check]) {
        $matches = array_filter($checks, fn (array $row): bool => $row['area'] === $area && str_starts_with((string) $row['check'], $check));
        expect($matches)->toHaveCount(1, "{$area} / {$check}");
    }
});

it('prints the non-blocking summary row in JSON and in the table', function () {
    $summary = dlrSummaryRow(dlrDoctorJson()['checks']);
    expect($summary['check'])->toBe('launch readiness (A4, A2, C1)')
        ->and($summary['blocking'])->toBeFalse()
        ->and($summary['status'])->not->toBe('FAIL');

    Artisan::call('onhost:doctor');
    expect(Artisan::output())->toContain('launch readiness (A4, A2, C1)');
});

it('reports a due e-invoice without Peppol and keeps the exit code of the blocking rows', function () {
    config(['onhost.einvoice.sk_mandatory_from' => '2027-01-01', 'onhost.einvoice.peppol.base_url' => '', 'onhost.einvoice.peppol.sender_id' => '']);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));

    $report = dlrDoctorJson();
    $einvoice = array_values(array_filter($report['checks'], fn (array $row): bool => $row['check'] === 'e-invoice (SK eFaktúra / Peppol) delivery'));
    expect($einvoice)->toHaveCount(1)->and($einvoice[0]['status'])->not->toBe('OK');

    $summary = dlrSummaryRow($report['checks']);
    expect($summary['status'])->toBe('WARN')->and($summary['detail'])->toContain('e-invoice (SK eFaktúra / Peppol) delivery');

    $others = array_filter($report['checks'], fn (array $row): bool => $row['area'] !== 'launch');
    $expected = array_filter($others, fn (array $row): bool => $row['status'] === 'FAIL') === [] ? 0 : 1;
    expect($report['exit'])->toBe($expected);
});

it('never puts the backup disk or the e-invoice driver into the summary detail', function () {
    Storage::fake('dlr-secret-disk');
    config(['onhost.platform_backup.disk' => 'dlr-secret-disk']);

    $summary = dlrSummaryRow(dlrDoctorJson()['checks']);
    expect($summary['detail'])->not->toContain('dlr-secret-disk')
        ->and($summary['detail'])->not->toContain((string) config('onhost.platform_backup.disk'));
    $driver = (string) config('onhost.einvoice.driver', '');
    if ($driver !== '') {
        expect($summary['detail'])->not->toContain($driver);
    }
});

/** @param  list<array<string, mixed>>  $checks */
function dlrAssertBlockingKeys(array $checks): void
{
    foreach ($checks as $row) {
        expect($row)->toHaveKey('blocking')
            ->and($row['blocking'])->toBeBool();
        if ($row['status'] === 'FAIL') {
            expect($row['blocking'])->toBeTrue((string) $row['check']);
        }
    }
}

it('writes a boolean blocking key on every JSON row', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $checks = dlrDoctorJson()['checks'];

    dlrAssertBlockingKeys($checks);
    expect(dlrSummaryRow($checks)['blocking'])->toBeFalse();
});
