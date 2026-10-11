<?php

declare(strict_types=1);

namespace Onhost\Domain\Content;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Onhost\Domain\Content\Models\ContentReference;
use Onhost\Domain\Content\Models\Post;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Staff editing of the blog and of the customer references (I-R17, I-R18). Called only by ContentCommandHandler, so every write is a
 * bus command (permission `content.manage`, audited). Everything stored is plain text (ContentText); a post's slug is fixed once it
 * was published (a published address does not move); a published post cannot be deleted, only withdrawn or archived first.
 */
final class ContentEditor
{
    /** The generated public data script reads posts and references; an edit shows on the site at the next request. */
    public const PUBLIC_CACHE_KEY = 'surfaces:onhost-data.js';

    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const MAX_SECTIONS = 40;

    // ── posts ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $input */
    public function createPost(array $input): Post
    {
        $fields = $this->postFields($input, null);
        $slug = $this->slug($input['slug'] ?? null, $fields['title']['cs'] ?? '');
        if (Post::query()->where('slug', $slug)->exists() || in_array($slug, ContentService::WITHHELD_POSTS, true)) {
            throw new DomainError('post_slug_taken', "A post with the address {$slug} already exists.", 409, ['field' => 'slug']);
        }
        $post = Post::query()->create($fields + ['slug' => $slug, 'kind' => 'blog', 'state' => 'draft', 'revision' => 1]);

        return $this->touched($post);
    }

    /** @param array<string, mixed> $input */
    public function updatePost(Post $post, array $input): Post
    {
        $fields = $this->postFields($input, $post);
        if (array_key_exists('slug', $input) && $input['slug'] !== null && $input['slug'] !== $post->slug) {
            if ($post->state === 'published' || $post->published_on !== null) {
                throw new DomainError('post_slug_fixed', 'A post that was published keeps its address.', 409, ['field' => 'slug']);
            }
            $slug = $this->slug($input['slug'], '');
            if (Post::query()->where('slug', $slug)->whereKeyNot($post->id)->exists()) {
                throw new DomainError('post_slug_taken', "A post with the address {$slug} already exists.", 409, ['field' => 'slug']);
            }
            $fields['slug'] = $slug;
        }
        $post->forceFill($fields + ['revision' => (int) $post->revision + 1])->save();

        return $this->touched($post);
    }

    public function setPostState(Post $post, string $state): Post
    {
        if (! in_array($state, Post::STATES, true)) {
            throw new DomainError('post_state_invalid', 'The state is draft, published or archived.', 422, ['field' => 'state']);
        }
        $changes = ['state' => $state, 'revision' => (int) $post->revision + 1];
        if ($state === 'published' && $post->published_on === null) {
            $changes['published_on'] = now('Europe/Prague')->toDateString();
        }
        $post->forceFill($changes)->save();

        return $this->touched($post);
    }

    public function deletePost(Post $post): void
    {
        if ($post->state === 'published') {
            throw new DomainError('post_published', 'Withdraw or archive the post before deleting it.', 409);
        }
        $post->delete();
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    /**
     * The old `PUT /v1/staff/content/posts` (kept for the clients that used it): create or replace by slug, in any state.
     *
     * @param  array<string, mixed>  $input
     */
    public function upsertPost(array $input): Post
    {
        $slug = $this->slug($input['slug'] ?? null, '');
        $post = Post::query()->where('slug', $slug)->first();
        $post = $post === null ? $this->createPost($input + ['slug' => $slug]) : $this->updatePost($post, array_diff_key($input, ['slug' => 1]));
        $state = $input['state'] ?? null;

        return is_string($state) && $state !== $post->state ? $this->setPostState($post, $state) : $post;
    }

    // ── references ──────────────────────────────────────────────────────────

    /** @param array<string, mixed> $input */
    public function createReference(array $input): ContentReference
    {
        $fields = $this->referenceFields($input, null);
        $fields['sort'] = isset($input['sort']) ? max(0, (int) $input['sort']) : ((int) ContentReference::query()->max('sort')) + 1;
        $reference = ContentReference::query()->create($fields + ['published' => false, 'demo' => false, 'revision' => 1]);

        return $this->touched($reference);
    }

    /** @param array<string, mixed> $input */
    public function updateReference(ContentReference $reference, array $input): ContentReference
    {
        $fields = $this->referenceFields($input, $reference);
        if (isset($input['sort'])) {
            $fields['sort'] = max(0, (int) $input['sort']);
        }
        $reference->forceFill($fields + ['revision' => (int) $reference->revision + 1])->save();

        return $this->touched($reference);
    }

    public function setReferencePublished(ContentReference $reference, bool $published, CommandContext $context): ContentReference
    {
        $reference->forceFill([
            'published' => $published,
            'published_by' => $published ? $context->actorId : null,
            'published_at' => $published ? now() : null,
            'revision' => (int) $reference->revision + 1,
        ])->save();

        return $this->touched($reference);
    }

    public function deleteReference(ContentReference $reference): void
    {
        $reference->delete();
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    /**
     * New order: the listed ids first, in that order; the rest keep their relative order after them.
     *
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    public function orderReferences(array $ids): array
    {
        $ids = array_values(array_unique(array_map('strval', $ids)));
        $known = ContentReference::query()->whereIn('id', $ids)->pluck('id')->all();
        if ($ids === [] || count($known) !== count($ids)) {
            throw new DomainError('reference_order_invalid', 'The order names a reference that does not exist.', 422, ['field' => 'ids']);
        }
        $rest = ContentReference::query()->whereNotIn('id', $ids)->orderBy('sort')->orderBy('created_at')->pluck('id')->all();
        foreach ([...$ids, ...$rest] as $position => $id) {
            ContentReference::query()->whereKey($id)->update(['sort' => $position + 1]);
        }
        Cache::forget(self::PUBLIC_CACHE_KEY);

        return ContentReference::query()->orderBy('sort')->get()->map(fn (ContentReference $r) => $r->toAdmin())->values()->all();
    }

    // ── shared ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function postFields(array $input, ?Post $post): array
    {
        $fields = [];
        foreach (['title' => 250, 'category' => 60, 'excerpt' => 500] as $key => $max) {
            if (array_key_exists($key, $input)) {
                $fields[$key] = array_replace($post?->{$key} ?? [], ContentText::locales($input[$key], $max, $key === 'excerpt'));
            }
        }
        if (array_key_exists('body', $input)) {
            $fields['body'] = array_replace($post?->body ?? [], $this->body($input['body']));
        }
        if (array_key_exists('author', $input)) {
            $fields['author'] = ContentText::line($input['author'], 120) ?: null;
        }
        foreach (['read_minutes' => 'int', 'featured' => 'bool', 'published_on' => 'date'] as $key => $type) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                $fields[$key] = match ($type) {
                    'int' => max(1, min(120, (int) $input[$key])), 'bool' => (bool) $input[$key], default => (string) $input[$key]
                };
            }
        }
        $title = $fields['title'] ?? $post?->title ?? [];
        $body = $fields['body'] ?? $post?->body ?? [];
        if (($title['cs'] ?? '') === '') {
            throw new DomainError('post_title_empty', 'The post needs a Czech title.', 422, ['field' => 'title.cs']);
        }
        if (($body['cs'] ?? []) === []) {
            throw new DomainError('post_body_empty', 'The post needs a Czech text.', 422, ['field' => 'body.cs']);
        }
        if (($fields['category']['cs'] ?? $post?->category['cs'] ?? '') === '') {
            throw new DomainError('post_category_empty', 'The post needs a Czech category.', 422, ['field' => 'category.cs']);
        }

        return $fields;
    }

    /** @return array<string, list<array{0:string,1:string}>> `{cs: [[heading, paragraph], …]}`, empty sections dropped */
    private function body(mixed $value): array
    {
        $out = [];
        foreach (['cs', 'en'] as $locale) {
            $sections = [];
            foreach (array_slice(is_array($value) && is_array($value[$locale] ?? null) ? array_values($value[$locale]) : [], 0, self::MAX_SECTIONS) as $section) {
                $section = is_array($section) ? array_values($section) : ['', $section];
                $heading = ContentText::line($section[0] ?? '', 160);
                $paragraph = ContentText::paragraph($section[1] ?? '', 5000);
                if ($heading !== '' || $paragraph !== '') {
                    $sections[] = [$heading, $paragraph];
                }
            }
            if ($sections !== []) {
                $out[$locale] = $sections;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function referenceFields(array $input, ?ContentReference $reference): array
    {
        $fields = [];
        if (array_key_exists('quote', $input)) {
            $fields['quote'] = array_replace($reference?->quote ?? [], ContentText::locales($input['quote'], 600, true));
        }
        if (array_key_exists('who', $input)) {
            $fields['who'] = ContentText::line($input['who'], 120);
        }
        if (array_key_exists('role', $input)) {
            $fields['role'] = ContentText::locales($input['role'], 160) ?: null;
        }
        if ((($fields['quote'] ?? $reference?->quote ?? [])['cs'] ?? '') === '' || ($fields['who'] ?? $reference?->who ?? '') === '') {
            throw new DomainError('reference_incomplete', 'A reference needs a Czech quote and the name of the person quoted.', 422, ['field' => 'quote.cs']);
        }

        return $fields;
    }

    private function slug(mixed $given, string $title): string
    {
        $slug = is_string($given) && $given !== '' ? $given : Str::slug($title, '-', 'cs');
        $slug = mb_substr($slug, 0, 120);
        if (preg_match(self::SLUG, $slug) !== 1) {
            throw new DomainError('post_slug_invalid', 'The address uses lower-case letters, digits and hyphens only.', 422, ['field' => 'slug']);
        }

        return $slug;
    }

    /**
     * @template T of Post|ContentReference
     *
     * @param  T  $model
     * @return T
     */
    private function touched(Post|ContentReference $model): Post|ContentReference
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);

        return $model->refresh();
    }
}
