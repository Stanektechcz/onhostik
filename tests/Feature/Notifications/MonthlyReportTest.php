<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CollectorHeartbeat;
use Onhost\Domain\Services\Models\Backup;

/*
 * Monthly customer report (W1-10): what was checked per service and when; unverified is said as unverified; nothing is
 * called "safe"; once per organization per month; an organization that switched the digests off gets nothing.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Date::setTestNow('2026-10-03 08:00:00');
});

afterEach(fn () => Date::setTestNow());

function monthlyBackup($service, array $attrs): Backup
{
    return Backup::query()->create($attrs + ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'scheduled', 'state' => 'completed', 'size_bytes' => 4096, 'protected' => false]);
}

function monthlyService($org, array $tags = [])
{
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['tags' => array_merge((array) $service->tags, ['tls' => ['expires_at' => now()->addDays(60)->toIso8601String(), 'checked_at' => now()->subHours(2)->toIso8601String(), 'names' => ['shop.cz']]], $tags)])->save();

    return $service;
}

it('lists what was checked with the day it was checked and never calls anything safe', function () {
    [, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    $service = monthlyService($org, ['restore_test' => ['last_at' => now()->subDays(5)->toIso8601String(), 'outcome' => 'ok']]);
    monthlyBackup($service, ['started_at' => now()->subDay(), 'finished_at' => now()->subDay()]);

    $report = app(DigestService::class)->customerMonthly($org);

    expect($report['title'])->toBe('Měsíční přehled služeb 10/2026');
    $text = $report['text'];
    expect($text)->toContain('Certifikát HTTPS')->toContain('ověřeno 3. 10. 2026')
        ->and($text)->toContain('Poslední úplná záloha: 2. 10. 2026')
        ->and($text)->toContain('Test obnovy zálohy')->toContain('ověřeno 28. 9. 2026')
        ->and($text)->not->toMatch('/\b(bezpečný|bezpečná|bezpečné|secure|safe)\b/iu');
});

it('shows a stale or never measured finding as neověřeno with the last observed time, not as fine', function () {
    [, $org] = $this->customerWithOrganization();
    $service = monthlyService($org, [
        'tls' => ['expires_at' => now()->addDays(60)->toIso8601String(), 'checked_at' => '2026-09-20T10:15:00+00:00', 'names' => ['shop.cz']],
        'security_headers' => ['checked_at' => '2026-09-01T04:00:00+00:00', 'missing' => []],
    ]);

    $lines = app(DigestService::class)->customerMonthly($org)['sections'][(string) ($service->hostname ?: ($service->label ?: $service->name))];
    $cert = collect($lines)->first(fn ($l) => str_starts_with($l, 'Certifikát HTTPS'));
    $headers = collect($lines)->first(fn ($l) => str_starts_with($l, 'Hlavičky odpovědí webu'));
    $backup = collect($lines)->first(fn ($l) => str_starts_with($l, 'Poslední úplná záloha'));

    expect($cert)->toContain('neověřeno')->toContain('20. 9. 2026')->not->toContain('platí do')
        ->and($headers)->toContain('neověřeno')->toContain('1. 9. 2026')->not->toContain('posílá')
        ->and($backup)->toContain('neověřeno')->not->toContain('0');
});

it('ignores an incomplete backup set and reports the newest complete one', function () {
    [, $org] = $this->customerWithOrganization();
    $service = monthlyService($org);
    monthlyBackup($service, ['started_at' => now()->subDays(2), 'finished_at' => now()->subDays(2)]);
    monthlyBackup($service, ['started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3), 'meta' => ['incomplete' => ['db_one']]]);

    $text = app(DigestService::class)->customerMonthly($org)['text'];

    expect($text)->toContain('Poslední úplná záloha: 1. 10. 2026')->not->toContain('Poslední úplná záloha: 3. 10. 2026');
});

it('sends once per month and nothing on a rerun', function () {
    [, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    monthlyService($org);

    expect(app(DigestService::class)->monthly())->toMatchArray(['organizations' => 1, 'sent' => 1, 'skipped' => 0]);
    expect(app(DigestService::class)->monthly())->toMatchArray(['sent' => 0, 'skipped' => 1]);
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'digest.monthly')->count())->toBe(1)
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'digest-monthly')->count())->toBe(1);

    Date::setTestNow('2026-11-02 08:00:00'); // the next month is a new report
    expect(app(DigestService::class)->monthly())->toMatchArray(['sent' => 1]);
});

it('sends nothing to an organization that switched the digests off', function () {
    [, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    monthlyService($org);
    $org->forceFill(['settings' => array_merge((array) $org->settings, ['digest' => ['frequency' => 'off']])])->save();

    expect(app(DigestService::class)->monthly())->toMatchArray(['sent' => 0, 'skipped' => 1])
        ->and(Notification::query()->where('event', 'digest.monthly')->exists())->toBeFalse()
        ->and(MailOutbox::query()->where('template_key', 'digest-monthly')->exists())->toBeFalse();
});

it('reads in English for an English account', function () {
    [, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    $org->forceFill(['locale' => 'en'])->save();
    $service = monthlyService($org, ['security_headers' => ['checked_at' => '2026-09-01T04:00:00+00:00', 'missing' => []]]);
    monthlyBackup($service, ['started_at' => now()->subDay(), 'finished_at' => now()->subDay()]);

    $report = app(DigestService::class)->customerMonthly($org->fresh());

    expect($report['title'])->toBe('Monthly service report 10/2026')
        ->and($report['text'])->toContain('HTTPS certificate')->toContain('verified 3 Oct 2026')->toContain('Last complete backup: 2 Oct 2026')
        ->and($report['text'])->toContain('not verified')->toContain('1 Sep 2026')
        ->and($report['text'])->not->toMatch('/\b(secure|safe|bezpečný)\b/iu');

    app(DigestService::class)->monthly();
    expect(MailOutbox::query()->where('template_key', 'digest-monthly')->value('locale'))->toBe('en');
});

it('is registered in the automation ledger and the collector heartbeat', function () {
    expect(CollectorHeartbeat::EXPECTED)->toHaveKey('digest.monthly')
        ->and(collect(app(AutomationLedger::class)->overview())->pluck('key')->all())->toContain('digest.monthly');
});
