<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Artisan;

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
