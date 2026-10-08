<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\ConsentDocument;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * The texts behind `consent_documents` and the owner's step that puts a prepared version in force (TASK-0142).
 *
 * * Text of a version: `resources/legal/<version>/<key>.md`. Versions older than versioned folders (2026-09) keep their text in
 *   `resources/legal/<key>.md`; once a version has its folder, a key missing from it has no text (never the older one).
 * * A version is prepared as a draft (LegalEntitySeeder) and published only by the owner after an attorney confirmed it (owner
 *   decision I-R4/4A): `php artisan onhost:legal:publish <version>` shows what would happen, `--apply` does it. Publishing freezes
 *   the text's hash, sets the day it takes effect (never sooner than the notice the documents promise to existing customers), closes
 *   the older version on that day and is audited. L-24 (TASK-0146): publishing also tells every customer who accepted a document
 *   that changes (`legal.document.changed`: a mandatory mail and a panel notice) — at that moment, which is at least the notice
 *   period before the version applies (VOP čl. 15, § 1752 OZ).
 */
final class LegalDocuments
{
    /** Days of notice before a new version of a document that customers already accepted takes effect (VOP čl. 15, privacy čl. 9). */
    public const NOTICE_DAYS = ['privacy' => 14];

    public const DEFAULT_NOTICE_DAYS = 30;

    /** Placeholders a text may use; LegalDocumentController fills them. */
    public const PLACEHOLDERS = ['entity_name', 'entity_ico', 'entity_dic', 'entity_address', 'entity_email', 'entity_phone', 'entity_registry', 'portal', 'version', 'effective_from'];

    private const VERSION_PATTERN = '/^\d{4}-\d{2}(-[a-z0-9]+)?$/';

    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /** The Markdown file of one version of a document, or null when it has none (an external document, a key the version lacks). */
    public static function path(string $key, string $version): ?string
    {
        if (preg_match('/^[a-z0-9_]+$/', $key) !== 1 || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            return null;
        }
        $folder = resource_path('legal/'.$version);
        $file = is_dir($folder) ? $folder.'/'.$key.'.md' : resource_path('legal/'.$key.'.md');

        return is_file($file) ? $file : null;
    }

    /** The text with line endings normalised, so a checkout on Windows and one on Linux hash the same text the same. */
    public static function text(string $key, string $version): ?string
    {
        $path = self::path($key, $version);

        return $path === null ? null : str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    public static function hash(string $key, string $version): ?string
    {
        $text = self::text($key, $version);

        return $text === null ? null : hash('sha256', $text);
    }

    /** @return list<ConsentDocument> */
    public function drafts(string $version): array
    {
        return ConsentDocument::query()->where('version', $version)->where('state', ConsentDocument::DRAFT)->orderBy('key')->get()->all();
    }

    /**
     * What stands in the way of publishing a version on that day. Empty = it may be published.
     *
     * @return list<string>
     */
    public function blockers(string $version, CarbonImmutable $effectiveFrom, string $approvedBy): array
    {
        $drafts = $this->drafts($version);
        if ($drafts === []) {
            return ["version {$version} has no draft to publish"];
        }
        $out = [];
        if (trim($approvedBy) === '') {
            $out[] = 'name who confirmed the texts (--approved-by): the owner publishes only what an attorney confirmed (I-R4)';
        }
        foreach ($drafts as $draft) {
            $key = (string) $draft->key;
            $text = self::text($key, $version);
            if ($text === null) {
                $out[] = "{$key}: no text at resources/legal/{$version}/{$key}.md";

                continue;
            }
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $found);
            foreach (array_diff(array_unique($found[1]), self::PLACEHOLDERS) as $unknown) {
                $out[] = "{$key}: unknown placeholder {{{$unknown}}}";
            }
            $notice = ConsentDocument::current($key) === null ? 0 : (self::NOTICE_DAYS[$key] ?? self::DEFAULT_NOTICE_DAYS);
            $zone = (string) config('onhost.billing.timezone', 'Europe/Prague'); // days are the seller's days, whatever zone the servers run in
            $earliest = CarbonImmutable::now($zone)->startOfDay()->addDays($notice);
            if ($effectiveFrom->lessThan($earliest)) {
                $out[] = "{$key}: takes effect {$effectiveFrom->setTimezone($zone)->toDateString()}, but customers who accepted the version in force must be told {$notice} days ahead — {$earliest->toDateString()} at the earliest";
            }
        }
        $entity = LegalEntity::query()->where('key', (string) config('onhost.billing.legal_entity', 'onhost-cz'))->first();
        if ($entity === null || in_array((string) $entity->ico, ['', '00000000'], true)) {
            $out[] = 'the legal entity still carries placeholder identifiers (onhost:production:prepare --legal)';
        }
        if (trim((string) config('onhost.legal_entity.phone', '')) === '') {
            $out[] = 'ONHOST_LEGAL_PHONE is empty: a consumer must be given a telephone number before the contract (§ 1820 OZ)';
        }
        // L-04: withdrawals and complaints go to {{entity_email}}; the no-reply sender (the fallback when ONHOST_LEGAL_EMAIL is empty) reads nothing
        $email = trim((string) config('onhost.legal_entity.email', '')) !== '' ? trim((string) config('onhost.legal_entity.email')) : trim((string) config('mail.from.address', ''));
        if ($email === '' || preg_match('/^(no-?reply|do-?not-?reply)@/i', $email) === 1) {
            $out[] = 'ONHOST_LEGAL_EMAIL is empty or a no-reply address: a consumer must be able to send a withdrawal and a complaint to a monitored mailbox';
        }

        return $out;
    }

    /**
     * Puts every draft of the version in force from `$effectiveFrom`: frozen hash of the text as it is now, the previous version
     * of each document closed on that day, one audit row per document. The caller checked `blockers()` first.
     *
     * @return list<array{key:string, version:string, effective_from:string, hash:string, closed:list<string>}>
     */
    public function publish(string $version, CarbonImmutable $effectiveFrom, string $approvedBy, CommandContext $context): array
    {
        return DB::transaction(function () use ($version, $effectiveFrom, $approvedBy, $context): array {
            $done = [];
            $documents = [];
            foreach ($this->drafts($version) as $draft) {
                $key = (string) $draft->key;
                $title = (array) (is_string($draft->title) ? json_decode($draft->title, true) : $draft->title);
                $documents[] = ['key' => $key, 'title' => (string) ($title['cs'] ?? $key), 'title_en' => (string) ($title['en'] ?? $title['cs'] ?? $key),
                    'url' => rtrim((string) ($draft->url ?? ''), '/').'/'.$version, 'changed' => ConsentDocument::current($key) !== null];
                $hash = (string) self::hash($key, $version);
                $closed = ConsentDocument::query()->where('key', $key)->where('state', ConsentDocument::ACTIVE)->where('version', '!=', $version)
                    ->where('effective_from', '<', $effectiveFrom)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom))
                    ->pluck('version')->map(fn ($v) => (string) $v)->all();
                if ($closed !== []) {
                    ConsentDocument::query()->where('key', $key)->whereIn('version', $closed)->update(['effective_to' => $effectiveFrom]);
                }
                ConsentDocument::query()->where('key', $key)->where('version', $version)->update([
                    'state' => ConsentDocument::ACTIVE, 'effective_from' => $effectiveFrom, 'hash' => $hash, 'published_at' => now(), 'published_by' => mb_substr(trim($approvedBy), 0, 160),
                ]);
                $this->audit->record($context, 'legal.document.published', 'succeeded', ['key' => $key, 'version' => $version, 'effective_from' => $effectiveFrom->toIso8601String(), 'hash' => $hash, 'closed' => $closed, 'approved_by' => mb_substr(trim($approvedBy), 0, 160)], 'consent_document', $key.'@'.$version);
                $done[] = ['key' => $key, 'version' => $version, 'effective_from' => $effectiveFrom->setTimezone((string) config('onhost.billing.timezone', 'Europe/Prague'))->toDateString(), 'hash' => $hash, 'closed' => $closed];
            }
            $told = $this->announce($version, $effectiveFrom, $documents, $context);

            return array_map(fn (array $row) => $row + ['told' => $told], $done);
        });
    }

    /**
     * L-24: one `legal.document.changed` per organization that accepted any document this version changes (a document new in the
     * version binds only those who accept it later, so it alone tells nobody). The event lists every document of the version.
     *
     * @param  list<array{key:string, title:string, title_en:string, url:string, changed:bool}>  $documents
     */
    private function announce(string $version, CarbonImmutable $effectiveFrom, array $documents, CommandContext $context): int
    {
        $changed = array_values(array_map(fn (array $d) => $d['key'], array_filter($documents, fn (array $d) => $d['changed'])));
        if ($changed === []) {
            return 0;
        }
        $notice = max(array_map(fn (string $key) => self::NOTICE_DAYS[$key] ?? self::DEFAULT_NOTICE_DAYS, $changed));
        $day = $effectiveFrom->setTimezone((string) config('onhost.billing.timezone', 'Europe/Prague'))->toDateString();
        $organizations = Consent::query()->whereIn('document_key', $changed)->whereNotNull('organization_id')->distinct()->orderBy('organization_id')->pluck('organization_id');
        foreach ($organizations as $organizationId) {
            $this->outbox->publish(GenericEvent::of('legal.document.changed', 'legal_version', $version, ['version' => $version, 'effective_from' => $day, 'notice_days' => $notice, 'documents' => $documents], (string) $organizationId));
        }
        $this->audit->record($context, 'legal.document.announced', 'succeeded', ['version' => $version, 'effective_from' => $day, 'organizations' => $organizations->count(), 'changed' => $changed], 'consent_document', 'version@'.$version);

        return $organizations->count();
    }
}
