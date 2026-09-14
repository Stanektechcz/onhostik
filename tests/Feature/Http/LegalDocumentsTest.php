<?php

declare(strict_types=1);

use App\Http\Controllers\Web\LegalDocumentController;
use Database\Seeders\LegalEntitySeeder;
use Onhost\Domain\Orders\Models\ConsentDocument;

/** Every consent document a customer accepts resolves to a readable, versioned page; the registrar terms never name a registrar. */
it('serves every seeded consent document at its URL with version, entity and neutral wording', function () {
    $this->seed([LegalEntitySeeder::class]);
    $index = $this->get('/dokumenty')->assertOk()->getContent();
    foreach (LegalDocumentController::SLUGS as $slug => $key) {
        $document = ConsentDocument::current($key);
        expect($document)->not->toBeNull($key);
        $page = $this->get('/dokumenty/'.$slug)->assertOk()->getContent();
        expect($page)->toContain('Verze '.$document->version)->toContain($document->title['cs'])->toContain('<h2>')->not->toContain('{{entity_')
            ->and($index)->toContain('/dokumenty/'.$slug);
        expect(preg_match('/wedos|subreg/i', $page))->toBe(0, $slug);
    }
    // the URLs stored on the documents themselves resolve (internal ones), so a consent link never dead-ends
    foreach (ConsentDocument::query()->get() as $document) {
        if (str_starts_with((string) $document->url, '/')) {
            $this->get($document->url)->assertStatus(in_array($document->url, ['/sla'], true) ? 301 : 200);
        }
    }
    $this->get('/sla')->assertRedirect('/dokumenty/sla');
    $this->get('/dokumenty/neexistuje')->assertNotFound();
    expect($this->get('/dokumenty/podminky-registrace-domen')->getContent())->toContain('AUTH-ID')->toContain('Držitelem domény je zákazník');
});
