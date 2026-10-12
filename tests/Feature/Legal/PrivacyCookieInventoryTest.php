<?php

declare(strict_types=1);

use App\Http\Support\CookieInventory;
use Database\Seeders\LegalEntitySeeder;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Domain\Orders\Models\ConsentDocument;

/*
 * TASK-0179 (staging browser check 2026-10-13, B1): the privacy page names every cookie a visitor receives, including the
 * aaPanel WAF's `server_session_<hash>` that the application itself never sets — as an annex under the document, so the
 * published (hash-frozen) privacy text does not change.
 */

it('lists the application cookies and the hosting firewall cookie under the privacy document', function () {
    $this->seed([LegalEntitySeeder::class]);
    config()->set('session.lifetime', 120);
    config()->set('session.expire_on_close', false);
    $document = ConsentDocument::current('privacy');
    $hashBefore = LegalDocuments::hash('privacy', (string) $document->version);

    $page = (string) $this->get('/dokumenty/ochrana-osobnich-udaju')->assertOk()->getContent();

    expect($page)->toContain('id="cookies"')->toContain('Seznam cookies')
        ->toContain('<code>'.e((string) config('session.cookie')).'</code>')
        ->toContain('<code>XSRF-TOKEN</code>')
        ->toContain('<code>onhost_ref</code>')
        ->toContain('<code>server_session_&lt;hash&gt;</code>')
        ->toContain('aaPanel WAF')
        ->toContain('2 hodiny')->toContain('30 dní')->toContain('10 dní');
    // the annex sits after the document text, never inside it, and the frozen text of the version is untouched
    expect(strpos($page, 'id="cookies"'))->toBeGreaterThan(strpos($page, '</article>'));
    expect(LegalDocuments::hash('privacy', (string) $document->version))->toBe($hashBefore);
});

it('shows the cookie list only on the privacy page', function () {
    $this->seed([LegalEntitySeeder::class]);

    expect((string) $this->get('/dokumenty/vop')->assertOk()->getContent())->not->toContain('Seznam cookies')->not->toContain('server_session_');
});

it('drops the firewall row on a host whose WAF cookie is off and follows the session settings', function () {
    config()->set('onhost.privacy.hosting_waf_cookie', false);
    config()->set('session.lifetime', 90);
    config()->set('session.expire_on_close', false);

    $rows = CookieInventory::rows();

    expect(array_column($rows, 'name'))->not->toContain(CookieInventory::WAF_COOKIE)->toContain('XSRF-TOKEN');
    expect($rows[0]['lifetime'])->toBe('90 minut')->and($rows[1]['lifetime'])->toBe('90 minut');

    config()->set('session.lifetime', 1440);
    expect(CookieInventory::rows()[0]['lifetime'])->toBe('1 den');

    config()->set('session.expire_on_close', true);
    expect(CookieInventory::rows()[0]['lifetime'])->toBe('do zavření prohlížeče');
});
