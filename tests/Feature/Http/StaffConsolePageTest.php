<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;

/*
 * The staff-side server console (audit §5p-2): a page next to the console with the log tail, a command line, the power
 * buttons and the live console token — for staff who may manage services, for nobody else; the console's Konzole
 * action opens it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('renders the staff console for a game server and refuses everyone without the staff permission', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'hrac@firma.cz'], ['name' => 'Herní klub s.r.o.']);
    $service = featureGameService($org);
    $html = $this->actingAs($this->staff('game_admin'))->get("/sprava/konzole/{$service->id}")->assertOk()->getContent();
    expect($html)->toContain('Konzole · '.($service->label ?: $service->name))->toContain('Herní klub s.r.o.')->toContain('uzel games01')
        ->toContain('data-power="start"')->toContain('data-power="kill"')->toContain('id="cmd"')->toContain("action: 'command.send'")->toContain("'X-Organization': org")->toContain('/console-token')->toContain('/logs?lines=300');
    $this->actingAs($this->staff('game_admin'))->get('/sprava/konzole/svc_nope')->assertNotFound();
    $this->actingAs($this->staff('support_agent'))->get("/sprava/konzole/{$service->id}")->assertForbidden();
    $this->actingAs($owner)->get("/sprava/konzole/{$service->id}")->assertForbidden(); // customers use their own workbench
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js'));
    expect($js)->toContain("window.open('/sprava/konzole/' + sid, '_blank', 'noopener')");
});
