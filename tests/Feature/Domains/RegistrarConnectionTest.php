<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarClient;
use Onhost\Domain\Integrations\DiscordMessage;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretStore;

/*
 * Bring your own WEDOS API: a customer connects their own registrar account (fresh step-up, password into the secret
 * store, never back out), the platform mirrors the account's domains and the zones it hosts, warns before expiries at
 * the customer's registrar, watches the credit, keeps a run history, and the operator sees and can pause it. Customer
 * instances never serve platform work. Disconnecting drops the credentials and the mirrors; reconnecting restores them.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('connects a customer WEDOS account, mirrors domains and zones, warns before expiries, watches the credit and keeps the operator in the loop', function () {
    [$user, $org] = $this->customerWithOrganization();
    $platform = ProviderInstance::query()->create(['key' => 'wedos-main', 'provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com/wapi/json', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'options' => []]);
    $state = [
        'domains' => [
            ['name' => 'eshop.cz', 'status' => 'active', 'expiration' => now()->addDays(10)->toDateString(), 'created' => '2020-01-01'],
            ['name' => 'blog.cz', 'status' => 'active', 'expiration' => now()->addYear()->toDateString(), 'created' => '2021-05-05'],
        ],
        'zones' => ['eshop.cz' => [['ID' => '11', 'name' => '', 'ttl' => 1800, 'rdtype' => 'A', 'rdata' => '203.0.113.5'], ['ID' => '12', 'name' => 'www', 'ttl' => 1800, 'rdtype' => 'CNAME', 'rdata' => 'eshop.cz.'], ['ID' => '13', 'name' => '', 'ttl' => 1800, 'rdtype' => 'MX', 'rdata' => '10 mail.eshop.cz.']]],
        'credit' => '150.00', 'commands' => [], 'logins' => [],
    ];
    connectionWapiFake($state);
    $this->actingAs($user, 'sanctum');

    // storing credentials is HIGH risk: a fresh step-up first
    $this->postJson('/v1/registrar-connections', ['login' => 'ucet@firma.cz', 'password' => 'api-secret-1', 'label' => 'Firemní WEDOS'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => 'Correct-Horse-Battery-9'])->assertOk();
    $created = $this->postJson('/v1/registrar-connections', ['login' => 'ucet@firma.cz', 'password' => 'api-secret-1', 'label' => 'Firemní WEDOS'])->assertCreated();
    $connection = RegistrarConnection::query()->findOrFail($created->json('connection.id'));
    expect($connection->state)->toBe('active')->and(str_contains((string) $created->getContent(), 'api-secret-1'))->toBeFalse()
        ->and(app(SecretStore::class)->read($connection->secretRef()))->toBe(['login' => 'ucet@firma.cz', 'wapi_password' => 'api-secret-1'])
        ->and(array_values(array_unique($state['logins'])))->toBe(['ucet@firma.cz']); // the customer's account, never the platform's

    // the account runs through customer-owned instances that platform lookups never pick
    $instances = ProviderInstance::query()->where('organization_id', $org->id)->orderBy('key')->get();
    expect($instances->pluck('provider')->all())->toBe(['wedos', 'wedos_zone'])->and($instances->pluck('key')->first())->toStartWith('wedos-c-')
        ->and(app(ProviderRegistry::class)->findInstance('wedos', null, 'registrar')?->id)->toBe($platform->id)
        ->and(collect(app(RegistrarClient::class)->instances())->pluck('organization_id')->filter()->all())->toBe([]);

    // mirrored domains and the hosted zone with its rows
    $show = $this->getJson("/v1/registrar-connections/{$connection->id}")->assertOk()->json('data');
    $domains = collect($show['domains'])->keyBy('fqdn');
    expect($show['domains'])->toHaveCount(2)->and($show['state'])->toBe('active')->and($show['credit']['balance'])->toBe('150.00')
        ->and($domains['eshop.cz']['source'])->toBe('connection')->and($domains['eshop.cz']['dns_provider'])->toBe('connected')->and($domains['eshop.cz']['days_to_expiry'])->toBe(10)
        ->and($domains['blog.cz']['dns_provider'])->toBe('external')->and($domains['blog.cz']['registrar_account'])->toBe('ucet@firma.cz');
    $zone = DnsZone::query()->where('name', 'eshop.cz')->firstOrFail();
    expect($zone->provider)->toBe('wedos_zone')->and($zone->provider_instance_id)->toBe($connection->dns_instance_id)->and($zone->organization_id)->toBe($org->id)
        ->and($zone->records()->count())->toBe(3)->and($zone->records()->where('type', 'MX')->value('protected'))->toBeTruthy()->and($zone->records()->pluck('managed_by')->unique()->all())->toBe(['customer']);
    expect(Domain::query()->where('fqdn_ascii', 'eshop.cz')->value('dns_zone_id'))->toBe($zone->id);

    // mirrored domains are managed at the customer's registrar: the platform refuses to renew, re-delegate or auto-renew them
    $eshopId = Domain::query()->where('fqdn_ascii', 'eshop.cz')->value('id');
    $this->postJson("/v1/domains/{$eshopId}/renew", ['years' => 1])->assertStatus(409)->assertJsonPath('error', 'domain_external');
    $this->postJson("/v1/domains/{$eshopId}/auto-renew", ['enabled' => true])->assertStatus(409)->assertJsonPath('error', 'domain_external');
    $this->postJson("/v1/domains/{$eshopId}/nameservers", ['nameservers' => ['ns1.example.net', 'ns2.example.net']])->assertStatus(409)->assertJsonPath('error', 'domain_external');
    $this->postJson("/v1/domains/{$eshopId}/use-onhost-dns")->assertStatus(409)->assertJsonPath('error', 'domain_external');
    // the calendar shows the expiry at the registrar, chat tools get their own wording
    $expiry = collect($this->getJson('/v1/calendar?days=60')->assertOk()->json('data'))->firstWhere('kind', 'domain_expiry');
    expect($expiry['title'])->toBe('Expirace domény eshop.cz')->and($expiry['description'])->toContain('připojeného účtu');
    expect(DiscordMessage::describe('registrar.connection.credit_low', ['label' => 'Firemní WEDOS', 'balance' => '150.00', 'currency' => 'CZK'])[0])->toContain('Registrátor')
        ->and(DiscordMessage::describe('domain.external_expiry_notice', ['fqdn' => 'eshop.cz', 'days' => 14, 'registrar' => 'WEDOS', 'account' => 'u***@firma.cz'])[0])->toBe('⏰ Doména eshop.cz expiruje za 14 dní');

    // notifications: linked, the 14-day expiry notice at the customer's registrar (with mail), the credit under the 200 CZK default
    app(OutboxPublisher::class)->relayPending();
    $titles = Notification::query()->where('organization_id', $org->id)->where('audience', 'customer')->pluck('title')->all();
    expect($titles)->toContain('Účet WEDOS připojen')->toContain('Doména eshop.cz expiruje za 14 dní')->toContain('Kredit u registrátora klesl na 150.00 CZK');
    $mail = MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'domain-external-expiry')->firstOrFail();
    expect($mail->vars['domena'])->toBe('eshop.cz')->and($mail->vars['dni'])->toBe('14')->and($mail->vars['registrator'])->toBe('WEDOS');
    expect(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'registrar-credit-low')->exists())->toBeTrue();

    // a second sync: nothing repeats, a changed expiry counts as an update, a domain gone from the account is flagged
    $state['domains'] = [['name' => 'eshop.cz', 'status' => 'active', 'expiration' => now()->addYear()->toDateString(), 'created' => '2020-01-01']];
    $summary = $this->postJson("/v1/registrar-connections/{$connection->id}/sync")->assertOk()->json('summary');
    expect($summary)->toMatchArray(['imported' => 0, 'updated' => 1, 'missing' => 1, 'notices' => 0]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Doména blog.cz už není v připojeném účtu')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Doména eshop.cz expiruje%')->count())->toBe(1)
        ->and(Domain::query()->where('fqdn_ascii', 'blog.cz')->first()->meta['missing_since'])->not->toBeNull();

    // history: runs on the row, the audit trail behind it; the list endpoint
    $history = $this->getJson("/v1/registrar-connections/{$connection->id}/history")->assertOk()->json('data');
    expect($history['runs'][0]['kind'])->toBe('sync')->and($history['runs'][0]['ok'])->toBeTrue()
        ->and(collect($history['audit'])->pluck('action')->all())->toContain('registrar.connection.connect')->toContain('registrar.connection.sync');
    $this->getJson('/v1/registrar-connections')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.domains', 2)->assertJsonPath('data.0.login', 'ucet@firma.cz');
    $this->patchJson("/v1/registrar-connections/{$connection->id}", ['credit_threshold_minor' => 5000, 'notices' => false])->assertOk()->assertJsonPath('connection.settings.credit_threshold_minor', 5000)->assertJsonPath('connection.settings.notices', false);

    // the operator sees every connection and can pause one; the customer's sync is refused while it is paused
    $this->actingAs($this->staff('domain_dns_admin'), 'sanctum');
    $this->getJson('/v1/staff/registrar-connections')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.organization.name', $org->name)->assertJsonPath('data.0.state', 'active');
    $this->getJson("/v1/staff/customers/{$org->id}")->assertOk()->assertJsonCount(1, 'data.registrar_connections')->assertJsonPath('data.registrar_connections.0.login', 'ucet@firma.cz');
    $this->postJson("/v1/staff/registrar-connections/{$connection->id}/disable", ['reason' => 'abuse report'])->assertOk()->assertJsonPath('connection.state', 'disabled');
    expect(ProviderInstance::query()->where('organization_id', $org->id)->pluck('state')->unique()->all())->toBe(['disabled']);
    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/registrar-connections/{$connection->id}/sync")->assertStatus(409)->assertJsonPath('error', 'registrar_connection_disabled'); // every sync call is its own command (no replay of the earlier one)
    $this->actingAs($this->staff('domain_dns_admin'), 'sanctum');
    $this->postJson("/v1/staff/registrar-connections/{$connection->id}/enable")->assertOk()->assertJsonPath('connection.state', 'active');

    // disconnecting drops the credentials, the customer instances and the mirrors — the registrar account itself is untouched
    $this->actingAs($user, 'sanctum');
    $commandsBefore = count($state['commands']);
    $this->deleteJson("/v1/registrar-connections/{$connection->id}")->assertOk()->assertJsonPath('disconnected', true);
    expect(count($state['commands']))->toBe($commandsBefore)
        ->and(app(SecretStore::class)->exists($connection->secretRef()))->toBeFalse()
        ->and(ProviderInstance::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(Domain::query()->where('organization_id', $org->id)->count())->toBe(0)->and(Domain::withTrashed()->where('organization_id', $org->id)->count())->toBe(2)
        ->and(DnsZone::query()->where('name', 'eshop.cz')->exists())->toBeFalse()
        ->and(RegistrarConnection::query()->findOrFail($connection->id)->state)->toBe('disabled');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Účet Firemní WEDOS odpojen')->exists())->toBeTrue();

    // reconnecting the same account restores the mirrors instead of colliding with them
    $again = $this->postJson('/v1/registrar-connections', ['login' => 'ucet@firma.cz', 'password' => 'api-secret-2'])->assertCreated()->json('connection');
    expect($again['id'])->not->toBe($connection->id)->and($again['label'])->toBe('WEDOS · ucet@firma.cz')
        ->and(Domain::query()->where('organization_id', $org->id)->pluck('fqdn_ascii')->all())->toBe(['eshop.cz'])
        ->and(DnsZone::query()->where('name', 'eshop.cz')->value('provider_instance_id'))->toBe(RegistrarConnection::query()->findOrFail($again['id'])->dns_instance_id);
    expect(OutboxMessage::query()->where('name', 'domain.imported')->where('organization_id', $org->id)->count())->toBe(2); // the first connect only; the restore imports nothing new
});

it('refuses wrong credentials without leaving anything behind', function () {
    [$user, $org] = $this->customerWithOrganization();
    Http::fake(['api.wedos.com/wapi/json' => Http::response(['response' => ['code' => 2050, 'result' => 'Authentication failed', 'timestamp' => time(), 'clTRID' => 'x', 'svTRID' => 'y', 'command' => 'domains-list', 'data' => []]])]);
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => 'Correct-Horse-Battery-9'])->assertOk();
    $this->postJson('/v1/registrar-connections', ['login' => 'ucet@firma.cz', 'password' => 'wrong'])->assertStatus(422)->assertJsonPath('error', 'registrar_connection_failed');
    expect(RegistrarConnection::query()->count())->toBe(0)->and(ProviderInstance::query()->where('organization_id', $org->id)->count())->toBe(0);
    $this->postJson('/v1/registrar-connections', ['login' => 'ucet@firma.cz', 'password' => 'wrong', 'provider' => 'subreg'])->assertStatus(422);
});
