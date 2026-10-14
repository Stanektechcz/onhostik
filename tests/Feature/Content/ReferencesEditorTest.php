<?php

declare(strict_types=1);

use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Content\Models\ContentReference;
use Onhost\Platform\Audit\AuditEvent;

/*
 * I-R18 (owner, 2026-10-08): customer references are edited in the administration (ContentCommand on the bus, audited, a publish
 * flag and an order) and the public pages show exactly the published ones. A few clearly marked DEMO entries are seeded outside
 * production only, and in production a demo entry is never shown unless a member of staff published it there.
 */

beforeEach(fn () => Cache::forget('surfaces:onhost-data.js'));

/** @return list<array<string, string>> */
function referencesOnSite($test, string $locale = 'cs'): array
{
    Cache::forget('surfaces:onhost-data.js');
    $js = $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) (json_decode($m[1] ?? '{}', true)[$locale]['references'] ?? []);
}

it('creates, publishes, orders, edits and deletes references through the bus', function () {
    $this->actingAs($this->staff('marketing_content'), 'sanctum');

    $a = $this->postJson('/v1/staff/content/references', ['quote' => ['cs' => 'Přechod proběhl bez potíží.', 'en' => 'The move went smoothly.'], 'who' => 'Jana Malá', 'role' => ['cs' => 'jednatelka, Malá s.r.o.']])
        ->assertCreated()->assertJsonPath('data.published', false)->json('data');
    $b = $this->postJson('/v1/staff/content/references', ['quote' => ['cs' => 'Podpora odpověděla věcně.'], 'who' => 'Petr Velký'])->assertCreated()->json('data');
    expect(referencesOnSite($this))->toBe([]); // nothing is public before it is published

    $this->postJson("/v1/staff/content/references/{$a['id']}/state", ['published' => true])->assertOk()->assertJsonPath('data.published', true);
    $this->postJson("/v1/staff/content/references/{$b['id']}/state", ['published' => true])->assertOk();
    $this->postJson('/v1/staff/content/references/order', ['ids' => [$b['id'], $a['id']]])->assertOk();
    expect(referencesOnSite($this))->toBe([
        ['quote' => 'Podpora odpověděla věcně.', 'who' => 'Petr Velký', 'role' => ''],
        ['quote' => 'Přechod proběhl bez potíží.', 'who' => 'Jana Malá', 'role' => 'jednatelka, Malá s.r.o.'],
    ])->and(referencesOnSite($this, 'en')[1]['quote'])->toBe('The move went smoothly.');

    $this->patchJson("/v1/staff/content/references/{$a['id']}", ['quote' => ['cs' => 'Přechod <b>proběhl</b> bez potíží.<script>x</script>']])->assertOk()->assertJsonPath('data.quote.cs', 'Přechod proběhl bez potíží.x');
    $this->postJson("/v1/staff/content/references/{$b['id']}/state", ['published' => false])->assertOk();
    expect(collect(referencesOnSite($this))->pluck('who')->all())->toBe(['Jana Malá']);

    $this->deleteJson("/v1/staff/content/references/{$b['id']}")->assertOk();
    expect(ContentReference::query()->find($b['id']))->toBeNull();
    foreach (['content.reference.create', 'content.reference.state', 'content.reference.order', 'content.reference.update', 'content.reference.delete'] as $action) {
        expect(AuditEvent::query()->where('action', $action)->where('result', 'succeeded')->exists())->toBeTrue($action);
    }

    $this->getJson('/v1/staff/content/references')->assertOk()->assertJsonCount(1, 'data');
});

it('refuses a reference without a quote or a name, an unknown id in the order and a writer without content.manage', function () {
    $this->actingAs($this->staff('marketing_content'), 'sanctum');
    $this->postJson('/v1/staff/content/references', ['quote' => ['cs' => '<b></b>'], 'who' => 'Jana'])->assertUnprocessable()->assertJsonPath('error', 'reference_incomplete');
    $this->postJson('/v1/staff/content/references', ['quote' => ['cs' => 'Text'], 'who' => ''])->assertUnprocessable();
    $this->postJson('/v1/staff/content/references/order', ['ids' => ['ref_missing']])->assertUnprocessable()->assertJsonPath('error', 'reference_order_invalid');

    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->getJson('/v1/staff/content/references')->assertForbidden();
    $this->postJson('/v1/staff/content/references', ['quote' => ['cs' => 'Text'], 'who' => 'Jana'])->assertForbidden();
});

it('seeds clearly marked DEMO references outside production only and never shows them in production unless staff published them', function () {
    $this->seed(ContentSeeder::class);
    $demo = ContentReference::query()->where('demo', true)->get();
    expect($demo)->not->toBeEmpty()->and($demo->every(fn (ContentReference $r) => str_contains($r->who, 'DEMO')))->toBeTrue();
    expect(collect(referencesOnSite($this))->pluck('who')->implode(' '))->toContain('DEMO'); // shown on a development site

    app()->detectEnvironment(fn () => 'production');
    expect(referencesOnSite($this))->toBe([]); // seeded demo rows are not shown in production

    $staff = $this->staff('marketing_content');
    $this->actingAs($staff, 'sanctum');
    $first = $demo->first();
    $this->postJson("/v1/staff/content/references/{$first->id}/state", ['published' => false])->assertOk();
    $this->postJson("/v1/staff/content/references/{$first->id}/state", ['published' => true])->assertOk();
    expect(collect(referencesOnSite($this))->pluck('who')->all())->toBe([$first->who]); // published by staff on purpose

    ContentReference::query()->delete();
    app(ContentSeeder::class)->run(); // the seeder adds no demo rows in production (called directly: db:seed asks for confirmation in production)
    expect(ContentReference::query()->count())->toBe(0);
});
