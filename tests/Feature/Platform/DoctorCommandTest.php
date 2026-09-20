<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Artisan;
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
