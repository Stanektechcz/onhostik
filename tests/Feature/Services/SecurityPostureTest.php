<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\SecurityPosture;

/*
 * Security posture of a service (W2-1): 22 controls, each pass | fail | unknown | not_applicable with its evidence. Nothing
 * is "pass" without a current measurement; what ONhost does not measure is `unknown`; there is no overall verdict.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Date::setTestNow('2026-10-11 08:00:00');
});

afterEach(fn () => Date::setTestNow());

/** @return array<string,array<string,mixed>> controls by id */
function postureOf(Service $service): array
{
    return collect(app(SecurityPosture::class)->run($service->refresh())['controls'])->keyBy('id')->all();
}

function postureWeb(object $org, array $tags = []): Service
{
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['tags' => array_merge((array) $service->tags, $tags)])->save();

    return $service->refresh();
}

it('lists the whole catalog and answers unknown for what nobody measured', function () {
    [, $org] = $this->customerWithOrganization();
    $service = postureWeb($org);

    $result = app(SecurityPosture::class)->run($service);
    $controls = collect($result['controls'])->keyBy('id');

    expect($controls->keys()->all())->toBe(array_keys(SecurityPosture::CATALOG))->and($controls)->toHaveCount(22);
    foreach (['iam-access-review', 'server-patching', 'ssh-hardening', 'web-input-validation', 'secrets-management', 'deploy-rollback', 'incident-response', 'agent-prompt-injection', 'agent-action-scope'] as $id) {
        expect($controls[$id]['status'])->toBe('unknown')->and($controls[$id]['note']['cs'])->toBe('ONhost toto zatím neměří.')->and($controls[$id]['note']['en'])->toBe('ONhost does not measure this yet.');
    }
    expect($controls['web-dependency-updates']['status'])->toBe('unknown')->and($controls['web-dependency-updates']['note']['en'])->toContain('does not measure this yet'); // W2-2: measured only for WordPress components it can see
    // a fresh site: nothing of the nightly collectors has looked at it
    foreach (['web-security-headers', 'web-session-security', 'web-upload-isolation', 'backup-restore', 'dns-change-control', 'network-exposure'] as $id) {
        expect($controls[$id]['status'])->toBe('unknown');
    }
    expect($controls['mail-authentication']['status'])->toBe('not_applicable')
        ->and($controls['tls-validity']['status'])->toBe('unknown') // nothing was ever measured on the node
        ->and($result['summary']['pass'] + $result['summary']['fail'] + $result['summary']['unknown'] + $result['summary']['not_applicable'])->toBe(22);
    foreach ($controls as $control) {
        expect($control['title']['cs'])->not->toBe('')->and($control['title']['en'])->not->toBe('')->and($control)->toHaveKeys(['status', 'evidence', 'advice']);
    }
});

it('fails a control with a current bad measurement and gives the advice', function () {
    [, $org] = $this->customerWithOrganization();
    $service = postureWeb($org, [
        'security_headers' => ['checked_at' => now()->subHours(3)->toIso8601String(), 'missing' => ['hsts', 'cookie_httponly']],
        'tls' => ['expires_at' => now()->addDays(60)->toIso8601String(), 'checked_at' => now()->subHours(2)->toIso8601String(), 'names' => ['shop.cz']],
        'integrity' => ['findings' => [['kind' => 'php_in_uploads', 'files' => ['wp-content/uploads/x.php'], 'more' => false]], 'checked_at' => now()->subHours(5)->toIso8601String()],
        'restore_test' => ['last_at' => now()->subDays(3)->toIso8601String(), 'outcome' => 'failed'],
    ]);

    $c = postureOf($service);

    expect($c['web-security-headers']['status'])->toBe('fail')->and($c['web-security-headers']['advice']['en'])->toContain('Strict-Transport-Security')->not->toContain('HttpOnly')
        ->and($c['web-security-headers']['evidence'][0])->toMatchArray(['source' => 'security_headers', 'state' => 'current'])->and($c['web-security-headers']['evidence'][0]['observed_at'])->not->toBeNull()
        ->and($c['web-session-security']['status'])->toBe('fail')->and($c['web-session-security']['advice']['cs'])->toContain('HttpOnly')
        ->and($c['tls-validity']['status'])->toBe('pass')
        ->and($c['web-upload-isolation']['status'])->toBe('fail')->and($c['web-upload-isolation']['advice']['en'])->toContain('upload folders')
        ->and($c['backup-restore']['status'])->toBe('fail')->and($c['backup-restore']['advice']['cs'])->not->toBe('');
});

it('never passes on a stale reading or a failed collector', function () {
    [, $org] = $this->customerWithOrganization();
    $service = postureWeb($org, ['security_headers' => ['checked_at' => now()->subDays(9)->toIso8601String(), 'missing' => []]]);

    $c = postureOf($service);
    expect($c['web-security-headers']['status'])->toBe('unknown')->and($c['web-security-headers']['evidence'][0]['state'])->toBe('stale')
        ->and($c['web-security-headers']['note']['en'])->toContain('old');

    $service->forceFill(['tags' => array_merge((array) $service->tags, ['security_headers' => ['checked_at' => now()->subHour()->toIso8601String(), 'missing' => [], 'error' => 'unreachable']])])->save();
    $c = postureOf($service);
    expect($c['web-security-headers']['status'])->toBe('unknown')->and($c['web-security-headers']['evidence'][0]['state'])->toBe('collection_error');

    $service->forceFill(['tags' => array_merge((array) $service->tags, ['security_headers' => ['checked_at' => now()->subHour()->toIso8601String(), 'missing' => []]])])->save();
    expect(postureOf($service)['web-security-headers']['status'])->toBe('pass');
});

it('judges the second factor of the members from their accounts and counts only', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = postureWeb($org);

    $c = postureOf($service);
    expect($c['iam-mfa']['status'])->toBe('fail')->and($c['iam-mfa']['advice']['en'])->toContain('1 of 1')->and(json_encode($c['iam-mfa']))->not->toContain($owner->email);

    $owner->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    expect(postureOf($service)['iam-mfa']['status'])->toBe('pass');
});

it('reads shares that carry manage rights without an end date as a failed least-privilege check', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = postureWeb($org);
    expect(postureOf($service)['iam-least-privilege']['status'])->toBe('unknown'); // no shares: the members\' own roles are not evaluated

    ServiceAccessGrant::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'email' => 'agency@example.cz', 'capabilities' => ['view', 'manage'], 'state' => 'active', 'granted_by' => $owner->id]);
    $c = postureOf($service);
    expect($c['iam-least-privilege']['status'])->toBe('fail')->and($c['iam-least-privilege']['advice']['cs'])->toContain('datum konce')->and(json_encode($c['iam-least-privilege']))->not->toContain('agency@example.cz');

    ServiceAccessGrant::query()->where('service_id', $service->id)->update(['expires_at' => now()->addDays(14)]);
    expect(postureOf($service)['iam-least-privilege']['status'])->toBe('pass');
});

it('takes mail authentication from the nightly mail check and monitoring from the monitor plus the audit chain', function () {
    [, $org] = $this->customerWithOrganization();
    $service = postureWeb($org);
    MailDomain::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'remote_id' => 9, 'remote_node' => '1', 'state' => 'active', 'sending_enabled' => true]);
    expect(postureOf($service)['mail-authentication']['status'])->toBe('unknown');

    $service->forceFill(['tags' => array_merge((array) $service->tags, ['mail_auth' => ['checked_at' => now()->subHours(4)->toIso8601String(), 'domains' => [
        ['domain' => 'shop.cz', 'error' => null, 'spf' => ['state' => 'ok'], 'dkim' => ['state' => 'ok', 'selector' => 'onh1'], 'dmarc' => ['state' => 'missing']],
    ]]])])->save();
    $c = postureOf($service);
    expect($c['mail-authentication']['status'])->toBe('fail')->and($c['mail-authentication']['advice']['en'])->toContain('DMARC is missing');

    expect($c['monitoring-audit']['status'])->toBe('fail'); // no uptime monitor is switched on
    app(AutomationLedger::class)->record('audit.verify', ['checked' => 10, 'ok' => false, 'broken_id' => 4], 'chain broken');
    expect(collect(postureOf($service)['monitoring-audit']['evidence'])->pluck('source')->all())->toBe(['uptime_monitor', 'audit_chain']); // evidence is listed per source
});

it('serves it read-only to whoever may read the service and to nobody else', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = postureWeb($org);

    $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$service->id}/security-posture")->assertOk()
        ->assertJsonPath('data.service_id', $service->id)->assertJsonCount(22, 'data.controls')->assertJsonPath('data.controls.0.id', 'iam-mfa')
        ->assertJsonStructure(['data' => ['controls' => [['id', 'title' => ['cs', 'en'], 'status', 'evidence', 'advice']], 'summary' => ['pass', 'fail', 'unknown', 'not_applicable']]]);

    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum')->getJson("/v1/services/{$service->id}/security-posture")->assertNotFound();
    $this->actingAs($owner, 'sanctum')->postJson("/v1/services/{$service->id}/security-posture")->assertStatus(405);
});

it('puts only counts in the monthly report, with no verdict', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [, $org] = $this->customerWithOrganization();
    postureWeb($org, ['security_headers' => ['checked_at' => now()->subHours(3)->toIso8601String(), 'missing' => []]]);

    $report = app(DigestService::class)->customerMonthly($org);
    $line = collect($report['sections'])->flatten()->first(fn ($l) => str_starts_with((string) $l, 'Bezpečnostní kontroly'));

    expect($line)->toMatch('/Bezpečnostní kontroly: \d+ splněno, \d+ nesplněno, \d+ neověřeno \(ověřeno 11\. 10\. 2026\)/')
        ->and($report['text'])->not->toMatch('/\b(bezpečný|bezpečná|bezpečné|secure|safe)\b/iu');

    $org->forceFill(['locale' => 'en'])->save();
    $en = collect(app(DigestService::class)->customerMonthly($org->fresh())['sections'])->flatten()->first(fn ($l) => str_starts_with((string) $l, 'Security controls'));
    expect($en)->toMatch('/Security controls: \d+ met, \d+ not met, \d+ not verified/');
});
