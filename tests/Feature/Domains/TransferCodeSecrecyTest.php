<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\IdempotencyStore;

/*
 * A domain's transfer code is what the step-up, the transfer lock and the critical-domain rule protect. The registrar's
 * answer about a domain was stored as it came — Subreg's `Info_Domain` carries `authid` — and returned by
 * `GET /v1/domains/{id}` to anybody who may READ the domain; a command result that showed the code inline once was kept
 * in clear text in the replay store for a day.
 */

it('never keeps or returns the transfer code a registrar mentions about a domain', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'tajny-kod.cz', 'fqdn_unicode' => 'tajny-kod.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear(), 'registrar_provider' => 'subreg']);
    $raw = ['name' => 'tajny-kod.cz', 'authid' => 'AUTH-ID-f00d-42', 'options' => ['nsset' => 'NSSET-ONHOST', 'auth_info' => 'second-secret'], 'registrant' => 'G-000001', 'exDate' => '2027-09-06'];

    // the way every reconcile pass and every activation writes it
    app(DomainService::class)->applyRegistryInfo($domain, ['status' => 'active', 'expires_at' => '2027-09-06', 'raw' => $raw]);
    $stored = (string) DB::table('domains')->where('id', $domain->id)->value('registry_status');
    expect($stored)->not->toContain('AUTH-ID-f00d-42')->not->toContain('second-secret')->toContain('NSSET-ONHOST')->toContain('G-000001');

    // a row written before the rule existed is read through the same filter, and the migration cleans it for good
    DB::table('domains')->where('id', $domain->id)->update(['registry_status' => json_encode($raw)]);
    $this->actingAs($owner, 'sanctum');
    $body = (string) $this->getJson('/v1/domains/'.$domain->id, ['X-Organization' => $org->id])->assertOk()->getContent();
    expect($body)->not->toContain('AUTH-ID-f00d-42')->not->toContain('second-secret')->toContain('NSSET-ONHOST');
    (require base_path('database/migrations/0001_01_01_000720_scrub_registry_status_of_domains.php'))->up();
    expect((string) DB::table('domains')->where('id', $domain->id)->value('registry_status'))->not->toContain('AUTH-ID-f00d-42')->toContain('NSSET-ONHOST');
});

it('does not keep a secret a command handed out once in the replay store', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $context = new CommandContext('user', $owner->id, $org->id);
    $store = app(IdempotencyStore::class);
    $store->remember('domain.auth_info:abc', $context, ['domain' => 'tajny-kod.cz', 'delivery' => 'inline', 'auth_info' => 'AUTH-ID-f00d-42', 'expires_at' => '2026-09-27T10:00:00+00:00']);
    $store->remember('token.create:abc', $context, ['id' => 'tok_1', 'name' => 'CI', 'plain_token' => 'onh_live_abcdefghijklmnop', 'scopes' => ['services:read']]);

    $rows = DB::table('idempotency_keys')->pluck('result')->implode("\n");
    expect($rows)->not->toContain('AUTH-ID-f00d-42')->not->toContain('onh_live_abcdefghijklmnop')->toContain('tajny-kod.cz')->toContain('services:read');
    // the replay still answers — with the same shape, the secret masked
    expect($store->find('domain.auth_info:abc', $context))->toMatchArray(['domain' => 'tajny-kod.cz', 'delivery' => 'inline', 'auth_info' => '[redacted]']);
});
