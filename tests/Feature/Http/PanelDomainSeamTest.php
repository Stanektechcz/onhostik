<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;

/** Panel domains are API-backed (seam #21): the payload carries the real domain state and TLD prices, the workbench module handles the domain family. */
it('feeds the panel real domain rows, the TLD price list and a domain workbench', function () {
    $this->seed([CatalogSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'registrar_provider' => 'subreg', 'kind' => 'registrant', 'name' => 'Jana', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'G-1']);
    Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'panelova.cz', 'fqdn_unicode' => 'panelova.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'subreg', 'expires_at' => now()->addDays(40), 'dns_provider' => 'external', 'nameservers' => ['ns1.example.net', 'ns2.example.net'], 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);

    $js = $this->actingAs($user)->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    preg_match('/window\.ONHOST_PANEL\s*=\s*(\{.*\});/s', $js, $m);
    $payload = json_decode($m[1] ?? '{}', true);
    $domain = $payload['services']['domain'][0] ?? null;
    expect($domain)->not->toBeNull()->and($domain)->toMatchArray(['type' => 'domain', 'name' => 'panelova.cz', 'fqdn' => 'panelova.cz', 'apiState' => 'ACTIVE', 'auto_renew' => true, 'transfer_lock' => true, 'dns_provider' => 'external', 'nameservers' => ['ns1.example.net', 'ns2.example.net']])
        ->and($domain['days'])->toBe(40);
    expect(json_encode($domain))->not->toMatch('/wedos|subreg/i');
    $cz = collect($payload['tlds'])->firstWhere('tld', 'cz');
    expect($cz)->toMatchArray(['currency' => 'CZK', 'register' => '179.00', 'renew' => '179.00', 'default_period' => 1, 'registrar_terms_url' => config('onhost.domains.terms_url')]);
    expect(array_map('intval', (array) $cz['periods']))->toContain(1)->toContain(2)->toContain(3);
    expect(json_encode($payload['tlds']))->not->toMatch('/wedos|subreg/i');

    $workbench = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-workbench.api.js')->assertOk()->baseResponse->getFile()); // file responses carry no body in tests
    expect($workbench)->toContain("domain: 'domain'")->toContain("domain: [['dns', 'zone'], ['soa', 'nameservers'], ['reg', 'registration'], ['sec', 'dnssec'], ['noc', 'operations']]")->toContain('/auth/step-up')->toContain("'/dnssec/publish'")->toContain("'/use-onhost-dns'");
    $order = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-order.api.js')->assertOk()->baseResponse->getFile());
    expect($order)->toContain("product_key: 'domain'")->toContain('/domains/check')->toContain('registry_terms_');
    $panel = $this->actingAs($user)->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain('x[3] != null ? x[3] : this.money(x[2])')->toContain('window.OnhostPanelOrder.priceLabel(this, md, orderSize)');
});
