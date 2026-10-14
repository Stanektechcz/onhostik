<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\LegalEntitySeeder;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Domain\Orders\Models\ConsentDocument;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Platform\Audit\AuditEvent;

/*
 * TASK-0142: the 2026-10 customer documents are prepared as DRAFTS through consent_documents (owner decision I-R4/4A: launch on
 * the 2026-09 texts until an attorney confirms these). A draft is never quoted, required at checkout or served; publishing is the
 * owner's step (`onhost:legal:publish`), which keeps the notice the documents promise and freezes the text's hash.
 */
const LEGAL_DRAFT_KEYS = ['aup', 'auto_renew', 'complaints', 'dpa', 'privacy', 'registrar_terms', 'sla', 'terms', 'withdrawal_waiver'];

function legalReadyEntity(): void
{
    config(['onhost.legal_entity.phone' => '+420 000 000 000', 'onhost.legal_entity.email' => 'reklamace@onhost.cz']);
    LegalEntity::query()->where('key', 'onhost-cz')->update(['ico' => '12345678']);
}

it('seeds version 2026-10 as drafts that nothing offers, quotes or serves', function () {
    $this->seed([LegalEntitySeeder::class]);

    $drafts = ConsentDocument::query()->where('version', '2026-10')->orderBy('key')->get();
    expect($drafts->pluck('key')->all())->toBe(LEGAL_DRAFT_KEYS)
        ->and($drafts->every(fn (ConsentDocument $d) => $d->isDraft() && $d->effective_from->year === 2099))->toBeTrue();

    foreach (['terms', 'privacy', 'withdrawal_waiver', 'sla', 'dpa', 'auto_renew', 'registrar_terms'] as $key) {
        expect(ConsentDocument::current($key)?->version)->toBe('2026-09', $key);
    }
    expect(ConsentDocument::current('complaints'))->toBeNull()->and(ConsentDocument::current('aup'))->toBeNull()
        ->and(array_unique(array_values(ConsentDocument::currentVersions())))->toBe(['2026-09'])
        ->and(app(QuoteService::class)->currentTermsVersions())->not->toContain('2026-10');

    $this->get('/dokumenty/reklamacni-rad')->assertNotFound();
    $this->get('/dokumenty/zasady-uzivani')->assertNotFound();
    $this->get('/dokumenty/vop/2026-10')->assertNotFound();
    $this->get('/dokumenty/vop/2026-09')->assertOk()->assertSee('Verze 2026-09');
    expect($this->get('/dokumenty')->getContent())->not->toContain('2026-10')->not->toContain('reklamacni-rad');
});

it('keeps a text for every draft, hashed line-ending blind, with known placeholders only', function () {
    $this->seed([LegalEntitySeeder::class]);
    foreach (LEGAL_DRAFT_KEYS as $key) {
        $text = LegalDocuments::text($key, '2026-10');
        expect($text)->not->toBeNull($key)
            ->and(ConsentDocument::query()->where('key', $key)->where('version', '2026-10')->value('hash'))->toBe(hash('sha256', (string) $text))
            ->and(str_contains((string) $text, "\r"))->toBeFalse();
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', (string) $text, $found);
        expect(array_diff($found[1], LegalDocuments::PLACEHOLDERS))->toBe([], $key);
        expect(preg_match('/wedos|subreg|aapanel|ispconfig|proxmox|pterodactyl/i', (string) $text))->toBe(0, $key); // vendor neutrality
    }
    // a version folder answers only for its own keys; the 2026-09 texts stay where they were
    expect(LegalDocuments::path('registry_terms_cz', '2026-10'))->toBeNull()
        ->and(LegalDocuments::path('terms', '2026-09'))->toBe(resource_path('legal/terms.md'))
        ->and(LegalDocuments::path('terms', '../2026-10'))->toBeNull();
});

it('freezes the 2026-09 texts customers have accepted', function () {
    // L-29: these texts were edited in place before (LEGAL_REVIEW_withdrawal.md, question 6). From TASK-0142 on, a change is a new
    // version in resources/legal/<version>/ — a hash below changes only together with an attorney-approved version, never alone.
    $accepted = [
        'terms' => '15e967e9d62ca8eec8552f9e6182adfcd16a8fd64587591080c3b3240dcc16e9',
        'privacy' => '6eefeff892ae6b18060081c9c809d8bb64bbd0e30eb38bc255853defd0e2f979',
        'dpa' => 'e5f8ccf48fb28dd8bb0a990cc0af554346cc83d14ed21f235853a07c18adcbb0',
        'sla' => '2fb52bf8a1e11bf2c9c397689e4ab49901e0ef88db7d9d352d16fb3e19e4921a',
        'withdrawal_waiver' => '193dadd067b82c699bfc9acb2e394a5abb1de26ca6d6e80e9e08bc569c0ed199',
        'auto_renew' => 'ebd9c4f5d9b41f8e348025cd0a37a353a98b465af187bdcdd7b788db3bf1186f',
        'registrar_terms' => 'c2fa8c71438ae69ae1c112404a0cda38b4206aa1e2eca66387e452a4663b8661',
    ];
    foreach ($accepted as $key => $hash) {
        expect(LegalDocuments::hash($key, '2026-09'))->toBe($hash, "resources/legal/{$key}.md (2026-09) changed — make a new version instead");
    }
});

it('writes the owner decisions into the drafts', function () {
    $terms = (string) LegalDocuments::text('terms', '2026-10');
    $withdrawal = (string) LegalDocuments::text('withdrawal_waiver', '2026-10');

    expect($terms)->toContain('Poskytovatel není plátcem daně z přidané hodnoty')       // I-R5: not a VAT payer
        ->toContain('Od dobití kreditu nelze odstoupit')                                   // H-R5
        ->toContain('nevyčerpaný kredit smazáním účtu propadá')                           // H-R5 + acknowledgement
        ->toContain('výslovným potvrzením zákazníka, že kredit propadne')
        ->toContain('Kredit se nevyplácí')                                                 // G-R4 with the statutory exception
        ->toContain('stejným platebním prostředkem')
        ->toContain('1 bod = 1 Kč')->toContain('24 měsíců')->toContain('20 %')            // G-R2 loyalty
        ->toContain('Penpot')->toContain('29 Kč')                                         // H-R7 / I-R7
        ->toContain('instalační obraz')                                                    // G-R5 custom ISO
        ->toContain('schválení vlastníkem nebo správcem organizace')                       // H-R1 / H-R1a
        ->not->toContain('platformy pro řešení sporů online');                             // the EU ODR platform closed in 2025
    expect($withdrawal)->toContain('14 dnů')->toContain('stejný platební prostředek')->toContain('registrace domény')
        ->toContain('Dobití kreditu')->toContain('souhlas je dobrovolný');
    expect((string) LegalDocuments::text('complaints', '2026-10'))->toContain('30 dnů')->toContain('adr.coi.cz');
    expect((string) LegalDocuments::text('aup', '2026-10'))->toContain('2022/2065')->toContain('Oznámení protiprávního obsahu');
});

it('publishes a version only on the owner\'s word, after the notice, and never re-drafts it on a later seed', function () {
    $this->seed([LegalEntitySeeder::class]);
    $soon = now('Europe/Prague')->addDays(5)->toDateString();

    // I-R11: the shipped defaults are the operator's real data, so an unconfigured installation is simulated: placeholder IČO, no telephone, no-reply mailbox
    config(['onhost.legal_entity.phone' => '', 'onhost.legal_entity.email' => '', 'mail.from.address' => 'noreply@example.test']);
    LegalEntity::query()->where('key', 'onhost-cz')->update(['ico' => '00000000']);
    // dry run names every blocker: placeholder entity, no telephone, no attorney named, too little notice
    $this->artisan('onhost:legal:publish', ['version' => '2026-10', '--effective-from' => $soon])
        ->expectsOutputToContain('placeholder identifiers')->expectsOutputToContain('ONHOST_LEGAL_PHONE')->expectsOutputToContain('ONHOST_LEGAL_EMAIL')
        ->expectsOutputToContain('--approved-by')->expectsOutputToContain('30 days ahead')->assertExitCode(1);
    $this->artisan('onhost:legal:publish', ['version' => '2026-10', '--effective-from' => $soon, '--approved-by' => 'Mgr. Test', '--apply' => true, '--yes' => true])->assertExitCode(1);
    expect(ConsentDocument::current('terms')->version)->toBe('2026-09');

    legalReadyEntity();
    $day = now('Europe/Prague')->addDays(31)->toDateString();
    $this->artisan('onhost:legal:publish', ['version' => '2026-10', '--effective-from' => $day, '--approved-by' => 'Mgr. Test, advokát', '--apply' => true, '--yes' => true])->assertExitCode(0);

    $terms = ConsentDocument::query()->where('key', 'terms')->where('version', '2026-10')->first();
    $from = CarbonImmutable::createFromFormat('!Y-m-d', $day, 'Europe/Prague')->utc();
    expect($terms->state)->toBe('active')->and($terms->published_by)->toBe('Mgr. Test, advokát')
        ->and($terms->effective_from->equalTo($from))->toBeTrue()
        ->and($terms->hash)->toBe(LegalDocuments::hash('terms', '2026-10'))
        ->and(ConsentDocument::query()->where('key', 'terms')->where('version', '2026-09')->first()->effective_to->equalTo($from))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'legal.document.published')->count())->toBe(count(LEGAL_DRAFT_KEYS));

    // until the day comes the 2026-09 texts stay in force; a later seed run leaves the published version alone
    expect(ConsentDocument::current('terms')->version)->toBe('2026-09');
    $this->seed([LegalEntitySeeder::class]);
    expect(ConsentDocument::query()->where('key', 'terms')->where('version', '2026-10')->value('state'))->toBe('active');

    $this->travelTo($from->addHour());
    expect(ConsentDocument::current('terms')->version)->toBe('2026-10')
        ->and(app(QuoteService::class)->currentTermsVersions()['terms'])->toBe('2026-10');
    $page = $this->get('/dokumenty/vop')->assertOk()->getContent();
    expect($page)->toContain('Verze 2026-10')->toContain('/dokumenty/vop/2026-09')->toContain('+420 000 000 000')->not->toContain('{{');
    $this->get('/dokumenty/reklamacni-rad')->assertOk()->assertSee('Reklamační řád');
    $this->get('/dokumenty/zasady-uzivani')->assertOk();
    $this->get('/dokumenty/vop/2026-09')->assertOk()->assertSee('Toto je dřívější verze');
    expect($this->get('/dokumenty')->getContent())->toContain('reklamacni-rad')->toContain('/dokumenty/vop/2026-09');
});
