<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Organizations\ProjectService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Assistant\AssistantScope;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Commands\CommandContext;
use Tests\TestCase;

/*
 * TASK-0043 (permission program S1-09, D19): a customer's ticket is read by the people of the service, project or billing it
 * concerns. Every member holding `support.ticket.read` read every ticket of the organization — a developer of one project the
 * tickets of another, anybody the billing tickets — and somebody whose role was given in one project only read none, not even
 * the tickets about the services they look after. Owner, organization admin and billing admin keep what they saw.
 */

beforeEach(function () {
    $this->seed(LegalEntitySeeder::class);
    Http::preventStrayRequests();
});

/** @return array{0: User, 1: Organization, 2: array<string, Ticket>, 3: array<string, Project>, 4: array<string, Service>} */
function tvisWorld(TestCase $test): array
{
    $owner = User::factory()->create(); // TestCase::customerWithOrganization() is protected: the same organization, built here
    $org = app(OrganizationService::class)->create($owner, ['name' => 'Test s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $projects = [
        'a' => Project::query()->create(['organization_id' => $org->id, 'name' => 'E-shop', 'slug' => 'tvis-a', 'tags' => []]),
        'b' => Project::query()->create(['organization_id' => $org->id, 'name' => 'Blog', 'slug' => 'tvis-b', 'tags' => []]),
    ];
    $services = ['a' => featureWebService($org, 'aapanel'), 'b' => featureMailService($org, 'blog-posta.cz')];
    $services['a']->forceFill(['project_id' => $projects['a']->id])->save();
    $services['b']->forceFill(['project_id' => $projects['b']->id])->save();
    $tickets = app(TicketService::class);
    $system = CommandContext::system('test');
    $open = fn (array $input) => $tickets->create($input + ['channel' => 'portal'], $system, $org, $owner);

    return [$owner, $org, [
        'a' => $open(['subject' => 'Eshop nejede', 'body' => 'Web vrací 502 od rána.', 'category' => 'dostupnost', 'service_id' => $services['a']->id]),
        'b' => $open(['subject' => 'Blog: pošta nechodí', 'body' => 'Maily na blog nechodí.', 'category' => 'mail', 'service_id' => $services['b']->id]),
        'billing' => $open(['subject' => 'Faktura za září', 'body' => 'Na faktuře nesedí IČO.', 'category' => 'fakturace']),
        'general' => $open(['subject' => 'Dotaz', 'body' => 'Jak funguje podpora o víkendu?', 'category' => 'ostatni']),
    ], $projects, $services];
}

function tvisMember(Organization $org, string $email, string $orgRole, ?Project $project = null, ?string $projectRole = null): User
{
    $user = User::query()->create(['email' => $email, 'name' => $email, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    app(OrganizationService::class)->attachMember($org, $user, $orgRole, CommandContext::system('test'), true);
    if ($project !== null && $projectRole !== null) {
        app(ProjectService::class)->addMember($org, $project, $user, $projectRole, CommandContext::system('test'));
    }
    app(Authorizer::class)->forget($user);

    return $user;
}

/** @return list<string> the ids the person lists, sorted */
function tvisListed(TestCase $test, User $user, Organization $org): array
{
    $ids = array_column($test->actingAs($user, 'sanctum')->getJson('/v1/tickets?limit=100', ['X-Organization' => $org->id])->assertOk()->json('data'), 'id');
    sort($ids);

    return $ids;
}

/** @param list<Ticket> $tickets @return list<string> */
function tvisIds(array $tickets): array
{
    $ids = array_map(fn (Ticket $t) => $t->id, $tickets);
    sort($ids);

    return $ids;
}

it('lets a project developer read and answer the tickets of their project, and nothing of another project or of billing', function () {
    [, $org, $tickets, $projects, $services] = tvisWorld($this);
    $developer = tvisMember($org, 'dev@tvis.test', 'guest', $projects['a'], 'developer');
    $h = ['X-Organization' => $org->id];

    expect(tvisListed($this, $developer, $org))->toBe([$tickets['a']->id]);
    $this->getJson("/v1/tickets/{$tickets['a']->id}", $h)->assertOk();
    $this->postJson("/v1/tickets/{$tickets['a']->id}/messages", ['body' => 'Díval jsem se do logu.'], $h)->assertOk();
    $this->getJson("/v1/tickets/{$tickets['b']->id}", $h)->assertNotFound(); // another project's service: not even its existence
    $this->getJson("/v1/tickets/{$tickets['billing']->id}", $h)->assertNotFound();
    $this->getJson("/v1/tickets/{$tickets['general']->id}", $h)->assertNotFound();
    $this->postJson("/v1/tickets/{$tickets['b']->id}/messages", ['body' => 'x'], $h)->assertNotFound();
    $this->getJson("/v1/tickets/{$tickets['b']->id}/work-offers", $h)->assertNotFound();
    // they open a ticket about their own service, not about the organization at large
    $this->postJson('/v1/tickets', ['subject' => 'Eshop pomalý', 'body' => 'Načítání trvá 10 s.', 'service_id' => $services['a']->id], $h)->assertCreated();
    $this->postJson('/v1/tickets', ['subject' => 'Blog', 'body' => 'Pošta', 'service_id' => $services['b']->id], $h)->assertForbidden();
    $this->postJson('/v1/tickets', ['subject' => 'Obecný dotaz', 'body' => 'Jak?'], $h)->assertForbidden();
});

it('keeps what the owner, the organization admin, the billing admin and an organization-wide viewer saw', function () {
    [$owner, $org, $tickets] = tvisWorld($this);
    $all = tvisIds(array_values($tickets));

    expect(tvisListed($this, $owner, $org))->toBe($all)
        ->and(tvisListed($this, tvisMember($org, 'admin@tvis.test', 'org_admin'), $org))->toBe($all)
        ->and(tvisListed($this, tvisMember($org, 'ucetni@tvis.test', 'billing_admin'), $org))->toBe($all)
        ->and(tvisListed($this, tvisMember($org, 'ctenar@tvis.test', 'viewer'), $org))->toBe($all);
    $this->actingAs($owner, 'sanctum')->getJson("/v1/tickets/{$tickets['billing']->id}", ['X-Organization' => $org->id])->assertOk();
});

it('takes the billing tickets of others from a support contact, but leaves them their own', function () {
    [, $org, $tickets] = tvisWorld($this);
    $contact = tvisMember($org, 'kontakt@tvis.test', 'support_contact');
    $h = ['X-Organization' => $org->id];

    expect(tvisListed($this, $contact, $org))->toBe(tvisIds([$tickets['a'], $tickets['b'], $tickets['general']]));
    $this->getJson("/v1/tickets/{$tickets['billing']->id}", $h)->assertNotFound();
    $mine = $this->postJson('/v1/tickets', ['subject' => 'Platba kartou', 'body' => 'Platba neprošla.', 'category' => 'fakturace'], $h)->assertCreated()->json('data.id');
    $this->getJson("/v1/tickets/{$mine}", $h)->assertOk();
    expect(tvisListed($this, $contact, $org))->toContain($mine)->not->toContain($tickets['billing']->id);
});

it('still refuses somebody with no ticket right in the organization, and a stranger', function () {
    [, $org, $tickets, , $services] = tvisWorld($this);
    $guest = tvisMember($org, 'host@tvis.test', 'guest');
    $h = ['X-Organization' => $org->id];

    $this->actingAs($guest, 'sanctum')->getJson('/v1/tickets', $h)->assertForbidden();
    $this->getJson("/v1/tickets/{$tickets['a']->id}", $h)->assertForbidden();
    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum')->getJson("/v1/tickets/{$tickets['a']->id}")->assertForbidden();
    expect(Ticket::query()->where('service_id', $services['a']->id)->count())->toBe(1);
});

it('shows the assistant only the open tickets the person may read', function () {
    [, $org, $tickets, $projects] = tvisWorld($this);
    $developer = tvisMember($org, 'dev2@tvis.test', 'viewer', $projects['a'], 'developer');
    $contact = tvisMember($org, 'kontakt2@tvis.test', 'support_contact');

    $lines = fn (User $user) => array_column(app(AssistantService::class)->detail($org, 'objednavka', 'cs', AssistantScope::for($org, $user, app(Authorizer::class))), 'k');
    expect($lines($contact))->not->toContain('Tiket '.$tickets['billing']->number)->toContain('Tiket '.$tickets['a']->number);
    expect(Str::of(implode(' ', $lines($developer)))->contains('Tiket '))->toBeTrue(); // an organization-wide viewer keeps the list
});

it('shows the customer their own "resolved" note: closing a ticket from the portal leaves a public message', function () {
    [$owner, $org, $tickets] = tvisWorld($this);
    $h = ['X-Organization' => $org->id];

    $this->actingAs($owner, 'sanctum')->postJson("/v1/tickets/{$tickets['general']->id}/close", [], $h)->assertOk();

    $note = $tickets['general']->messages()->where('body', 'Zákazník označil požadavek za vyřešený.')->first();
    expect($note)->not->toBeNull()->and($note->visibility)->toBe('public');
    expect(array_column($this->getJson("/v1/tickets/{$tickets['general']->id}", $h)->assertOk()->json('data.messages'), 'text'))
        ->toContain('Zákazník označil požadavek za vyřešený.');
});
