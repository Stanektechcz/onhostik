<?php

declare(strict_types=1);

use App\Http\Support\LegalIdentitySeam;
use App\Http\Support\SurfaceRenderer;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Content\ContentService;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Platform\Errors\DomainError;

/*
 * I-R11 (owner decision of 2026-10-08, "Vše potvrzuji"): the operator's real, public identity — read from ARES (IČO 08094616: a natural
 * person trading under a trade licence, seat Molákova 2145/5, 628 00 Brno, not a VAT payer) — its telephone and its complaints mailbox
 * replace the placeholders; the prototype's invented telephone number, customer quotes, rating and case study are not served.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Cache::forget('surfaces:onhost-data.js');
});

/** @return array<string, mixed> */
function i11Data($test): array
{
    $js = $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return ['data' => json_decode($m[1] ?? '{}', true), 'js' => $js];
}

it('seeds the legal entity from the real public data and invents no VAT number', function () {
    $entity = LegalEntity::query()->where('key', 'onhost-cz')->firstOrFail();

    expect($entity->name)->toBe('Adrian Staněk')->and($entity->ico)->toBe('08094616')
        ->and($entity->address)->toMatchArray(['street' => 'Molákova 2145/5', 'city' => 'Brno', 'postal_code' => '628 00'])
        ->and($entity->dic)->toBeNull()->and($entity->vat_id)->toBeNull()
        ->and($entity->meta['registry'])->toContain('živnostenském rejstříku')->not->toContain('obchodním rejstříku')
        ->and(config('onhost.legal_entity.phone'))->toBe('+420 736 741 902')->and(config('onhost.legal_entity.email'))->toBe('reklamace@onhost.cz');
    // the bank details are not public data: they stay placeholders until the owner gives them
    expect($entity->iban)->toBe('CZ0000000000000000000000');
});

it('prints the real identity in the documents and leaves only the attorney, the notice and the bank details to publication', function () {
    $page = $this->get('/dokumenty/ochrana-osobnich-udaju')->assertOk()->getContent();
    expect($page)->toContain('Adrian Staněk')->toContain('08094616')->toContain('Molákova 2145/5')->toContain('reklamace@onhost.cz')->not->toContain('{{');

    $blockers = app(LegalDocuments::class)->blockers('2026-10', now('Europe/Prague')->addDays(40)->toImmutable(), 'Mgr. Test, advokát');
    expect(implode(' | ', $blockers))->not->toContain('placeholder identifiers')->not->toContain('ONHOST_LEGAL_PHONE')->not->toContain('ONHOST_LEGAL_EMAIL');
});

it('refuses publication while the withdrawal and complaint address is a no-reply sender', function () {
    config(['onhost.legal_entity.email' => '', 'mail.from.address' => 'noreply@onhost.cz']);

    expect(implode(' | ', app(LegalDocuments::class)->blockers('2026-10', now('Europe/Prague')->addDays(40)->toImmutable(), 'Mgr. Test')))->toContain('ONHOST_LEGAL_EMAIL');
});

it('words the 2026-10 provider line and SLA draft for a sole trader and keeps the compensation of 2026-09', function () {
    $terms = (string) LegalDocuments::text('terms', '2026-10');
    expect($terms)->toContain('{{entity_registry}}')->not->toContain('obchodním rejstříku vedeném')->not->toContain('DIČ {{entity_dic}}');

    $sla = (string) LegalDocuments::text('sla', '2026-10');
    expect($sla)->toContain('| Standard | 99,9 % | 99,9 % | 5 % měsíční ceny za každou započatou hodinu nedostupnosti nad měsíční limit (nejvýše 50 %) |')
        ->toContain('| Business | 99,95 % | 99,95 % | 10 % měsíční ceny za každou započatou hodinu nedostupnosti nad měsíční limit (nejvýše 100 %) |')
        ->toContain('pondělí až pátek 8:00–17:00 pražského času')->toContain('4 hodiny v kalendářním měsíci')
        ->not->toContain('Třída Standard má cílovou dostupnost bez smluvní kompenzace');
    // the draft table equals what the code pays (config sla.credit_policies, L-05)
    expect(config('onhost.sla.credit_policies.standard'))->toBe(['bands' => [['per_started_hour_percent' => 5]], 'cap_percent' => 50])
        ->and(config('onhost.sla.credit_policies.business'))->toBe(['bands' => [['per_started_hour_percent' => 10]], 'cap_percent' => 100])
        ->and(config('onhost.sla.maintenance.working_hours'))->toBe(['timezone' => 'Europe/Prague', 'days' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '17:00']);
});

it('shows the real company and telephone in the public footer and nowhere the prototype number', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('+420 736 741 902 · reklamace@onhost.cz')
        ->toContain('© '.now('Europe/Prague')->year.' Adrian Staněk · IČO 08094616 · Molákova 2145/5, 628 00 Brno · fyzická osoba podnikající na základě živnostenského oprávnění')
        ->toContain('natural person trading under a trade licence')
        ->not->toContain('210 000 111')->not->toContain('Onhost s.r.o.')->not->toContain('Telefon 24/7')->not->toContain('Phone, 24/7');
});

it('replaces the prototype number in the panel, the console and the widgets gallery too', function () {
    $renderer = app(SurfaceRenderer::class);
    foreach (['panel', 'admin', 'widgets', 'mobile'] as $surface) {
        $html = $renderer->render($surface, [], false);
        expect($html)->not->toContain('210 000 111', $surface);
    }
    $panel = $renderer->render('panel', [], false);
    expect($panel)->toContain("'+420 736 741 902'")->not->toContain('do dvou zazvonění')->not->toContain('handled immediately');
    // the prototype files themselves stay byte-identical
    expect((string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html')))->toContain('+420 210 000 111')
        ->and((string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html')))->toContain('Onhost s.r.o. · Praha');
});

it('shows a dash instead of a made-up number when no telephone is configured, and escapes the values it prints', function () {
    config(['onhost.legal_entity.phone' => '', 'onhost.legal_entity.email' => "rekla'mace@onhost.cz"]);
    $html = LegalIdentitySeam::apply("tbPhone: '+420 210 000 111', x: '+420 210 000 111'");

    expect($html)->toContain("tbPhone: '— · rekla\\u0027mace@onhost.cz',")->toContain("x: '—'")->not->toContain('210 000 111');
});

it('serves no invented customer quote, rating or numbered reference on the public site', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach (['Tomáš Vrba', 'Fibersy', 'CraftLine', 'Studio Nuvo', 'Skladomat', '4,9 / 5 · 380', '4.9 / 5 · 380', 'obnova databáze při testu', 'kredit vrácený bez žádosti', 'Přešli jsme z VPS u velkého providera'] as $invented) {
        expect($html)->not->toContain($invented, $invented);
    }
    expect($html)->toContain('<sc-if value="{{ hasTestimonials }}"')->toContain('<sc-if value="{{ svc.hasReviews }}"')
        ->and(i11Data($this)['data']['cs']['references'])->toBe([])->and(i11Data($this)['js'])->toContain('references: function (cs)');

    // the prototype is untouched and demo mode keeps its copy
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect($prototype)->toContain('Tomáš Vrba')->and(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('Tomáš Vrba');
});

it('serves a reference only when a real one is configured', function () {
    config(['onhost.content.references.cs' => [['quote' => 'Migrace proběhla bez výpadku.', 'who' => 'Jana Skutečná', 'role' => 'Studio X'], ['quote' => '', 'who' => 'Nikdo', 'role' => '']]]);
    Cache::forget('surfaces:onhost-data.js');

    expect(i11Data($this)['data']['cs']['references'])->toBe([['quote' => 'Migrace proběhla bez výpadku.', 'who' => 'Jana Skutečná', 'role' => 'Studio X']])
        ->and(i11Data($this)['data']['en']['references'])->toBe([]);
});

it('does not serve the invented migration case study of the blog', function () {
    $this->seed([ContentSeeder::class]);
    $content = app(ContentService::class);

    expect(collect($content->posts('cs'))->pluck('slug')->all())->not->toContain('migrace-z-cloudu')->and(collect($content->posts('cs'))->count())->toBeGreaterThan(3);
    expect(fn () => $content->post('migrace-z-cloudu', 'cs'))->toThrow(DomainError::class);
});
