<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One vulnerability as the feeds describe it, merged across feeds by its CVE (`key`): what is affected (`affected`, a list of
 * `{source, ecosystem, product, introduced?, fixed?, last_affected?, versions?}`), how bad it is and whether it is exploited now
 * (`kev`, CISA's Known Exploited Vulnerabilities). Text from a feed is untrusted: it is stored and shown as plain text, never acted on.
 *
 * @property list<string> $sources
 * @property list<string>|null $aliases
 * @property list<array<string,mixed>>|null $affected
 * @property list<string>|null $references
 * @property ?Carbon $published_at
 * @property ?Carbon $source_modified_at
 * @property ?Carbon $kev_added_on
 * @property ?float $cvss
 */
final class Advisory extends Model
{
    public const SEVERITIES = ['critical', 'high', 'medium', 'low', 'unknown'];

    protected static string $idPrefix = 'adv';

    protected $table = 'security_advisories';

    protected function casts(): array
    {
        return ['sources' => 'array', 'aliases' => 'array', 'affected' => 'array', 'references' => 'array', 'kev' => 'boolean', 'cvss' => 'float', 'kev_added_on' => 'date', 'published_at' => 'datetime', 'source_modified_at' => 'datetime'];
    }

    /** Critical, or exploited in the wild: the case is hot and the customer is told at once. */
    public function urgent(): bool
    {
        return $this->kev || $this->severity === 'critical';
    }

    /** @return array<string,mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id, 'key' => $this->key, 'sources' => $this->sources, 'aliases' => $this->aliases ?? [], 'title' => $this->title, 'summary' => $this->summary,
            'severity' => $this->severity, 'cvss' => $this->cvss, 'kev' => $this->kev, 'kev_added_on' => $this->kev_added_on?->toDateString(),
            'affected' => array_map(fn (array $a) => array_intersect_key($a, array_flip(['source', 'ecosystem', 'product', 'introduced', 'fixed', 'last_affected'])), $this->affected ?? []),
            'references' => array_values(array_filter($this->references ?? [], fn ($u) => is_string($u) && str_starts_with($u, 'https://'))),
            'published_at' => $this->published_at?->toIso8601String(), 'modified_at' => $this->source_modified_at?->toIso8601String(),
        ];
    }
}
