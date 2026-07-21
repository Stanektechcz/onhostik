<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * DNS record CRUD (audit F83), one-click templates (F91) and bulk nameserver
 * changes (F90).
 *
 * The editor could previously only ADD records — there was no way to correct
 * a typo or remove a stale record without contacting support.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function ownedDomain(?\App\Models\User $user = null, string $domain = 'mojedomena', string $tld = 'cz'): DomainRegistration
{
    $user ??= customerUser();

    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    return DomainRegistration::create([
        'service_id'  => $service->id,
        'domain'      => $domain,
        'tld'         => $tld,
        'registrar'   => 'wedos',
        'registered_at' => now(),
        'expires_at'  => now()->addYear(),
        'auto_renew'  => true,
    ]);
}

// ── F83: read ─────────────────────────────────────────────────────────────────

it('shows DNS records with edit and delete controls', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->get(route('panel.domains.dns', $domain))
        ->assertOk()
        ->assertSee('Upravit')
        ->assertSee('Smazat');
});

// ── F83: update ───────────────────────────────────────────────────────────────

it('updates an individual DNS record', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->put(route('panel.domains.dns.update', $domain), [
            'row_id' => 1, 'type' => 'A', 'name' => 'www', 'rdata' => '10.0.0.1', 'ttl' => 300,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'dns.record_updated')->exists())->toBeTrue();
});

it('rejects an unknown record type', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->put(route('panel.domains.dns.update', $domain), [
            'row_id' => 1, 'type' => 'EVIL', 'name' => 'x', 'rdata' => 'y',
        ])
        ->assertSessionHasErrors('type');
});

it('requires a row id to update a record', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->put(route('panel.domains.dns.update', $domain), ['type' => 'A', 'name' => '@', 'rdata' => '1.2.3.4'])
        ->assertSessionHasErrors('row_id');
});

// ── F83: delete ───────────────────────────────────────────────────────────────

it('deletes an individual DNS record', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->delete(route('panel.domains.dns.destroy', $domain), ['row_id' => 3])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'dns.record_deleted')->exists())->toBeTrue();
});

// ── Ownership ─────────────────────────────────────────────────────────────────

it('forbids editing DNS on someone else\'s domain', function (): void {
    $domain = ownedDomain(); // belongs to a different customer

    $this->actingAs(customerUser())
        ->put(route('panel.domains.dns.update', $domain), [
            'row_id' => 1, 'type' => 'A', 'name' => '@', 'rdata' => '6.6.6.6',
        ])
        ->assertForbidden();
});

it('lets an admin edit DNS on any domain', function (): void {
    $domain = ownedDomain();

    // Admins may perform any action — including on other customers' domains.
    $this->actingAs(adminUser())
        ->put(route('panel.domains.dns.update', $domain), [
            'row_id' => 1, 'type' => 'A', 'name' => '@', 'rdata' => '10.0.0.9',
        ])
        ->assertRedirect();
});

// ── F91: templates ────────────────────────────────────────────────────────────

it('applies a DNS provider template', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->post(route('panel.domains.dns.template', $domain), ['template' => 'google_workspace'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'dns.template_applied')->exists())->toBeTrue();
});

it('rejects an unknown DNS template', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->post(route('panel.domains.dns.template', $domain), ['template' => 'sketchy_provider'])
        ->assertSessionHasErrors('template');
});

it('offers the templates on the DNS page', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->get(route('panel.domains.dns', $domain))
        ->assertOk()
        ->assertSee('DNS šablony')
        ->assertSee('Google Workspace')
        ->assertSee('Microsoft 365');
});

// ── F90: bulk nameservers ─────────────────────────────────────────────────────

it('changes nameservers across several domains at once', function (): void {
    $user = customerUser();
    $a    = ownedDomain($user, 'prvni');
    $b    = ownedDomain($user, 'druha');

    $this->actingAs($user)
        ->post(route('panel.domains.bulk-nameservers'), [
            'domain_ids'  => [$a->id, $b->id],
            'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'],
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($a->fresh()->nameservers)->toBe(['ns1.onhost.cz', 'ns2.onhost.cz'])
        ->and($b->fresh()->nameservers)->toBe(['ns1.onhost.cz', 'ns2.onhost.cz']);
});

it('never touches a domain belonging to another customer in a bulk change', function (): void {
    $mine     = ownedDomain($user = customerUser(), 'moje');
    $theirs   = ownedDomain(customerUser(), 'cizi');
    $original = $theirs->nameservers;

    $this->actingAs($user)
        ->post(route('panel.domains.bulk-nameservers'), [
            'domain_ids'  => [$mine->id, $theirs->id], // smuggling someone else's id
            'nameservers' => ['ns1.evil.cz'],
        ])
        ->assertRedirect();

    expect($mine->fresh()->nameservers)->toBe(['ns1.evil.cz'])
        ->and($theirs->fresh()->nameservers)->toBe($original);
});

it('rejects an invalid nameserver hostname', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.index'))
        ->post(route('panel.domains.bulk-nameservers'), [
            'domain_ids'  => [$domain->id],
            'nameservers' => ['not a hostname'],
        ])
        ->assertSessionHasErrors('nameservers.0');
});

// ── 64: DNSSEC (DS record) management ───────────────────────────────────────────

it('shows the DNSSEC card on the DNS page', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->get(route('panel.domains.dns', $domain))
        ->assertOk()
        ->assertSee('DNSSEC')
        // Mock mode never fabricates a fake DS key — an honest "off".
        ->assertSee('DNSSEC není pro tuto doménu aktivní');
});

it('accepts a well-formed DS record', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->post(route('panel.domains.dnssec.store', $domain), [
            'key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2,
            'digest'  => '2BB183AF5F22588179A53B0A98631FAD1A292118',
        ])
        ->assertRedirect(route('panel.domains.dns', $domain))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('activity_log', ['description' => 'dnssec.key_added']);
});

it('rejects a non-hex digest', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->post(route('panel.domains.dnssec.store', $domain), [
            'key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2, 'digest' => 'not-hex-!!',
        ])
        ->assertSessionHasErrors('digest');
});

it('rejects an algorithm no registry accepts', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->post(route('panel.domains.dnssec.store', $domain), [
            'key_tag' => 1, 'algorithm' => 999, 'digest_type' => 2, 'digest' => 'ABCD',
        ])
        ->assertSessionHasErrors('algorithm');
});

it('removes a DS record by key tag', function (): void {
    $user   = customerUser();
    $domain = ownedDomain($user);

    $this->actingAs($user)
        ->from(route('panel.domains.dns', $domain))
        ->delete(route('panel.domains.dnssec.destroy', $domain), ['key_tag' => 12345])
        ->assertRedirect(route('panel.domains.dns', $domain))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('activity_log', ['description' => 'dnssec.key_deleted']);
});

it('forbids managing DNSSEC on someone else’s domain', function (): void {
    $theirs = ownedDomain(customerUser());

    $this->actingAs(customerUser())
        ->post(route('panel.domains.dnssec.store', $theirs), [
            'key_tag' => 1, 'algorithm' => 13, 'digest_type' => 2, 'digest' => 'ABCD',
        ])
        ->assertForbidden();
});
