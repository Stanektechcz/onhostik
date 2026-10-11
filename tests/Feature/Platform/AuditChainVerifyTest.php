<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Platform\Audit\AuditChainVerifier;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/** @return list<string> the ids of $count chained audit events, a second apart */
function auditChainSeed(CommandContext $context, int $count = 4): array
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        Carbon::setTestNow(now()->addSecond());
        $ids[] = app(AuditRecorder::class)->record($context, "chain.test.{$i}", 'ok', ['n' => $i])->id;
    }

    return $ids;
}

function auditDoctorRows(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->keyBy('check')->all();
}

afterEach(fn () => Carbon::setTestNow());

it('passes an intact chain', function () {
    auditChainSeed($this->contextFor(...$this->customerWithOrganization()));

    $result = app(AuditChainVerifier::class)->verify();

    expect($result['ok'])->toBeTrue()->and($result['checked'])->toBeGreaterThanOrEqual(4)->and($result['broken_id'])->toBeNull();
    expect(Artisan::call('onhost:audit:verify'))->toBe(0);
});

it('fails a chain whose row was modified and names that row', function () {
    $ids = auditChainSeed($this->contextFor(...$this->customerWithOrganization()));
    DB::table('audit_events')->where('id', $ids[1])->update(['result' => 'denied']);

    $result = app(AuditChainVerifier::class)->verify();

    expect($result['ok'])->toBeFalse()->and($result['broken_id'])->toBe($ids[1])->and($result['reason'])->toContain('modified');
    expect(Artisan::call('onhost:audit:verify'))->toBe(1);
});

it('fails a chain whose middle row was deleted at the row after the gap', function () {
    $ids = auditChainSeed($this->contextFor(...$this->customerWithOrganization()));
    DB::table('audit_events')->where('id', $ids[1])->delete();

    $result = app(AuditChainVerifier::class)->verify();

    expect($result['ok'])->toBeFalse()->and($result['broken_id'])->toBe($ids[2])->and($result['reason'])->toContain('prev_hash');
});

it('stops at the requested row limit', function () {
    auditChainSeed($this->contextFor(...$this->customerWithOrganization()), 3);

    expect(app(AuditChainVerifier::class)->verify(2)['checked'])->toBe(2);
});

it('shows the doctor row red after a failed verify and green after an intact one', function () {
    $ids = auditChainSeed($this->contextFor(...$this->customerWithOrganization()));
    Artisan::call('onhost:audit:verify');
    expect(auditDoctorRows()['audit chain intact'])->toMatchArray(['status' => 'OK']);

    DB::table('audit_events')->where('id', $ids[2])->update(['action' => 'chain.tampered']);
    Artisan::call('onhost:audit:verify');
    $row = auditDoctorRows()['audit chain intact'];

    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('BROKEN')->and($row['detail'])->toContain($ids[2]);
    app()->detectEnvironment(fn () => 'production');
    expect(auditDoctorRows()['audit chain intact']['status'])->toBe('FAIL');
});

it('warns when the audit verify never ran or is older than 48 hours', function () {
    expect(auditDoctorRows()['audit chain verified']['status'])->toBe('WARN');

    app(AutomationLedger::class)->record('audit.verify', ['checked' => 3, 'ok' => true]);
    expect(auditDoctorRows()['audit chain verify is recent']['status'])->toBe('OK');

    Carbon::setTestNow(now()->addHours(49));
    expect(auditDoctorRows()['audit chain verify is recent']['status'])->toBe('WARN');
});
