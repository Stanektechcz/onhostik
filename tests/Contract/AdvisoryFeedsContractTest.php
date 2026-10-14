<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Advisories\AdvisoryIngest;
use Onhost\Domain\Advisories\Models\Advisory;
use Onhost\Domain\Advisories\Models\AdvisorySource;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Advisories\CisaKevFeed;
use Onhost\Providers\Advisories\NvdFeed;
use Onhost\Providers\Advisories\OsvLookup;
use Onhost\Providers\Advisories\WordfenceFeed;

/*
 * SN-1: the vulnerability feeds behind AdvisoryFeed / AdvisoryLookup. Shapes as the vendors document them (CISA KEV catalogue,
 * NVD CVE API 2.0, Wordfence Intelligence v3 production feed, OSV.dev v1) — ASSUMED from the public docs until SN-0 records real
 * answers. Feed text is plain text with no markup, references only https, a failed feed records a redacted error and does not stop
 * the others, and an API key travels only in its header.
 */

const SN_KEV = 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json';
const SN_NVD = 'https://services.nvd.nist.gov/rest/json/cves/2.0*';
const SN_WORDFENCE = 'https://www.wordfence.com/api/intelligence/v3/vulnerabilities/production';
const SN_OSV = 'https://api.osv.dev/v1/*';

beforeEach(function () {
    config(['onhost.advisories.feeds' => ['kev', 'nvd', 'wordfence', 'osv'], 'onhost.advisories.wordfence_api_key' => 'wf-test-key', 'onhost.advisories.nvd_api_key' => '', 'onhost.advisories.initial_days' => 14]);
    $this->travelTo(now()->parse('2026-10-14 12:00:00', 'UTC'));
});

it('reads the CISA KEV catalogue since its cursor and marks the CVEs exploited', function () {
    Http::fake([SN_KEV => Http::response(['catalogVersion' => '2026.10.14', 'vulnerabilities' => [
        ['cveID' => 'CVE-2026-1111', 'vendorProject' => 'WordPress', 'product' => 'WordPress', 'vulnerabilityName' => 'WordPress <b>Core</b> RCE', 'dateAdded' => '2026-10-13', 'shortDescription' => 'Remote code execution.', 'requiredAction' => 'Apply updates.'],
        ['cveID' => 'CVE-2020-0001', 'vendorProject' => 'Old', 'product' => 'Thing', 'vulnerabilityName' => 'Old one', 'dateAdded' => '2020-01-01'],
        ['cveID' => 'not-a-cve', 'dateAdded' => '2026-10-13'],
    ]])]);
    $batch = app(CisaKevFeed::class)->fetch(null);

    expect($batch->advisories)->toHaveCount(1)->and($batch->cursor)->toBe('2026-10-13')
        ->and($batch->advisories[0]['key'])->toBe('CVE-2026-1111')->and($batch->advisories[0]['kev'])->toBeTrue()
        ->and($batch->advisories[0]['title'])->toBe('WordPress Core RCE')
        ->and($batch->advisories[0]['summary'])->toContain('CISA: Apply updates.');
    expect(app(CisaKevFeed::class)->fetch('2026-10-14')->advisories)->toBe([]); // the same day is read again (dateAdded has no time); upserting it twice changes nothing
});

it('reads NVD by last change, keeps only the watched CPE products and maps WordPress core to its ecosystem', function () {
    Http::fake([SN_NVD => Http::response(['resultsPerPage' => 2, 'startIndex' => 0, 'totalResults' => 2, 'vulnerabilities' => [
        ['cve' => ['id' => 'CVE-2026-2222', 'published' => '2026-10-10T10:00:00.000', 'lastModified' => '2026-10-12T10:00:00.000', 'vulnStatus' => 'Analyzed',
            'descriptions' => [['lang' => 'en', 'value' => 'WordPress before 6.6.3 allows XSS.']],
            'metrics' => ['cvssMetricV31' => [['cvssData' => ['baseScore' => 6.1, 'baseSeverity' => 'MEDIUM']]]],
            'configurations' => [['nodes' => [['cpeMatch' => [['vulnerable' => true, 'criteria' => 'cpe:2.3:a:wordpress:wordpress:*:*:*:*:*:*:*:*', 'versionStartIncluding' => '6.6', 'versionEndExcluding' => '6.6.3']]]]]],
            'references' => [['url' => 'https://wordpress.org/news/'], ['url' => 'http://insecure.example/']]]],
        ['cve' => ['id' => 'CVE-2026-3333', 'vulnStatus' => 'Analyzed', 'descriptions' => [['lang' => 'en', 'value' => 'Some router.']],
            'configurations' => [['nodes' => [['cpeMatch' => [['vulnerable' => true, 'criteria' => 'cpe:2.3:h:acme:router:*:*:*:*:*:*:*:*']]]]]]]],
    ]])]);
    $batch = app(NvdFeed::class)->fetch(null);

    expect($batch->advisories)->toHaveCount(1)->and($batch->complete)->toBeTrue()->and($batch->cursor)->toBe('2026-10-14T12:00:00Z');
    $row = $batch->advisories[0];
    expect($row['key'])->toBe('CVE-2026-2222')->and($row['severity'])->toBe('medium')->and($row['cvss'])->toBe(6.1)
        ->and($row['affected'][0])->toMatchArray(['ecosystem' => 'wordpress-core', 'product' => 'wordpress', 'introduced' => '6.6', 'fixed' => '6.6.3'])
        ->and($row['references'])->toBe(['https://wordpress.org/news/']);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'lastModStartDate=2026-09-30T12') && ! $r->hasHeader('apiKey'));
});

it('resumes an NVD window it could not finish and sends the key only as its header', function () {
    config(['onhost.advisories.nvd_max_pages' => 1, 'onhost.advisories.nvd_api_key' => 'nvd-test-key']);
    Http::fake([SN_NVD => Http::response(['resultsPerPage' => 2000, 'startIndex' => 0, 'totalResults' => 4500, 'vulnerabilities' => []])]);
    $batch = app(NvdFeed::class)->fetch('2026-10-01T00:00:00Z');

    expect($batch->complete)->toBeFalse()->and($batch->cursor)->toBe('2026-10-01T00:00:00Z|2000|2026-10-14T12:00:00Z');
    Http::assertSent(fn (Request $r) => $r->header('apiKey') === ['nvd-test-key'] && ! str_contains($r->url(), 'nvd-test-key'));
});

it('reads Wordfence with its bearer key, maps plugins and themes and turns an inclusive range into the first patched version', function () {
    Http::fake([SN_WORDFENCE => Http::response([
        'f1' => ['id' => 'f1', 'title' => 'Contact Form 7 <= 5.9.7 - XSS', 'description' => 'Stored XSS.', 'cve' => 'CVE-2026-4444', 'cvss' => ['score' => 7.2, 'rating' => 'High'],
            'software' => [['type' => 'plugin', 'slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'patched_versions' => ['5.9.8'], 'affected_versions' => ['* - 5.9.7' => ['from_version' => '*', 'from_inclusive' => true, 'to_version' => '5.9.7', 'to_inclusive' => true]]]],
            'references' => ['https://www.wordfence.com/threat-intel/f1'], 'published' => '2026-10-12 08:00:00', 'updated' => '2026-10-13 08:00:00'],
        'f2' => ['id' => 'f2', 'title' => 'Old', 'software' => [['type' => 'theme', 'slug' => 'astra']], 'updated' => '2020-01-01 00:00:00'],
        'f3' => ['id' => 'f3', 'title' => 'Info only', 'informational' => true, 'updated' => '2026-10-13 09:00:00'],
    ])]);
    $batch = app(WordfenceFeed::class)->fetch(null);

    expect($batch->advisories)->toHaveCount(1)->and($batch->cursor)->toBe('2026-10-13 08:00:00');
    $row = $batch->advisories[0];
    expect($row['key'])->toBe('CVE-2026-4444')->and($row['severity'])->toBe('high')->and($row['title'])->toBe('Contact Form 7 <= 5.9.7 - XSS')
        ->and($row['affected'][0])->toMatchArray(['ecosystem' => 'wordpress-plugin', 'product' => 'contact-form-7', 'last_affected' => '5.9.7', 'fixed' => '5.9.8'])
        ->and($row['aliases'])->toBe(['CVE-2026-4444', 'wordfence:f1']);
    Http::assertSent(fn (Request $r) => $r->header('Authorization') === ['Bearer wf-test-key']);

    config(['onhost.advisories.wordfence_api_key' => '']);
    expect(app(WordfenceFeed::class)->enabled())->toBeFalse();
});

it('asks OSV for the libraries we run and reads the details of what it names', function () {
    Http::fake([
        'https://api.osv.dev/v1/querybatch' => Http::response(['results' => [['vulns' => [['id' => 'GHSA-aaaa-bbbb-cccc']]]]]),
        'https://api.osv.dev/v1/vulns/GHSA-aaaa-bbbb-cccc' => Http::response(['id' => 'GHSA-aaaa-bbbb-cccc', 'aliases' => ['CVE-2026-5555'], 'summary' => 'Guzzle header leak', 'details' => 'Leaks headers.',
            'database_specific' => ['severity' => 'HIGH'], 'affected' => [['package' => ['ecosystem' => 'Packagist', 'name' => 'guzzlehttp/guzzle'], 'ranges' => [['type' => 'ECOSYSTEM', 'events' => [['introduced' => '0'], ['fixed' => '7.9.9']]]]]],
            'references' => [['type' => 'ADVISORY', 'url' => 'https://github.com/advisories/GHSA-aaaa-bbbb-cccc']], 'modified' => '2026-10-12T00:00:00Z']),
    ]);
    $rows = app(OsvLookup::class)->lookup([['ecosystem' => 'Packagist', 'product' => 'guzzlehttp/guzzle', 'version' => '7.9.2']]);

    expect($rows)->toHaveCount(1)->and($rows[0]['key'])->toBe('CVE-2026-5555')->and($rows[0]['severity'])->toBe('high')
        ->and($rows[0]['affected'][0])->toBe(['ecosystem' => 'Packagist', 'product' => 'guzzlehttp/guzzle', 'fixed' => '7.9.9'])
        ->and($rows[0]['references'])->toBe(['https://github.com/advisories/GHSA-aaaa-bbbb-cccc']);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.osv.dev/v1/querybatch' && $r['queries'][0]['package']['name'] === 'guzzlehttp/guzzle');
});

it('merges one CVE from several feeds, records each feed and keeps going when one fails', function () {
    Http::fake([
        SN_KEV => Http::response(['vulnerabilities' => [['cveID' => 'CVE-2026-4444', 'vendorProject' => 'Contact Form 7', 'product' => 'plugin', 'vulnerabilityName' => 'CF7 XSS', 'dateAdded' => '2026-10-13']]]),
        SN_NVD => Http::response(['message' => 'Service unavailable'], 503),
        SN_WORDFENCE => Http::response(['f1' => ['id' => 'f1', 'title' => 'Contact Form 7 <= 5.9.7 - XSS', 'cve' => 'CVE-2026-4444', 'cvss' => ['score' => 7.2, 'rating' => 'High'],
            'software' => [['type' => 'plugin', 'slug' => 'contact-form-7', 'patched_versions' => ['5.9.8'], 'affected_versions' => [['from_version' => '*', 'to_version' => '5.9.7', 'to_inclusive' => true]]]], 'updated' => '2026-10-13 08:00:00']]),
        SN_OSV => Http::response(['results' => []]),
    ]);
    InventoryItem::query()->create(['subject_type' => InventoryItem::PLATFORM, 'subject_id' => '', 'ecosystem' => 'Packagist', 'product' => 'laravel/framework', 'version' => '13.0.0', 'label' => 'Control plane · laravel/framework', 'seen_at' => now()]);
    $run = app(AdvisoryIngest::class)->run();

    expect($run['feeds'])->toBe(4)->and($run['failed'])->toBe(1)->and(Advisory::query()->count())->toBe(1);
    $advisory = Advisory::query()->firstOrFail();
    expect($advisory->kev)->toBeTrue()->and($advisory->severity)->toBe('high')->and($advisory->title)->toBe('Contact Form 7 <= 5.9.7 - XSS')
        ->and($advisory->sources)->toContain('kev', 'wordfence');
    expect(AdvisorySource::query()->find('nvd')->last_error)->not->toBeNull()->and(AdvisorySource::query()->find('nvd')->last_success_at)->toBeNull()
        ->and(AdvisorySource::query()->find('kev')->cursor)->toBe('2026-10-13')->and(AdvisorySource::query()->find('osv')->last_success_at)->not->toBeNull();
    expect(fn () => app(NvdFeed::class)->fetch(null))->toThrow(ProviderException::class);
});

it('keeps WordPress plugins and themes from NVD without a Wordfence key and maps their CPE to the slug', function () {
    config(['onhost.advisories.wordfence_api_key' => '']);
    Http::fake([SN_NVD => Http::response(['resultsPerPage' => 2, 'startIndex' => 0, 'totalResults' => 2, 'vulnerabilities' => [
        ['cve' => ['id' => 'CVE-2026-6001', 'vulnStatus' => 'Analyzed', 'descriptions' => [['lang' => 'en', 'value' => 'The Contact Form 7 plugin for WordPress is vulnerable to XSS in versions up to 5.9.7.']],
            'configurations' => [['nodes' => [['cpeMatch' => [['vulnerable' => true, 'criteria' => 'cpe:2.3:a:rocklobster:contact_form_7:*:*:*:*:*:wordpress:*:*', 'versionEndExcluding' => '5.9.8']]]]]]]],
        ['cve' => ['id' => 'CVE-2026-6002', 'vulnStatus' => 'Analyzed', 'descriptions' => [['lang' => 'en', 'value' => 'The Astra theme for WordPress allows CSRF.']],
            'configurations' => [['nodes' => [['cpeMatch' => [['vulnerable' => true, 'criteria' => 'cpe:2.3:a:brainstormforce:astra:*:*:*:*:*:wordpress:*:*', 'versionEndIncluding' => '4.1.0']]]]]]]],
    ]])]);
    $rows = app(NvdFeed::class)->fetch(null)->advisories;

    expect(app(WordfenceFeed::class)->enabled())->toBeFalse()
        ->and($rows[0]['affected'][0])->toBe(['ecosystem' => 'wordpress-plugin', 'product' => 'contact-form-7', 'fixed' => '5.9.8'])
        ->and($rows[1]['affected'][0])->toBe(['ecosystem' => 'wordpress-theme', 'product' => 'astra', 'last_affected' => '4.1.0']);
});
