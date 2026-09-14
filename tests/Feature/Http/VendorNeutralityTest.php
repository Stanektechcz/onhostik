<?php

declare(strict_types=1);

use App\Http\Presenters\Presenters;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Orders\Models\ConsentDocument;

/** The registrar behind a domain (WEDOS, Subreg) is never named outside the console: customer surfaces, shared scripts, catalogue, consents, API. */
it('never shows a registrar name on the public site, the panel, the shared scripts or the customer API', function () {
    $this->seed([CatalogSeeder::class, LegalEntitySeeder::class]);
    [$customer, $org] = $this->customerWithOrganization();

    foreach (['/' => null, '/m' => null, '/widgets' => null, '/panel' => $customer, '/partner' => $customer] as $path => $user) {
        $response = $user === null ? $this->get($path) : $this->actingAs($user)->get($path);
        if ($response->isRedirect()) {
            continue; // surfaces the user has no access to
        }
        $html = $response->assertOk()->getContent();
        expect(preg_match('/wedos|subreg/i', $html, $m, PREG_OFFSET_CAPTURE))->toBe(0, $path.': '.substr($html, max(0, ($m[0][1] ?? 0) - 80), 200));
    }
    $panel = $this->actingAs($customer)->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain('Migrace od jiného poskytovatele krok za krokem')->toContain("['migrac', 'migrat', 'forpsi',");
    $public = $this->get('/')->assertOk()->getContent();
    expect($public)->toContain("migFrom: 'other', migSize: 'm'")->toContain('Přišli od jiného poskytovatele na jeden VPS.');

    $js = $this->get('/surfaces/onhost-svc-web.js')->assertOk()->getContent();
    expect($js)->not->toMatch('/wedos/i')->toContain("'Hetzner', 'Forpsi']");

    $tlds = $this->getJson('/v1/catalog/tlds')->assertOk()->json();
    expect(json_encode($tlds))->not->toMatch('/wedos|subreg/i');
    expect(collect($tlds['data'])->firstWhere('tld', 'cz')['registrar_terms_url'] ?? config('onhost.domains.terms_url'))->toBe(config('onhost.domains.terms_url'));
    $doc = ConsentDocument::query()->where('key', 'registrar_terms')->first();
    expect($doc)->not->toBeNull()->and(json_encode($doc->title))->not->toMatch('/wedos/i')->and($doc->url)->toBe(config('onhost.domains.terms_url'));

    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'registrar_provider' => 'subreg', 'kind' => 'registrant', 'name' => 'Jana', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'G-000001']);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'neutral.cz', 'fqdn_unicode' => 'neutral.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'subreg', 'expires_at' => now()->addYear(), 'dns_provider' => 'external', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id, 'meta' => ['registrar_selection' => ['provider' => 'subreg', 'reason' => 'cheapest']]]);
    RegistrarOperation::query()->create(['domain_id' => $domain->id, 'organization_id' => $org->id, 'command' => 'domain-create', 'cltrid' => 'onhost:v4:domain-create:t1', 'registrar_provider' => 'subreg', 'state' => 'FAILED', 'vendor_code' => '502.1001', 'vendor_message' => 'Subreg Make_Order failed: registry timeout', 'normalized_error' => 'REGISTRY_TEMPORARILY_UNAVAILABLE', 'sent_at' => now()]);
    $this->actingAs($customer, 'sanctum');
    $list = $this->getJson('/v1/domains')->assertOk()->getContent();
    $show = $this->getJson('/v1/domains/'.$domain->id)->assertOk()->getContent();
    expect($list.$show)->not->toMatch('/wedos|subreg|wapi/i');
    expect(Presenters::customerError('Subreg order failed at WEDOS via WAPI'))->toBe('server order failed at server via server');
});
