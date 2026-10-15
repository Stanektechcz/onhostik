<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * D4 (panel decision 2026-10-15): OIDC is reserved configuration, not a working feature. The Doctor reports it OK
 * while off and as a non-blocking WARN once OIDC_ENABLED=true, even in production.
 */
function doctorOidcRow(): ?array
{
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    return collect($report['checks'])->firstWhere('check', 'OIDC not implemented');
}

it('reports OIDC as OK while it stays off', function () {
    config(['onhost.identity.oidc.enabled' => false]);

    $row = doctorOidcRow();
    expect($row)->not->toBeNull()->and($row['status'])->toBe('OK');
});

it('warns, never fails, when OIDC_ENABLED=true although no OIDC sign-in exists', function () {
    config(['onhost.identity.oidc.enabled' => true]);

    foreach ([false, true] as $production) {
        if ($production) {
            app()->instance('env', 'production');
        }
        $row = doctorOidcRow();
        expect($row)->not->toBeNull()
            ->and($row['status'])->toBe('WARN')
            ->and($row['detail'])->toContain('OIDC_ENABLED=true')
            ->and($row['remedy'])->toContain('OIDC_ENABLED=false');
    }
});
