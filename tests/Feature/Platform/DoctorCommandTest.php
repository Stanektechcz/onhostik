<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

it('reports production readiness as warnings outside production and as a machine-readable JSON report', function () {
    $this->seed([LegalEntitySeeder::class]);
    $this->artisan('onhost:doctor')->assertSuccessful()->expectsOutputToContain('APP_KEY set')->expectsOutputToContain('WARN');

    expect(Artisan::call('onhost:doctor', ['--json' => true]))->toBe(0);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    $checks = collect($report['checks']);
    expect($report['fail'])->toBe(0)->and($report['warn'])->toBeGreaterThan(0)
        ->and($checks->pluck('check')->all())->toContain('secrets driver', 'CA bundle for outbound TLS', 'provider instances registered', 'legal entity exists', 'staff MFA required', 'no development accounts', 'bank account for transfers', 'card gateway (comgate) configured')
        ->and($checks->firstWhere('check', 'legal entity bank details real')['status'])->toBe('WARN') // seeded placeholder IBAN
        ->and($checks->firstWhere('check', 'APP_KEY set')['status'])->toBe('OK')
        ->and($checks->firstWhere('check', 'no development accounts')['status'])->toBe('OK');
});

it('writes one redacted report of how the installation stands: doctor findings, what the panels answer, latency, four eyes', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'ispconfig');
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $instance->forceFill(['capabilities' => array_merge((array) $instance->capabilities, ['prereqs' => ['checked_at' => now()->toIso8601String(), 'api' => 'up', 'backup_api' => 'broken', 'datalog_api' => 'ok',
        'probes' => ['backup_api' => 'refused: You do not have the permissions to access this function.', 'datalog_api' => 'ok', 'datalog_fields' => ['datalog_id', 'dbtable', 'dbidx', 'status', 'error']], 'warnings' => ['Kontrola „backup_api“: refused'], 'secret_note' => 'never reported']])])->save();
    $path = storage_path('app/test-staging-report.json');
    @unlink($path);

    Artisan::call('onhost:staging:report', ['--path' => $path]);
    $report = json_decode((string) file_get_contents($path), true);
    @unlink($path);

    expect($report['environment'])->toBe('testing')->and($report['doctor']['not_ok'])->not->toBeEmpty()->and($report['four_eyes'])->toHaveKeys(['enabled', 'deciders'])->and($report['latency']['target_s'])->toBe(30);
    $isp = collect($report['instances'])->firstWhere('key', 'ispconfig-shared01');
    expect($isp['prereqs']['probes']['datalog_fields'])->toContain('status', 'error')->and($isp['prereqs']['backup_api'])->toBe('broken')->and($isp['prereqs'])->not->toHaveKey('secret_note'); // an allow-list of what is reported, not everything the instance carries
    expect(json_encode($report))->not->toContain('remote_password')->not->toContain('secret_ref');
});

/*
 * The two `catalog` rows added by TASK-0017 (audit §5ad, brain card H278): the tracked backlog of metering gaps is
 * a standing WARN — never hidden, never blocking a deploy — while a *new*, untracked gap is a production FAIL.
 */
it('shows the known metering gap ratchet as a standing WARN, never a FAIL, even forced into production', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);

    foreach ([false, true] as $production) {
        if ($production) {
            app()->instance('env', 'production');
        }
        Artisan::call('onhost:doctor', ['--json' => true]);
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $row = collect($report['checks'])->firstWhere('check', 'no known metering gap');

        expect($row)->not->toBeNull("no 'no known metering gap' row (production={$production})")
            ->and($row['status'])->toBe('WARN', "expected WARN, not {$row['status']} (production={$production})")
            ->and($row['detail'])->toContain('known gap(s), tracked in PlanPromises::KNOWN_GAPS');
    }
});

it('fails a production deploy when a plan starts selling a number the registry has never tracked', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);
    $version = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    $version->forceFill(['entitlements' => array_merge((array) $version->entitlements, ['made_up_metric_xyz' => 42])])->save();

    // outside production the very same new gap is only a WARN — the row is blocking, not the environment
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    $row = collect($report['checks'])->firstWhere('check', 'the metering gap ratchet is not growing');
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('made_up_metric_xyz');

    app()->instance('env', 'production');
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    $row = collect($report['checks'])->firstWhere('check', 'the metering gap ratchet is not growing');

    expect($row['status'])->toBe('FAIL')->and($row['detail'])->toContain('made_up_metric_xyz');
});

/*
 * TASK-0022 catalog-versions: the gaps a code-defined catalogue revision retires leave PlanPromises::KNOWN_GAPS in the same
 * commit, but production only loses them when the operator applies the revision after the deploy. Until then the doctor
 * says so as a WARN naming the command — it must not turn the deploy red as an "untracked gap".
 */
it('shows a catalogue revision not yet applied as a WARN naming the command, not as a new gap', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);
    Artisan::call('onhost:doctor', ['--json' => true]); // outside production first, as the other production cases do: the secret store is built by then
    app()->instance('env', 'production');
    $row = function (string $check): array {
        Artisan::call('onhost:doctor', ['--json' => true]);
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        return (array) collect($report['checks'])->firstWhere('check', $check);
    };

    expect($row('the metering gap ratchet is not growing')['status'])->toBe('OK');
    $pending = $row('every catalogue revision is applied');
    expect($pending['status'])->toBe('WARN')->and($pending['detail'])->toContain('onhost:catalog:revise')->toContain('database/db-s');

    app()->instance('env', 'testing'); // the operator applies it; the production boot checks are not the subject here
    Artisan::call('onhost:catalog:revise', ['--apply' => true, '--yes' => true]);
    app()->instance('env', 'production');
    expect($row('every catalogue revision is applied')['status'])->toBe('OK')
        ->and($row('the metering gap ratchet is not growing')['status'])->toBe('OK')
        ->and($row('no customer holds a version promising an unkept number')['status'])->toBe('OK');
});
