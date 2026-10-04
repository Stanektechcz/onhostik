<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DomainController;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditEvent;

/*
 * The holder's contact details (e-mail, phone, address) can be changed from the panel (TASK-0056): HIGH, so a fresh step-up; the
 * registrar is asked first (WEDOS/Subreg contact update, faked here) and a refusal leaves the platform's copy as it was; the holder
 * himself (name, company, IČO) cannot be changed this way — that is a transfer of the domain.
 *
 * The route `POST /v1/domains/{domain}/holder` belongs in routes/api.php beside the other domain writes; that file was held by another
 * task while this one was written, so the test registers the same route with the same middleware.
 */

beforeEach(function () {
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    Route::middleware(['api', 'auth:sanctum', 'token.scope', 'throttle:api', 'idempotency'])->prefix('v1')->post('domains/{domain}/holder', [DomainController::class, 'holder']);
    Http::preventStrayRequests();
});

/** @return array{0:User,1:Organization,2:Domain,3:RegistrarContact} */
function holderDomain(array $account, string $fqdn = 'drzitel.cz', array $domain = [], array $contact = []): array
{
    [$user, $org] = $account;
    $c = RegistrarContact::query()->create($contact + ['organization_id' => $org->id, 'registrar_provider' => 'wedos', 'kind' => 'registrant', 'name' => 'Jana Nováková', 'organization_name' => null, 'email' => 'jana@example.cz', 'phone' => '+420.777000111', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'cz:ONH-HOLDER1']);
    $d = Domain::query()->create($domain + ['organization_id' => $org->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => $fqdn, 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'wedos', 'expires_at' => now()->addYear(), 'registrant_contact_id' => $c->id, 'admin_contact_id' => $c->id]);

    return [$user, $org, $d, $c];
}

/** @param list<array{string,array}> $sent */
function holderWapiFake(array &$sent, array $override = []): void
{
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$sent, $override) {
        $payload = json_decode((string) $request['request'], true)['request'] ?? [];
        $sent[] = [(string) ($payload['command'] ?? ''), (array) ($payload['data'] ?? [])];

        return Http::response(['response' => $override + ['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'] ?? null, 'svTRID' => 'sv-1', 'command' => $payload['command'] ?? null, 'data' => []]]);
    }]);
}

function postHolder(object $test, $user, $org, Domain $domain, array $body, string $key = 'holder-1'): TestResponse
{
    $test->actingAs($user, 'sanctum');

    return $test->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => $key])->postJson('/v1/domains/'.$domain->id.'/holder', $body);
}

it('needs a fresh step-up and sends nothing to the registrar without it', function () {
    [$user, $org, $domain, $contact] = holderDomain($this->customerWithOrganization());
    $sent = [];
    holderWapiFake($sent);

    postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz'])->assertStatus(403)->assertJsonPath('error', 'step_up_required');

    expect($sent)->toBe([])->and($contact->fresh()->email)->toBe('jana@example.cz');
});

it('changes e-mail, phone and address at the registrar first and then in the platform, and audits the fields (not their values)', function () {
    [$user, $org, $domain, $contact] = holderDomain($this->customerWithOrganization());
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    $response = postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz', 'phone' => '+420.777123456', 'street' => 'Nová 5', 'city' => 'Brno', 'postal_code' => '60200'])->assertOk();

    $response->assertJsonPath('changed', ['email', 'phone', 'street', 'city', 'postal_code'])->assertJsonPath('domains_affected', 1)->assertJsonPath('registrar_updated', true);
    expect($sent)->toHaveCount(1)->and($sent[0][0])->toBe('contact-update')
        ->and($sent[0][1])->toMatchArray(['tld' => 'cz', 'cname' => 'ONH-HOLDER1', 'email' => 'nova@example.cz', 'phone' => '+420.777123456', 'addr_street' => 'Nová 5', 'addr_city' => 'Brno', 'addr_zip' => '60200', 'addr_country' => 'CZ']);
    $fresh = $contact->fresh();
    expect($fresh->email)->toBe('nova@example.cz')->and($fresh->city)->toBe('Brno')->and($fresh->postal_code)->toBe('60200')->and($fresh->name)->toBe('Jana Nováková');
    expect(RegistrarOperation::query()->where('domain_id', $domain->id)->where('command', 'contact-update')->where('state', RegistrarOperation::SUCCEEDED)->count())->toBe(1);
    $audit = AuditEvent::query()->where('action', 'domain.holder_update')->latest('id')->first();
    expect($audit)->not->toBeNull()->and(json_encode($audit->getAttributes()))->toContain('email')->not->toContain('nova@example.cz')->not->toContain('Nová 5');
});

it('refuses a change of the holder himself and sends nothing to the registrar', function () {
    [$user, $org, $domain, $contact] = holderDomain($this->customerWithOrganization());
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    postHolder($this, $user, $org, $domain, ['name' => 'Petr Nový', 'email' => 'petr@example.cz'])->assertStatus(422)->assertJsonPath('error', 'domain_holder_identity_change');
    postHolder($this, $user, $org, $domain, ['ico' => '12345678'], 'holder-2')->assertStatus(422)->assertJsonPath('error', 'domain_holder_identity_change')->assertJsonPath('field', 'ico');

    expect($sent)->toBe([])->and($contact->fresh()->email)->toBe('jana@example.cz');
    // the same name is not a change: a form that sends everything back works
    postHolder($this, $user, $org, $domain, ['name' => 'Jana Nováková', 'email' => 'nova@example.cz'], 'holder-3')->assertOk()->assertJsonPath('changed', ['email']);
});

it('leaves the platform copy as it was when the registrar refuses', function () {
    [$user, $org, $domain, $contact] = holderDomain($this->customerWithOrganization());
    $sent = [];
    holderWapiFake($sent, ['code' => 2201, 'result' => 'Contact is locked']);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    $response = postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz']);

    expect($response->status())->toBeGreaterThanOrEqual(400)->and($sent)->toHaveCount(1);
    expect($contact->fresh()->email)->toBe('jana@example.cz');
    expect($response->json('error'))->not->toBeNull();
});

it('says so when nothing differs, and validates the e-mail and the country', function () {
    [$user, $org, $domain] = holderDomain($this->customerWithOrganization());
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    postHolder($this, $user, $org, $domain, ['email' => 'jana@example.cz', 'country' => 'cz'])->assertStatus(422)->assertJsonPath('error', 'domain_holder_unchanged');
    postHolder($this, $user, $org, $domain, ['email' => 'neni-email'], 'holder-4')->assertStatus(422);
    postHolder($this, $user, $org, $domain, ['country' => 'CZE'], 'holder-5')->assertStatus(422);
    expect($sent)->toBe([]);
});

it('tells how many domains share the contact that was changed', function () {
    [$user, $org, $domain, $contact] = holderDomain($this->customerWithOrganization());
    Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'druhy.cz', 'fqdn_unicode' => 'druhy.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'wedos', 'expires_at' => now()->addYear(), 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    postHolder($this, $user, $org, $domain, ['phone' => '+420.777999888'])->assertOk()->assertJsonPath('domains_affected', 2);
});

it('does not touch a domain of another organization or one managed at a connected registrar account', function () {
    [$user, $org, $domain] = holderDomain($this->customerWithOrganization());
    [$stranger, $strangerOrg] = $this->customerWithOrganization();
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($stranger, 'totp', null, '127.0.0.1');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    expect(postHolder($this, $stranger, $strangerOrg, $domain, ['email' => 'unos@example.cz'])->status())->toBeIn([403, 404]);
    $domain->forceFill(['meta' => ['source' => 'connection', 'connection_id' => 'rcn_1']])->save();
    postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz'], 'holder-7')->assertStatus(409)->assertJsonPath('error', 'domain_external');

    expect($sent)->toBe([]);
});

it('answers a repeated request with the first answer and a reused key for another request with a refusal', function () {
    [$user, $org, $domain] = holderDomain($this->customerWithOrganization());
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz'], 'holder-same')->assertOk();
    postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz'], 'holder-same')->assertOk();
    expect($sent)->toHaveCount(1);
    expect(postHolder($this, $user, $org, $domain, ['email' => 'jiny@example.cz'], 'holder-same')->status())->toBeIn([409, 422]);
    expect($sent)->toHaveCount(1);
    Http::assertSentCount(1);
});

it('refuses a domain that is not active', function () {
    [$user, $org, $domain] = holderDomain($this->customerWithOrganization(), 'pending.cz', ['state' => DomainStateMachine::TRANSFER_IN_PENDING]);
    $sent = [];
    holderWapiFake($sent);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    postHolder($this, $user, $org, $domain, ['email' => 'nova@example.cz'])->assertStatus(409)->assertJsonPath('error', 'domain_holder_not_changeable');
    expect($sent)->toBe([]);
    Http::assertNothingSent();
    unset($sent);
});
