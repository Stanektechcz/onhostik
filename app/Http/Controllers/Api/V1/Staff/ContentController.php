<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Support\PublicSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Content\Commands\ContentCommand;
use Onhost\Domain\Content\ContentService;
use Onhost\Domain\Content\Models\ChangelogEntry;
use Onhost\Domain\Content\Models\ContentReference;
use Onhost\Domain\Content\Models\Lead;
use Onhost\Domain\Content\Models\Post;
use Onhost\Domain\Content\Models\StockItem;
use Onhost\Domain\Support\Models\KnowledgeArticle;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Admin content editing (`content.manage`, `support.kb.manage`) and the sales inbox of leads. The blog and the customer references
 * (I-R17, I-R18) are written through ContentCommand on the bus; the knowledge base, changelog and stock keep their direct upserts.
 */
final class ContentController extends ApiController
{
    public function leads(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.read', CommandScope::global());
        $query = Lead::query();
        foreach (['kind', 'state'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        return $this->api->paginate($request, $query, fn (Lead $l) => $l->toArray());
    }

    public function transitionLead(Request $request, string $lead, ContentService $content, AuditRecorder $audit): JsonResponse
    {
        $this->api->authorize($request, 'staff.order.manage', CommandScope::global());
        $data = $request->validate(['state' => ['required', 'in:'.implode(',', Lead::STATES)], 'note' => ['nullable', 'string', 'max:2000']]);
        $model = Lead::query()->find($lead);
        if ($model === null) {
            throw DomainError::notFound('lead');
        }

        return $this->ok($content->transitionLead($model, $data['state'], $this->api->context($request, null, $data['note'] ?? null), $data['note'] ?? null)->toArray());
    }

    // ── blog (I-R17) and customer references (I-R18): every write is a ContentCommand on the bus ──────────────────────────

    /** The editor's list: every post in every state (drafts included), newest change first; `?state=` filters. */
    public function posts(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'content.manage', CommandScope::global());
        $query = Post::query()->where('kind', 'blog');
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (Post $p) => $p->toAdmin(), 'updated_at');
    }

    public function createPost(Request $request): JsonResponse
    {
        $data = $request->validate($this->postRules(true));

        return $this->content($request, $this->idempotencyKey($request, 'content.post.create'), ['op' => 'post.create', 'input' => $data], 201);
    }

    public function updatePost(Request $request, string $post): JsonResponse
    {
        $model = $this->findPost($post);
        $data = $request->validate($this->postRules(false));

        return $this->content($request, $this->idempotencyKey($request, "content.post.update:{$model->id}:{$model->revision}"), ['op' => 'post.update', 'id' => $model->id, 'input' => $data]);
    }

    public function postState(Request $request, string $post): JsonResponse
    {
        $model = $this->findPost($post);
        $data = $request->validate(['state' => ['required', 'in:'.implode(',', Post::STATES)]]);

        return $this->content($request, $this->idempotencyKey($request, "content.post.state:{$model->id}:{$model->revision}"), ['op' => 'post.state', 'id' => $model->id, 'state' => $data['state']]);
    }

    public function deletePost(Request $request, string $post): JsonResponse
    {
        $model = $this->findPost($post);

        return $this->content($request, $this->idempotencyKey($request, "content.post.delete:{$model->id}:{$model->revision}"), ['op' => 'post.delete', 'id' => $model->id]);
    }

    /** The original endpoint (create or replace by slug), now on the bus like the editor. */
    public function upsertPost(Request $request): JsonResponse
    {
        $data = $request->validate(['slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'], 'state' => ['nullable', 'in:'.implode(',', Post::STATES)]] + $this->postRules(false));
        $current = Post::query()->where('slug', $data['slug'])->value('revision');

        return $this->content($request, $this->idempotencyKey($request, 'content.post.upsert:'.$data['slug'].':'.($current ?? 'new')), ['op' => 'post.upsert', 'input' => $data]);
    }

    public function references(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'content.manage', CommandScope::global());

        return $this->api->paginate($request, ContentReference::query()->orderBy('sort')->orderBy('created_at'), fn (ContentReference $r) => $r->toAdmin(), 'sort');
    }

    public function createReference(Request $request): JsonResponse
    {
        $data = $request->validate($this->referenceRules(true));

        return $this->content($request, $this->idempotencyKey($request, 'content.reference.create'), ['op' => 'reference.create', 'input' => $data], 201);
    }

    public function updateReference(Request $request, string $reference): JsonResponse
    {
        $model = $this->findReference($reference);
        $data = $request->validate($this->referenceRules(false));

        return $this->content($request, $this->idempotencyKey($request, "content.reference.update:{$model->id}:{$model->revision}"), ['op' => 'reference.update', 'id' => $model->id, 'input' => $data]);
    }

    public function referenceState(Request $request, string $reference): JsonResponse
    {
        $model = $this->findReference($reference);
        $data = $request->validate(['published' => ['required', 'boolean']]);

        return $this->content($request, $this->idempotencyKey($request, "content.reference.state:{$model->id}:{$model->revision}"), ['op' => 'reference.state', 'id' => $model->id, 'published' => (bool) $data['published']]);
    }

    public function deleteReference(Request $request, string $reference): JsonResponse
    {
        $model = $this->findReference($reference);

        return $this->content($request, $this->idempotencyKey($request, "content.reference.delete:{$model->id}:{$model->revision}"), ['op' => 'reference.delete', 'id' => $model->id]);
    }

    public function orderReferences(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['required', 'string', 'max:40']]);
        $version = ContentReference::query()->orderBy('id')->get(['id', 'sort', 'revision'])->map(fn (ContentReference $r) => "{$r->id}:{$r->sort}:{$r->revision}")->implode('|');

        return $this->content($request, $this->idempotencyKey($request, 'content.reference.order:'.hash('crc32b', $version)), ['op' => 'reference.order', 'ids' => array_values($data['ids'])]);
    }

    /** @param array<string, mixed> $payload */
    private function content(Request $request, string $key, array $payload, int $status = 200): JsonResponse
    {
        $command = new ContentCommand($key, $payload);
        $this->api->assertTokenScope($request, $command->permission());

        return $this->ok($this->bus->dispatch($command, $this->api->context($request)), $status);
    }

    /** @return array<string, list<string>> plain-text fields; ContentEditor strips markup and checks what must not be empty */
    private function postRules(bool $create): array
    {
        $required = $create ? 'required' : 'sometimes';

        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => [$required, 'array'], 'title.cs' => [$required, 'string', 'max:250'], 'title.en' => ['nullable', 'string', 'max:250'],
            'category' => [$required, 'array'], 'category.cs' => [$required, 'string', 'max:60'], 'category.en' => ['nullable', 'string', 'max:60'],
            'excerpt' => ['sometimes', 'nullable', 'array'], 'excerpt.cs' => ['nullable', 'string', 'max:500'], 'excerpt.en' => ['nullable', 'string', 'max:500'],
            'body' => [$required, 'array'], 'body.cs' => [$required, 'array', 'max:40'], 'body.en' => ['nullable', 'array', 'max:40'],
            'body.*.*' => ['array', 'max:2'], 'body.*.*.*' => ['nullable', 'string', 'max:5000'],
            'author' => ['sometimes', 'nullable', 'string', 'max:120'], 'read_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'featured' => ['sometimes', 'nullable', 'boolean'], 'published_on' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<string, list<string>> */
    private function referenceRules(bool $create): array
    {
        $required = $create ? 'required' : 'sometimes';

        return [
            'quote' => [$required, 'array'], 'quote.cs' => [$required, 'string', 'max:600'], 'quote.en' => ['nullable', 'string', 'max:600'],
            'who' => [$required, 'string', 'max:120'],
            'role' => ['sometimes', 'nullable', 'array'], 'role.cs' => ['nullable', 'string', 'max:160'], 'role.en' => ['nullable', 'string', 'max:160'],
            'sort' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    private function findPost(string $id): Post
    {
        return Post::query()->find($id) ?? throw DomainError::notFound('post');
    }

    private function findReference(string $id): ContentReference
    {
        return ContentReference::query()->find($id) ?? throw DomainError::notFound('reference');
    }

    public function upsertArticle(Request $request, AuditRecorder $audit): JsonResponse
    {
        $this->api->authorize($request, 'support.kb.manage', CommandScope::global());
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9-]+$/'], 'category' => ['required', 'string', 'max:60'], 'title' => ['required', 'array'], 'title.cs' => ['required', 'string', 'max:250'], 'excerpt' => ['nullable', 'array'],
            'body' => ['required', 'array'], 'body.cs' => ['required', 'array'], 'tags' => ['nullable', 'array'], 'state' => ['nullable', 'in:draft,published,archived'], 'read_minutes' => ['nullable', 'integer', 'min:1', 'max:120'], 'author' => ['nullable', 'string', 'max:120'],
        ]);
        $article = KnowledgeArticle::query()->updateOrCreate(['slug' => $data['slug']], array_diff_key($data, ['slug' => 1]) + ['published_at' => now()]);
        Cache::forget(PublicSeo::SITEMAP_CACHE); // W7-4b: a published, changed or withdrawn article changes the sitemap
        $audit->record($this->api->context($request), 'content.kb.upsert', 'succeeded', ['slug' => $article->slug, 'state' => $article->state], 'knowledge_article', $article->id);

        return $this->ok($article->toArray());
    }

    public function upsertChangelog(Request $request, AuditRecorder $audit): JsonResponse
    {
        $this->api->authorize($request, 'content.manage', CommandScope::global());
        $data = $request->validate(['id' => ['nullable', 'string'], 'entry_date' => ['required', 'date'], 'tag' => ['required', 'in:'.implode(',', ChangelogEntry::TAGS)], 'title' => ['required', 'array'], 'title.cs' => ['required', 'string', 'max:250'], 'body' => ['required', 'array'], 'body.cs' => ['required', 'string', 'max:4000'], 'state' => ['nullable', 'in:draft,published']]);
        $entry = isset($data['id']) ? ChangelogEntry::query()->find($data['id']) : null;
        $entry = $entry ? tap($entry)->forceFill(array_diff_key($data, ['id' => 1]))->save() : ChangelogEntry::query()->create(array_diff_key($data, ['id' => 1]));
        $entry = $entry instanceof ChangelogEntry ? $entry : ChangelogEntry::query()->findOrFail($data['id']);
        $audit->record($this->api->context($request), 'content.changelog.upsert', 'succeeded', ['tag' => $entry->tag], 'changelog_entry', $entry->id);

        return $this->ok($entry->toArray());
    }

    public function upsertStock(Request $request, AuditRecorder $audit): JsonResponse
    {
        $this->api->authorize($request, 'content.manage', CommandScope::global());
        $data = $request->validate(['sku' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:120'], 'spec' => ['required', 'array'], 'spec.cs' => ['required', 'string', 'max:250'], 'price_minor' => ['required', 'integer', 'min:0'], 'currency' => ['nullable', 'in:CZK,EUR'], 'available' => ['required', 'integer', 'min:0'], 'lead_days' => ['nullable', 'integer', 'min:0'], 'availability' => ['required', 'in:ok,warn,off'], 'sort' => ['nullable', 'integer']]);
        $item = StockItem::query()->updateOrCreate(['sku' => $data['sku']], array_diff_key($data, ['sku' => 1]));
        $audit->record($this->api->context($request), 'content.stock.upsert', 'succeeded', ['sku' => $item->sku, 'available' => $item->available], 'stock_item', $item->id);

        return $this->ok($item->toArray());
    }
}
