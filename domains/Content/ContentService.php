<?php

declare(strict_types=1);

namespace Onhost\Domain\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Onhost\Domain\Content\Models\ChangelogEntry;
use Onhost\Domain\Content\Models\ContentReference;
use Onhost\Domain\Content\Models\Lead;
use Onhost\Domain\Content\Models\Location;
use Onhost\Domain\Content\Models\Post;
use Onhost\Domain\Content\Models\StockItem;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Support\Models\KnowledgeArticle;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Public content in the exact shapes the prototype consumes (handoff docs-backend-handoff §2–3):
 * blog posts, knowledge base, changelog, locations, stock, plus inbound forms (leads, reseller, tender).
 */
final class ContentService
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly NotificationService $notifications,
        private readonly PartnerService $partners,
    ) {}

    /**
     * Posts the public site does not serve (I-R11, owner 2026-10-08: no fictional case studies, only real ones). The prototype's
     * "Skladomat" migration story names a customer, a bill and a downtime nobody can document. A real case study is published under
     * its own slug with the customer's approval.
     */
    public const WITHHELD_POSTS = ['migrace-z-cloudu'];

    /** I-R16 (owner, 2026-10-08): GPU cards are planned, not sold — no rental row, price or stock count for them is served. */
    public const NOT_ON_SALE_STOCK = ['l40s', 'h100'];

    /**
     * J-R1 (owner, 2026-10-11): the stock rows the prototype seed invented, as `sku => [available, price_minor]`. A database seeded
     * before 2026-10-11 still holds them; such a row is not served while it is untouched — once staff change its count or price it is.
     */
    public const PROTOTYPE_STOCK = ['epyc-9354' => [24, 890000], 'epyc-9124' => [41, 490000], 'storage-240' => [0, 640000]];

    /** @return list<array<string,mixed>> `{ slug, cat, title, excerpt, date, read, author, featured }` */
    public function posts(string $locale, ?string $category = null, string $kind = 'blog'): array
    {
        $posts = $this->publishedPosts($kind)->orderByDesc('published_on')->orderByDesc('created_at')->get();
        if ($category !== null && $category !== 'all') {
            $posts = $posts->filter(fn (Post $p) => $p->text('category', $locale) === $category);
        }

        return $posts->map(fn (Post $p) => $this->postSummary($p, $locale))->values()->all();
    }

    /**
     * One published post of a kind (/v1/posts/{slug} and, W7-4, the public /blog/{slug} page); a draft, an archived or a withheld
     * post is not found. `updated_at` (ISO 8601) and `translated` (the post has a title of its own in the locale) serve the page head.
     *
     * @return array<string, mixed>
     */
    public function post(string $slug, string $locale, string $kind = 'blog'): array
    {
        $post = $this->publishedPosts($kind)->where('slug', $slug)->first();
        if (! $post instanceof Post) {
            throw DomainError::notFound('post');
        }

        return $this->postSummary($post, $locale) + [
            'body' => $post->body[$locale] ?? $post->body['cs'] ?? [],
            'updated_at' => $post->updated_at === null ? null : Carbon::parse($post->updated_at)->toIso8601String(),
            'translated' => $this->ownTitle($post, $locale),
        ];
    }

    /**
     * W7-4: the published blog posts of the sitemap in one query — slug, last change and whether a Slovak title of its own exists.
     *
     * @return list<array{slug: string, updated_at: string|null, sk: bool}>
     */
    public function sitemapPosts(): array
    {
        return $this->publishedPosts('blog')->orderBy('slug')->get(['slug', 'updated_at', 'title'])
            ->map(fn (Post $p): array => [
                'slug' => (string) $p->slug,
                'updated_at' => $p->updated_at === null ? null : Carbon::parse($p->updated_at)->toIso8601String(),
                'sk' => $this->ownTitle($p, 'sk'),
            ])->values()->all();
    }

    /**
     * The one place the public visibility of posts lives: published, of the kind, never a withheld post (I-R11).
     *
     * @return Builder<Post>
     */
    private function publishedPosts(string $kind): Builder
    {
        return Post::query()->where('kind', $kind)->where('state', 'published')->whereNotIn('slug', self::WITHHELD_POSTS);
    }

    private function ownTitle(Post $post, string $locale): bool
    {
        $title = $post->title;

        return is_array($title) && is_string($title[$locale] ?? null) && $title[$locale] !== '';
    }

    /** Knowledge base search: title/excerpt/body substring match ranked by field, category filter. */
    public function kb(string $locale, ?string $query = null, ?string $category = null): array
    {
        $articles = KnowledgeArticle::query()->where('state', 'published')->orderByDesc('published_at')->get();
        $q = mb_strtolower(trim((string) $query));
        $out = [];
        foreach ($articles as $article) {
            $title = $this->localized($article->title, $locale);
            $excerpt = $this->localized($article->excerpt, $locale);
            $cat = $this->localized($article->category, $locale);
            if ($category !== null && $category !== 'all' && $cat !== $category) {
                continue;
            }
            $score = 0;
            if ($q !== '') {
                $body = $article->body[$locale] ?? $article->body['cs'] ?? [];
                $bodyText = mb_strtolower(implode(' ', array_map(fn ($p) => implode(' ', (array) $p), (array) $body)));
                $score = (str_contains(mb_strtolower($title), $q) ? 3 : 0) + (str_contains(mb_strtolower($excerpt), $q) ? 2 : 0) + (str_contains($bodyText, $q) ? 1 : 0);
                if ($score === 0) {
                    continue;
                }
            }
            $out[] = ['slug' => $article->slug, 'cat' => $cat, 'title' => $title, 'excerpt' => $excerpt, 'updated' => $article->published_at?->toDateString(), 'read' => "{$article->read_minutes} min", 'score' => $score];
        }
        usort($out, fn ($a, $b) => [$b['score'], $b['updated'] ?? ''] <=> [$a['score'], $a['updated'] ?? '']);

        return array_map(fn ($row) => array_diff_key($row, ['score' => 1]), $out);
    }

    public function kbArticle(string $slug, string $locale): array
    {
        $article = KnowledgeArticle::query()->where('slug', $slug)->where('state', 'published')->first();
        if ($article === null) {
            throw DomainError::notFound('article');
        }

        return [
            'slug' => $article->slug, 'cat' => $this->localized($article->category, $locale), 'title' => $this->localized($article->title, $locale), 'excerpt' => $this->localized($article->excerpt, $locale),
            'updated' => $article->published_at?->toDateString(), 'read' => "{$article->read_minutes} min", 'body' => $article->body[$locale] ?? $article->body['cs'] ?? [], 'tags' => $article->tags ?? [],
        ];
    }

    /**
     * W7-4b: the published knowledge base articles for the sitemap, in one query (slug and published_at only).
     *
     * @return list<array{slug: string, lastmod: string|null}>
     */
    public function kbSitemap(): array
    {
        return KnowledgeArticle::query()->where('state', 'published')->orderBy('slug')->get(['slug', 'published_at'])
            ->map(fn (KnowledgeArticle $a): array => ['slug' => (string) $a->slug, 'lastmod' => $a->published_at === null ? null : Carbon::parse($a->published_at)->toDateString()])
            ->values()->all();
    }

    /** `[date, tag, tagLabel, title, body][]` */
    public function changelog(string $locale, ?string $tag = null): array
    {
        return ChangelogEntry::query()->where('state', 'published')->when($tag !== null && $tag !== 'all', fn ($q) => $q->where('tag', $tag))->orderByDesc('entry_date')->get()
            ->map(fn (ChangelogEntry $e) => [$locale === 'cs' || Lexicon::slovak($locale) ? $e->entry_date->format('j. n.') : $e->entry_date->format('j M'), $e->tag, ChangelogEntry::LABELS[$locale][$e->tag] ?? strtoupper($e->tag), $e->title[$locale] ?? $e->title['cs'] ?? '', $e->body[$locale] ?? $e->body['cs'] ?? ''])->values()->all();
    }

    /** `[code, city, country, ping, live][]` */
    public function locations(string $locale): array
    {
        return Location::query()->orderBy('sort')->get()->map(fn (Location $l) => [$l->code, $l->city, $l->country[$locale] ?? $l->country['cs'] ?? '', $l->ping_ms === null ? '—' : "{$l->ping_ms} ms", $l->live ? 1 : 0])->values()->all();
    }

    /** `[name, spec, price, unit, availability, state][]` */
    public function stock(string $locale): array
    {
        $invented = fn (StockItem $s): bool => isset(self::PROTOTYPE_STOCK[$s->sku]) && self::PROTOTYPE_STOCK[$s->sku] === [(int) $s->available, (int) $s->price_minor];

        return StockItem::query()->whereNotIn('sku', self::NOT_ON_SALE_STOCK)->orderBy('sort')->get()->reject($invented)->map(function (StockItem $s) use ($locale) {
            $price = Money::minor($s->price_minor, $s->currency)->format($locale);
            if (Lexicon::slovak($locale)) { // W5-5: lang/sk/surface.php, plural forms through trans_choice
                return [$s->name, $s->spec[$locale] ?? $s->spec['cs'] ?? '', $price, __('surface.stock_unit', [], 'sk'), match (true) {
                    $s->available > 0 => trans_choice('surface.stock_units', (int) $s->available, ['count' => $s->available], 'sk'),
                    $s->lead_days > 0 => trans_choice('surface.stock_lead', (int) $s->lead_days, ['days' => $s->lead_days], 'sk'),
                    default => __('surface.stock_on_order', [], 'sk'),
                }, $s->availability];
            }
            $unit = $locale === 'cs' ? 'nájem / měsíc' : 'rental / month';
            $availability = match (true) {
                $s->available > 0 => $locale === 'cs' ? "{$s->available} ".($s->available === 1 ? 'kus' : ($s->available < 5 ? 'kusy' : 'kusů')).' skladem' : "{$s->available} units in stock",
                $s->lead_days > 0 => $locale === 'cs' ? "dodání {$s->lead_days} dnů" : "delivery in {$s->lead_days} days",
                default => $locale === 'cs' ? 'na objednávku' : 'made to order',
            };

            return [$s->name, $s->spec[$locale] ?? $s->spec['cs'] ?? '', $price, $unit, $availability, $s->availability];
        })->values()->all();
    }

    // ── inbound forms ────────────────────────────────────────────────────────

    /** @param array{kind:string,name:string,email:string,phone?:string,company?:string,message?:string,meta?:array,source?:string} $input */
    public function lead(array $input, CommandContext $context, ?Organization $organization = null): Lead
    {
        $kind = (string) ($input['kind'] ?? 'contact');
        if (! in_array($kind, Lead::KINDS, true)) {
            throw new DomainError('lead_kind_invalid', 'Unknown request kind.', 422, ['field' => 'kind']);
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainError('lead_email_invalid', 'A valid e-mail is required.', 422, ['field' => 'email']);
        }
        $lead = Lead::query()->create([
            'kind' => $kind, 'name' => mb_substr(trim((string) ($input['name'] ?? '')), 0, 120), 'email' => $email, 'phone' => isset($input['phone']) ? mb_substr((string) $input['phone'], 0, 40) : null,
            'company' => isset($input['company']) ? mb_substr((string) $input['company'], 0, 190) : null, 'message' => isset($input['message']) ? mb_substr((string) $input['message'], 0, 8000) : null,
            'meta' => (array) ($input['meta'] ?? []), 'source' => (string) ($input['source'] ?? 'web'), 'organization_id' => $organization?->id, 'state' => 'new',
        ]);
        $this->audit->record($context, 'lead.create', 'succeeded', ['kind' => $kind, 'company' => $lead->company], 'lead', $lead->id);
        $this->outbox->publish(GenericEvent::of('lead.received', 'lead', $lead->id, ['kind' => $kind, 'name' => $lead->name, 'company' => $lead->company, 'email' => $email], $organization?->id));

        return $lead;
    }

    /** Tender request: a lead plus the document pack mailed to the requester (`tdDocs` in the prototype). */
    public function tenderRequest(array $input, CommandContext $context, string $locale = 'cs'): Lead
    {
        $docs = (array) config("onhost.content.tender_docs.{$locale}", config('onhost.content.tender_docs.cs', []));
        $lead = $this->lead(['kind' => 'tender', 'meta' => ['documents' => array_column($docs, 0), 'deadline' => $input['deadline'] ?? null, 'authority' => $input['authority'] ?? null, 'scope' => $input['scope'] ?? null]] + $input, $context);
        $this->notifications->queueMail('tender-docs', $lead->email, [
            'jmeno' => $lead->name, 'dokumenty' => implode("\n", array_map(fn ($d) => '• '.$d[0].' — '.$d[1], $docs)), 'url' => rtrim((string) config('onhost.portal_url', config('app.url')), '/').'/verejne-zakazky',
        ], 'lead', $lead->id, null, $locale);

        return $lead;
    }

    /** Public reseller tiers (`rsTiers`): clients range, margin, benefit. */
    public function resellerTiers(string $locale): array
    {
        return (array) config("onhost.partners.public_tiers.{$locale}", config('onhost.partners.public_tiers.cs', []));
    }

    /** Reseller application: a lead for sales, and — when the applicant is signed in — a partner record awaiting approval. */
    public function resellerApply(array $input, CommandContext $context, ?Organization $organization = null): array
    {
        $lead = $this->lead(['kind' => 'reseller', 'meta' => ['clients' => $input['clients'] ?? null, 'site' => $input['site'] ?? null, 'model' => $input['model'] ?? 'share']] + $input, $context, $organization);
        $partner = $organization === null ? null : $this->partners->apply($organization, ['company' => $input['company'] ?? $organization->name, 'clients' => $input['clients'] ?? null, 'site' => $input['site'] ?? null, 'note' => $input['message'] ?? null, 'model' => $input['model'] ?? 'share'], $context);

        return ['lead' => $lead, 'partner' => $partner];
    }

    public function transitionLead(Lead $lead, string $state, CommandContext $context, ?string $note = null): Lead
    {
        if (! in_array($state, Lead::STATES, true)) {
            throw new DomainError('lead_state_invalid', 'Unknown lead state.', 422, ['field' => 'state']);
        }
        $lead->forceFill(['state' => $state, 'owner_id' => $lead->owner_id ?? $context->actorId, 'meta' => ($lead->meta ?? []) + ($note ? ['notes' => [...(($lead->meta['notes'] ?? [])), ['at' => now()->toIso8601String(), 'by' => $context->actorId, 'text' => $note]]] : [])])->save();
        $this->audit->record($context, 'lead.transition', 'succeeded', ['state' => $state], 'lead', $lead->id);

        return $lead;
    }

    /**
     * The public blog for the generated data script (I-R17): every published post with its text, newest first, in the shape the
     * prototype's content module renders (`onhost-content.js`: slug, cat, title, excerpt, date, read, author, featured, body).
     *
     * @return list<array<string, mixed>>
     */
    public function blog(string $locale): array
    {
        return $this->publishedPosts('blog')->orderByDesc('published_on')->orderByDesc('created_at')->get()
            ->map(fn (Post $p) => array_replace($this->postSummary($p, $locale), [
                'date' => $p->published_on === null ? '' : ($locale === 'en' ? $p->published_on->format('j M Y') : $p->published_on->format('j. n. Y')),
                'author' => (string) $p->author,
                'body' => array_values(array_map(fn ($section) => [(string) ($section[0] ?? ''), (string) ($section[1] ?? '')], (array) ($p->body[$locale] ?? $p->body['cs'] ?? []))),
            ]))->values()->all();
    }

    /**
     * The customer references the public pages show (I-R18): published, in the editor's order, `{quote, who, role}` in the locale
     * (Czech when the English text is missing). A DEMO row seeded outside production is shown in production only when a member of
     * staff published it there.
     *
     * @return list<array{quote:string, who:string, role:string}>
     */
    public function references(string $locale): array
    {
        $query = ContentReference::query()->where('published', true)->orderBy('sort')->orderBy('created_at');
        if (app()->environment('production')) {
            $query->where(fn ($q) => $q->where('demo', false)->orWhereNotNull('published_by'));
        }

        return $query->get()->map(fn (ContentReference $r) => [
            'quote' => $this->localized($r->quote, $locale), 'who' => (string) $r->who, 'role' => (string) (($r->role ?? [])[$locale] ?? ($r->role ?? [])['cs'] ?? ''),
        ])->filter(fn (array $r) => $r['quote'] !== '' && $r['who'] !== '')->values()->all();
    }

    private function postSummary(Post $p, string $locale): array
    {
        return [
            'slug' => $p->slug, 'cat' => $p->text('category', $locale), 'title' => $p->text('title', $locale), 'excerpt' => $p->text('excerpt', $locale),
            'date' => $p->published_on?->toDateString(), 'read' => "{$p->read_minutes} min", 'author' => $p->author, 'featured' => $p->featured,
        ];
    }

    private function localized(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['cs'] ?? reset($value) ?: '');
        }

        return (string) $value;
    }
}
