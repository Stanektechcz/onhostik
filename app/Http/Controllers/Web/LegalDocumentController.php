<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\Models\ConsentDocument;

/**
 * Public legal documents (`/dokumenty/{slug}`, `/sla`): the versioned consent documents the checkout, the domain
 * registration and the panel link to. The text lives in `resources/legal/<key>.md` (Markdown with `{{entity_*}}`
 * placeholders filled from the legal entity), the version and effective date come from `consent_documents`, so a
 * customer always reads exactly the version they accepted.
 */
final class LegalDocumentController extends Controller
{
    /** URL slug → consent document key. */
    public const SLUGS = [
        'vop' => 'terms', 'ochrana-osobnich-udaju' => 'privacy', 'dpa' => 'dpa', 'sla' => 'sla', 'odstoupeni' => 'withdrawal_waiver',
        'obnovovani' => 'auto_renew', 'podminky-registrace-domen' => 'registrar_terms',
    ];

    public function index(Request $request): View
    {
        $documents = [];
        foreach (self::SLUGS as $slug => $key) {
            $document = ConsentDocument::current($key);
            if ($document !== null && is_file($this->path($key))) {
                $documents[] = ['slug' => $slug, 'key' => $key, 'title' => $this->title($document, $request), 'version' => $document->version, 'effective_from' => $document->effective_from];
            }
        }

        return view('legal.index', ['documents' => $documents, 'entity' => $this->entity(), 'stylesheet' => $this->stylesheet()]);
    }

    public function show(Request $request, string $slug): View
    {
        $key = self::SLUGS[$slug] ?? null;
        $document = $key === null ? null : ConsentDocument::current($key);
        if ($key === null || $document === null || ! is_file($this->path($key))) {
            abort(404);
        }
        $entity = $this->entity();
        $markdown = (string) file_get_contents($this->path($key));
        $markdown = strtr($markdown, [
            '{{entity_name}}' => (string) ($entity?->name ?? 'ONhost'), '{{entity_ico}}' => (string) ($entity?->ico ?? ''), '{{entity_dic}}' => (string) ($entity?->dic ?? ''),
            '{{entity_address}}' => $entity === null ? '' : trim(implode(', ', array_filter([(string) ($entity->address['street'] ?? ''), trim(((string) ($entity->address['postal_code'] ?? '')).' '.((string) ($entity->address['city'] ?? '')))]))),
            '{{entity_email}}' => (string) config('mail.from.address', ''), '{{portal}}' => rtrim((string) config('app.url'), '/'), '{{version}}' => (string) $document->version,
            '{{effective_from}}' => $document->effective_from?->format('j. n. Y') ?? '',
        ]);
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $others = [];
        foreach (self::SLUGS as $otherSlug => $otherKey) {
            $other = ConsentDocument::current($otherKey);
            if ($other !== null && $otherKey !== $key && is_file($this->path($otherKey))) {
                $others[] = ['slug' => $otherSlug, 'title' => $this->title($other, $request)];
            }
        }

        return view('legal.document', [
            'slug' => $slug, 'document' => $document, 'title' => $this->title($document, $request), 'html' => $html, 'others' => $others,
            'entity' => $entity, 'stylesheet' => $this->stylesheet(),
        ]);
    }

    private function title(ConsentDocument $document, Request $request): string
    {
        $titles = (array) $document->title;
        $locale = $request->query('locale') === 'en' ? 'en' : 'cs';

        return (string) ($titles[$locale] ?? $titles['cs'] ?? $document->key);
    }

    private function path(string $key): string
    {
        return resource_path('legal/'.$key.'.md');
    }

    private function entity(): ?LegalEntity
    {
        return LegalEntity::query()->where('key', (string) config('onhost.legal_entity', 'onhost-cz'))->first() ?? LegalEntity::query()->orderBy('key')->first();
    }

    private function stylesheet(): ?string
    {
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1));
    }
}
