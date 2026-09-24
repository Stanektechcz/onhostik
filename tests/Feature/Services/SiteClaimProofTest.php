<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\PublicDnsCheck;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * A claim has to be proved, or it is a squat.
 *
 * Audit row 89 made a host name belong to one service: whoever holds it, nobody else may claim it. That is the only
 * way a shared node can be safe — and it hands anyone a new weapon, because nothing had to be true for a claim to
 * stick. Order the cheapest hosting for `firma.cz`, never point it anywhere, and the real owner of `firma.cz` can
 * never be hosted here: the cart refuses them with "another service already serves it" and there the story ends.
 *
 * Nothing new has to be asked of an honest customer. The platform already knows three things that prove a name:
 * the domain is registered here by that organization, its DNS zone is here, or the name already answers with the
 * node that serves it (the daily DNS check works that out anyway). A claim that can show none of them for longer
 * than the grace period is an **operator's** problem — never the customer's, who cannot fix somebody else's squat —
 * and a rightful owner turned away is told the way out instead of a dead end.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Public DNS as the world answers it right now. */
function proofDns(array $answers): void
{
    app()->bind(RecordResolver::class, fn () => new class($answers) implements RecordResolver
    {
        public function __construct(private readonly array $answers) {}

        public function records(string $name, string $type): array
        {
            return (array) ($this->answers[mb_strtolower($name).'|'.$type] ?? []);
        }
    });
}

/** A live web hosting on a node with a known address. */
function proofWeb(object $org, string $domain, int $remoteId = 41): Service
{
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);
    $service->forceFill(['hostname' => $domain, 'desired_spec' => array_merge((array) $service->desired_spec, ['domain' => $domain])])->save();
    ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => (string) $remoteId, 'meta' => json_encode(['name' => $domain, 'path' => "/www/wwwroot/{$domain}"])]);
    Website::query()->create(['service_id' => $service->id, 'domain' => $domain, 'aliases' => [], 'executor' => 'aapanel', 'php_version' => '8.3',
        'remote_site_id' => $remoteId, 'remote_node' => 'aapanel-managed01', 'state' => 'active']);

    return $service->refresh();
}

it('tells the operators about a name that was never proved, once the grace period is out', function () {
    proofDns([]); // the domain answers with nothing at all: it was never pointed here
    [, $org] = $this->customerWithOrganization();
    $service = proofWeb($org, 'firma.cz');

    app(PublicDnsCheck::class)->run();
    expect(data_get($service->refresh()->tags, 'name_claim.proof'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.name_unproved')->exists())->toBeFalse(); // the grace period has not run out

    $this->travel(40)->days();
    app(PublicDnsCheck::class)->run();

    $message = OutboxMessage::query()->where('name', 'service.name_unproved')->first();
    expect($message)->not->toBeNull()
        ->and((string) data_get($message?->payload, 'domain'))->toBe('firma.cz')
        ->and(data_get($service->refresh()->tags, 'name_claim.told_on'))->toBe(now()->toDateString());
});

it('says nothing about a name that already answers with its node', function () {
    proofDns(['firma.cz|A' => [['ip' => '203.0.113.10']]]);
    [, $org] = $this->customerWithOrganization();
    $service = proofWeb($org, 'firma.cz');

    $this->travel(40)->days();
    app(PublicDnsCheck::class)->run();

    expect(data_get($service->refresh()->tags, 'name_claim.proof'))->toBe('dns')
        ->and(OutboxMessage::query()->where('name', 'service.name_unproved')->exists())->toBeFalse();
});

it('says nothing when the customer has the domain registered here', function () {
    proofDns([]); // not pointed at us yet — a domain bought here on Friday and set up on Monday
    [, $org] = $this->customerWithOrganization();
    $service = proofWeb($org, 'firma.cz');
    Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'firma.cz', 'fqdn_unicode' => 'firma.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear()]);

    $this->travel(40)->days();
    app(PublicDnsCheck::class)->run();

    expect(data_get($service->refresh()->tags, 'name_claim.proof'))->toBe('domain')
        ->and(OutboxMessage::query()->where('name', 'service.name_unproved')->exists())->toBeFalse();
});

it('says nothing when the DNS of the name is run here', function () {
    proofDns([]);
    [, $org] = $this->customerWithOrganization();
    $service = proofWeb($org, 'firma.cz');
    DnsZone::query()->create(['organization_id' => $org->id, 'name' => 'firma.cz', 'provider' => 'powerdns', 'state' => 'active']);

    $this->travel(40)->days();
    app(PublicDnsCheck::class)->run();

    expect(data_get($service->refresh()->tags, 'name_claim.proof'))->toBe('zone')
        ->and(OutboxMessage::query()->where('name', 'service.name_unproved')->exists())->toBeFalse();
});

it('tells the operators once a day, however often it looks', function () {
    proofDns([]);
    [, $org] = $this->customerWithOrganization();
    proofWeb($org, 'firma.cz');

    app(PublicDnsCheck::class)->run();
    $this->travel(40)->days();
    app(PublicDnsCheck::class)->run();
    app(PublicDnsCheck::class)->run();

    expect(OutboxMessage::query()->where('name', 'service.name_unproved')->count())->toBe(1);
});

it('tells the rightful owner the way out and leaves the dispute for support', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    proofDns([]);
    [, $squatter] = $this->customerWithOrganization();
    proofWeb($squatter, 'firma.cz', 77);          // holds the name and can prove nothing
    [$user, $org] = $this->customerWithOrganization();
    Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'firma.cz', 'fqdn_unicode' => 'firma.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear()]);

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'firma.cz']]], 'currency' => 'CZK'])->assertOk();

    $quote = $this->postJson('/v1/cart/quote');

    expect($quote->status())->toBe(409)
        ->and((string) $quote->json('message'))->toContain('podpoře')      // a way out, not a dead end
        ->and(AuditEvent::query()->where('action', 'service.name_disputed')->exists())->toBeTrue();
});

it('says what to do even when the person asking cannot prove the name', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    proofDns([]);
    [, $holder] = $this->customerWithOrganization();
    proofWeb($holder, 'firma.cz', 77);
    [$user, $org] = $this->customerWithOrganization();

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'firma.cz']]], 'currency' => 'CZK'])->assertOk();

    $quote = $this->postJson('/v1/cart/quote');

    expect($quote->status())->toBe(409)
        ->and((string) $quote->json('message'))->toContain('podpoře')
        ->and(AuditEvent::query()->where('action', 'service.name_disputed')->exists())->toBeFalse(); // nothing to dispute yet
});
