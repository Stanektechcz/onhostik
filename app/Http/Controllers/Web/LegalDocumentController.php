<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Domain\Orders\Models\ConsentDocument;

/**
 * Public legal documents (`/dokumenty/{slug}`, `/dokumenty/{slug}/{version}`, `/sla`): the versioned consent documents the
 * checkout, the domain registration and the panel link to. The text of a version lives in `resources/legal/<version>/<key>.md`
 * (older versions in `resources/legal/<key>.md`, LegalDocuments::path) as Markdown with `{{entity_*}}` placeholders filled from the
 * legal entity; the version and effective date come from `consent_documents`, so a customer reads exactly the version they
 * accepted — the one in force, or an earlier one at its own address (VOP: "Aktuální i předchozí verze"). A draft (TASK-0142) is
 * never served.
 */
final class LegalDocumentController extends Controller
{
    /** URL slug → consent document key. A key without a published version is simply not listed (TASK-0142 drafts). */
    public const SLUGS = [
        'vop' => 'terms', 'ochrana-osobnich-udaju' => 'privacy', 'dpa' => 'dpa', 'sla' => 'sla', 'odstoupeni' => 'withdrawal_waiver',
        'obnovovani' => 'auto_renew', 'podminky-registrace-domen' => 'registrar_terms', 'reklamacni-rad' => 'complaints',
        'zasady-uzivani' => 'aup',
    ];

    public function index(Request $request): View
    {
        $documents = [];
        foreach (self::SLUGS as $slug => $key) {
            $document = ConsentDocument::current($key);
            if ($document !== null && LegalDocuments::path($key, (string) $document->version) !== null) {
                $documents[] = ['slug' => $slug, 'key' => $key, 'title' => $this->title($document, $request), 'version' => $document->version, 'effective_from' => $document->effective_from, 'earlier' => $this->earlier($key, (string) $document->version)];
            }
        }

        return view('legal.index', ['documents' => $documents, 'entity' => $this->entity(), 'stylesheet' => $this->stylesheet()]);
    }

    public function show(Request $request, string $slug): View
    {
        $key = self::SLUGS[$slug] ?? null;

        return $this->render($request, $slug, $key === null ? null : ConsentDocument::current($key));
    }

    /** One published version, in force now or earlier — the text a customer accepted. Drafts and future versions answer 404. */
    public function version(Request $request, string $slug, string $version): View
    {
        $key = self::SLUGS[$slug] ?? null;

        return $this->render($request, $slug, $key === null ? null : ConsentDocument::published($key, $version));
    }

    private function render(Request $request, string $slug, ?ConsentDocument $document): View
    {
        $path = $document === null ? null : LegalDocuments::path((string) $document->key, (string) $document->version);
        if ($document === null || $path === null) {
            abort(404);
        }
        $key = (string) $document->key;
        $entity = $this->entity();
        $html = Str::markdown(strtr((string) file_get_contents($path), $this->placeholders($document, $entity)), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $others = [];
        foreach (self::SLUGS as $otherSlug => $otherKey) {
            $other = ConsentDocument::current($otherKey);
            if ($other !== null && $otherKey !== $key && LegalDocuments::path($otherKey, (string) $other->version) !== null) {
                $others[] = ['slug' => $otherSlug, 'title' => $this->title($other, $request)];
            }
        }
        $current = ConsentDocument::current($key);

        return view('legal.document', [
            'slug' => $slug, 'document' => $document, 'title' => $this->title($document, $request), 'html' => $html, 'others' => $others,
            'entity' => $entity, 'stylesheet' => $this->stylesheet(), 'superseded' => $current !== null && $current->version !== $document->version ? $current : null,
            'earlier' => $this->earlier($key, (string) ($current->version ?? '')),
        ]);
    }

    /** @return array<string,string> */
    private function placeholders(ConsentDocument $document, ?LegalEntity $entity): array
    {
        $email = trim((string) config('onhost.legal_entity.email', '')) !== '' ? (string) config('onhost.legal_entity.email') : (string) config('mail.from.address', '');

        return [
            '{{entity_name}}' => (string) ($entity?->name ?? 'ONhost'), '{{entity_ico}}' => (string) ($entity?->ico ?? ''), '{{entity_dic}}' => (string) ($entity?->dic ?? ''),
            '{{entity_address}}' => $entity === null ? '' : trim(implode(', ', array_filter([(string) ($entity->address['street'] ?? ''), trim(((string) ($entity->address['postal_code'] ?? '')).' '.((string) ($entity->address['city'] ?? '')))]))),
            '{{entity_email}}' => $email, '{{entity_phone}}' => (string) config('onhost.legal_entity.phone', ''), '{{entity_registry}}' => (string) data_get($entity?->meta, 'registry', ''),
            '{{portal}}' => rtrim((string) config('app.url'), '/'), '{{version}}' => (string) $document->version,
            '{{effective_from}}' => $document->effective_from?->format('j. n. Y') ?? '',
        ];
    }

    /** Earlier published versions of a document (newest first), each at its own address. @return list<array{version:string, effective_from:mixed}> */
    private function earlier(string $key, string $currentVersion): array
    {
        return ConsentDocument::query()->where('key', $key)->where('state', ConsentDocument::ACTIVE)->where('version', '!=', $currentVersion)->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')->get()
            ->filter(fn (ConsentDocument $d) => LegalDocuments::path($key, (string) $d->version) !== null)
            ->map(fn (ConsentDocument $d) => ['version' => (string) $d->version, 'effective_from' => $d->effective_from])->values()->all();
    }

    private function title(ConsentDocument $document, Request $request): string
    {
        $titles = (array) $document->title;
        $locale = $request->query('locale') === 'en' ? 'en' : 'cs';

        return (string) ($titles[$locale] ?? $titles['cs'] ?? $document->key);
    }

    private function entity(): ?LegalEntity
    {
        return LegalEntity::query()->where('key', (string) config('onhost.billing.legal_entity', 'onhost-cz'))->first() ?? LegalEntity::query()->orderBy('key')->first();
    }

    private function stylesheet(): ?string
    {
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1));
    }
}
