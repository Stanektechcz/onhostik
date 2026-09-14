<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Content\ContentService;
use Onhost\Domain\Content\Models\ChangelogEntry;
use Onhost\Domain\Content\Models\Lead;
use Onhost\Domain\Content\Models\Post;
use Onhost\Domain\Content\Models\StockItem;
use Onhost\Domain\Support\Models\KnowledgeArticle;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Admin content editing (`content.manage`, `support.kb.manage`) and the sales inbox of leads. */
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

    public function upsertPost(Request $request, AuditRecorder $audit): JsonResponse
    {
        $this->api->authorize($request, 'content.manage', CommandScope::global());
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9-]+$/'], 'kind' => ['nullable', 'in:blog,page'], 'category' => ['required', 'array'], 'category.cs' => ['required', 'string', 'max:60'], 'title' => ['required', 'array'], 'title.cs' => ['required', 'string', 'max:250'],
            'excerpt' => ['nullable', 'array'], 'body' => ['required', 'array'], 'body.cs' => ['required', 'array'], 'author' => ['nullable', 'string', 'max:120'], 'read_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'featured' => ['nullable', 'boolean'], 'state' => ['nullable', 'in:draft,published,archived'], 'published_on' => ['nullable', 'date'],
        ]);
        $post = Post::query()->updateOrCreate(['slug' => $data['slug']], array_diff_key($data, ['slug' => 1]) + ['kind' => 'blog']);
        $audit->record($this->api->context($request), 'content.post.upsert', 'succeeded', ['slug' => $post->slug, 'state' => $post->state], 'post', $post->id);

        return $this->ok($post->toArray());
    }

    public function upsertArticle(Request $request, AuditRecorder $audit): JsonResponse
    {
        $this->api->authorize($request, 'support.kb.manage', CommandScope::global());
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9-]+$/'], 'category' => ['required', 'string', 'max:60'], 'title' => ['required', 'array'], 'title.cs' => ['required', 'string', 'max:250'], 'excerpt' => ['nullable', 'array'],
            'body' => ['required', 'array'], 'body.cs' => ['required', 'array'], 'tags' => ['nullable', 'array'], 'state' => ['nullable', 'in:draft,published,archived'], 'read_minutes' => ['nullable', 'integer', 'min:1', 'max:120'], 'author' => ['nullable', 'string', 'max:120'],
        ]);
        $article = KnowledgeArticle::query()->updateOrCreate(['slug' => $data['slug']], array_diff_key($data, ['slug' => 1]) + ['published_at' => now()]);
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
