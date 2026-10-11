<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Content\Models\Post;
use Onhost\Domain\Content\StarterPosts;
use Onhost\Platform\Audit\AuditEvent;

/*
 * I-R17 (owner, 2026-10-08): the blog is real — staff write, edit, publish, withdraw, archive and delete posts in the administration
 * (`content.manage`), every write is a ContentCommand on the bus and audited, the text is plain (tags and control characters are
 * stripped before it is stored), a draft is never public, and the public site reads only published posts.
 */

beforeEach(fn () => Cache::forget('surfaces:onhost-data.js'));

/** @return array<string, mixed> */
function blogEditorPayload(array $overrides = []): array
{
    return array_replace([
        'title' => ['cs' => 'Jak funguje kredit', 'en' => 'How credit works'],
        'category' => ['cs' => 'Fakturace', 'en' => 'Billing'],
        'excerpt' => ['cs' => 'Kredit, dobití a co se s ním stane.', 'en' => 'Credit, top-ups and what happens to it.'],
        'body' => [
            'cs' => [['', 'Kredit je předplacená částka.'], ['Dobití', 'Dobíjí se převodem nebo kartou.']],
            'en' => [['', 'Credit is a prepaid amount.'], ['Top-ups', 'Top up by transfer or card.']],
        ],
    ], $overrides);
}

/** @return list<array<string, mixed>> */
function blogEditorPublicPosts($test, string $locale = 'cs'): array
{
    Cache::forget('surfaces:onhost-data.js');
    $js = $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) (json_decode($m[1] ?? '{}', true)[$locale]['blog'] ?? []);
}

it('writes a draft through the bus, keeps it off the public site, then publishes, withdraws and archives it', function () {
    $this->actingAs($this->staff('marketing_content'), 'sanctum');

    $draft = $this->postJson('/v1/staff/content/posts', blogEditorPayload())->assertCreated()
        ->assertJsonPath('data.state', 'draft')->assertJsonPath('data.slug', 'jak-funguje-kredit')->json('data');
    expect(AuditEvent::query()->where('action', 'content.post.create')->where('result', 'succeeded')->exists())->toBeTrue();
    $this->getJson('/v1/posts/jak-funguje-kredit')->assertNotFound();
    expect(collect(blogEditorPublicPosts($this))->pluck('slug'))->not->toContain('jak-funguje-kredit');

    $this->patchJson("/v1/staff/content/posts/{$draft['id']}", ['title' => ['cs' => 'Jak funguje kredit v ONhostu']])->assertOk()
        ->assertJsonPath('data.title.cs', 'Jak funguje kredit v ONhostu')->assertJsonPath('data.slug', 'jak-funguje-kredit');

    $this->postJson("/v1/staff/content/posts/{$draft['id']}/state", ['state' => 'published'])->assertOk()->assertJsonPath('data.state', 'published');
    $this->getJson('/v1/posts/jak-funguje-kredit')->assertOk()->assertJsonPath('data.title', 'Jak funguje kredit v ONhostu');
    $public = collect(blogEditorPublicPosts($this))->firstWhere('slug', 'jak-funguje-kredit');
    expect($public)->toMatchArray(['title' => 'Jak funguje kredit v ONhostu', 'cat' => 'Fakturace'])
        ->and($public['body'])->toBe([['', 'Kredit je předplacená částka.'], ['Dobití', 'Dobíjí se převodem nebo kartou.']])
        ->and(collect(blogEditorPublicPosts($this, 'en'))->firstWhere('slug', 'jak-funguje-kredit')['title'])->toBe('How credit works');

    $this->postJson("/v1/staff/content/posts/{$draft['id']}/state", ['state' => 'draft'])->assertOk();
    $this->getJson('/v1/posts/jak-funguje-kredit')->assertNotFound();
    $this->postJson("/v1/staff/content/posts/{$draft['id']}/state", ['state' => 'published'])->assertOk()->assertJsonPath('data.state', 'published'); // a toggle back is a new act, not a replay
    $this->postJson("/v1/staff/content/posts/{$draft['id']}/state", ['state' => 'archived'])->assertOk();
    $this->getJson('/v1/posts/jak-funguje-kredit')->assertNotFound();

    $list = $this->getJson('/v1/staff/content/posts?state=archived')->assertOk();
    expect(collect($list->json('data'))->pluck('slug'))->toContain('jak-funguje-kredit');
    expect(AuditEvent::query()->where('action', 'content.post.state')->where('result', 'succeeded')->count())->toBe(4);
});

it('stores plain text only: tags, scripts and control characters never reach the public site', function () {
    $this->actingAs($this->staff('marketing_content'), 'sanctum');

    $post = $this->postJson('/v1/staff/content/posts', blogEditorPayload([
        'title' => ['cs' => '<script>alert(1)</script>Bezpečný <b>titulek</b>'],
        'body' => ['cs' => [['<img src=x onerror=alert(1)>Nadpis', "Odstavec\u{0007} s <a href=\"javascript:alert(1)\">odkazem</a>."]]],
        'author' => '<i>Tým</i> ONhost',
    ]))->assertCreated()->json('data');

    expect($post['title']['cs'])->toBe('alert(1)Bezpečný titulek')
        ->and($post['body']['cs'][0])->toBe(['Nadpis', 'Odstavec s odkazem.'])
        ->and($post['author'])->toBe('Tým ONhost');
    $this->postJson("/v1/staff/content/posts/{$post['id']}/state", ['state' => 'published'])->assertOk();
    $js = $this->get('/surfaces/onhost-data.js')->getContent();
    expect($js)->not->toContain('<script>')->not->toContain('onerror')->not->toContain('javascript:');
});

it('refuses a taken or malformed slug, an empty body and deleting a published post', function () {
    $this->actingAs($this->staff('marketing_content'), 'sanctum');
    $first = $this->postJson('/v1/staff/content/posts', blogEditorPayload())->assertCreated()->json('data');

    $this->postJson('/v1/staff/content/posts', blogEditorPayload(['excerpt' => ['cs' => 'jiný text']]))->assertStatus(409)->assertJsonPath('error', 'post_slug_taken');
    $this->postJson('/v1/staff/content/posts', blogEditorPayload(['slug' => 'Špatný slug!']))->assertUnprocessable();
    $this->postJson('/v1/staff/content/posts', blogEditorPayload(['slug' => 'prazdny', 'body' => ['cs' => [['', '   ']]]]))->assertUnprocessable()->assertJsonPath('error', 'post_body_empty');

    $this->postJson("/v1/staff/content/posts/{$first['id']}/state", ['state' => 'published'])->assertOk();
    $this->deleteJson("/v1/staff/content/posts/{$first['id']}")->assertStatus(409)->assertJsonPath('error', 'post_published');
    $this->postJson("/v1/staff/content/posts/{$first['id']}/state", ['state' => 'archived'])->assertOk();
    $this->deleteJson("/v1/staff/content/posts/{$first['id']}")->assertOk();
    expect(Post::query()->find($first['id']))->toBeNull()
        ->and(AuditEvent::query()->where('action', 'content.post.delete')->where('result', 'succeeded')->exists())->toBeTrue();
});

it('lets only content.manage write or read the drafts', function () {
    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->getJson('/v1/staff/content/posts')->assertForbidden();
    $this->postJson('/v1/staff/content/posts', blogEditorPayload())->assertForbidden();

    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->postJson('/v1/staff/content/posts', blogEditorPayload())->assertForbidden();
    expect(Post::query()->where('slug', 'jak-funguje-kredit')->exists())->toBeFalse();
});

it('keeps the old upsert endpoint working through the bus', function () {
    $this->actingAs($this->staff('marketing_content'), 'sanctum');

    $this->putJson('/v1/staff/content/posts', blogEditorPayload(['slug' => 'starsi-klient', 'state' => 'published']))->assertOk()->assertJsonPath('data.state', 'published');
    $this->putJson('/v1/staff/content/posts', blogEditorPayload(['slug' => 'starsi-klient', 'title' => ['cs' => 'Upraveno <b>klientem</b>']]))->assertOk()->assertJsonPath('data.title.cs', 'Upraveno klientem');
    expect(AuditEvent::query()->where('action', 'content.post.upsert')->where('result', 'succeeded')->count())->toBe(2);
});

it('retires the prototype posts with invented figures and publishes honest starter posts', function () {
    $migration = require database_path('migrations/0001_01_01_001150_content_references_and_honest_posts.php');
    Post::query()->create(['slug' => 'pue-118', 'kind' => 'blog', 'category' => ['cs' => 'Provoz'], 'title' => ['cs' => 'Jak jsme dostali PUE v Praze na 1,18'], 'body' => ['cs' => [['', 'PUE 1,54']]], 'state' => 'published']);
    $migration->up();

    expect(Post::query()->where('slug', 'pue-118')->value('state'))->toBe('archived');
    $published = $this->getJson('/v1/posts')->assertOk()->json('data');
    expect(collect($published)->pluck('slug')->sort()->values()->all())->toBe(collect(StarterPosts::SLUGS)->sort()->values()->all());
    foreach (StarterPosts::SLUGS as $slug) {
        $post = $this->getJson("/v1/posts/{$slug}")->assertOk()->json('data');
        expect($post['author'])->toBe('Tým ONhost')->and(json_encode($post, JSON_UNESCAPED_UNICODE))->not->toMatch('/\d+\s?%|Kč\/h|PUE|Gbps/');
    }

    $content = $this->get('/surfaces/onhost-content.js')->assertOk()->getContent();
    expect($content)->not->toContain('pue-118')->not->toContain('Marek Šimek')->toContain('ONHOST_DATA.blog');
});

it('opens the editor page in the settings for content.manage only', function () {
    $this->actingAs($this->staff('marketing_content'));
    $this->get('/sprava/nastaveni/obsah')->assertOk()->assertSee('Blog a reference')->assertSee('/v1/staff/content', false);

    $this->actingAs($this->staff('support_l1'));
    expect($this->get('/sprava/nastaveni/obsah')->status())->toBeIn([302, 403]);
});
