<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

use Onhost\Domain\Support\Assistant\SecretMask;
use Onhost\Platform\Redaction\Redactor;

/**
 * The report of the provider-calls audit, as JSON (toArray) or Markdown.
 *
 * Only whitelisted fields leave the audit — ids, functions, verdicts, times — and never a logged request or answer:
 * rows written before the H12 masks may still hold a password or a key. A name keeps its service prefix and loses
 *
 * the rest (`oh1yz8n6_s***`), an address keeps its domain (`i***@other.cz`), a planted SSH key appears only as its
 * fingerprint, and every string then passes Redactor and SecretMask.
 */
final class ProviderCallsAuditReport
{
    public const SEVERITIES = ['CRITICAL', 'HIGH', 'MEDIUM', 'REVIEW', 'INFO', 'CLEAN'];

    private const METHOD = [
        'Read-only: built from provider_calls, operations, operation_attempts, services, provider_bindings and mail_domains; no panel was called and nothing was written.',
        'ISPConfig only: aaPanel resolves every id against its own site listing and was clean (security-boundaries.md).',
        'ISPConfig calls carry no operation id: a write is tied to an operation by the time its attempts ran, the same function and the same id.',
        'Accepted means the panel answered body code `ok`; HTTP 200 alone is not acceptance (ISPConfig refuses inside a 200).',
        'Owner evidence comes from listings, reads and creations the platform logged, of any age; UNKNOWN means none exists — look the record up in sys_datalog.',
    ];

    /**
     * @param  array<string,mixed>  $meta
     * @param  array<string,int>  $summary
     * @param  list<array<string,mixed>>  $actions
     * @param  list<array<string,mixed>>  $probes
     * @param  list<array<string,mixed>>  $unattributed
     * @param  list<array<string,mixed>>  $coverage
     */
    public function __construct(
        public readonly array $meta,
        public readonly array $summary,
        public readonly array $actions,
        public readonly array $probes,
        public readonly array $unattributed,
        public readonly array $coverage,
    ) {}

    /** Something the operator must act on: a CRITICAL or HIGH row in any section. */
    public function hasFindings(): bool
    {
        return ($this->summary['CRITICAL'] ?? 0) + ($this->summary['HIGH'] ?? 0) > 0;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return self::clean([
            'meta' => $this->meta + ['read_only' => true, 'method' => self::METHOD],
            'summary' => $this->summary,
            'actions' => $this->actions,
            'probes' => $this->probes,
            'unattributed' => $this->unattributed,
            'coverage' => $this->coverage,
        ], new SecretMask(new Redactor));
    }

    public function toMarkdown(): string
    {
        $data = $this->toArray();
        $lines = ['# Provider calls audit (ISPConfig ownership)', '', 'Window: '.$data['meta']['since'].' – '.$data['meta']['until'].' · instances: '.implode(', ', $data['meta']['instances']).' · generated '.$data['meta']['generated_at'], ''];
        foreach ($data['meta']['method'] as $line) {
            $lines[] = '- '.$line;
        }
        $lines = array_merge($lines, ['', '## Summary', '', self::table(['severity', 'rows'], array_map(fn (string $s) => [$s, $data['summary'][$s] ?? 0], self::SEVERITIES))]);
        $lines = array_merge($lines, ['', '## Coverage', '', self::table(['instance', 'oldest', 'newest', 'calls', 'unreadable', 'warning'], array_map(fn (array $c) => [$c['instance_key'], $c['oldest'], $c['newest'], $c['calls'], $c['unparseable'], $c['warning']], $data['coverage']))]);
        $lines = array_merge($lines, ['', '## Actions on a named record', '', self::table(
            ['severity', 'verdict', 'operation', 'action', 'service', 'organization', 'actor', 'at', 'instance', 'target', 'sent', 'calls', 'owner service', 'owner organization', 'owner', 'evidence', 'sys_datalog', 'key', 'notes'],
            array_map(fn (array $r) => [$r['severity'], $r['verdict'], $r['operation_id'], $r['action'], $r['service_id'], $r['organization_id'], $r['actor'], $r['created_at'], $r['instance_key'], $r['target_kind'].' '.$r['target'], $r['sent'], $r['provider_call_ids'], $r['owner_service_id'], $r['owner_organization_id'], self::ownerOf($r), $r['evidence_call_ids'], $r['sys_datalog'], $r['key_fingerprint'], $r['notes']], $data['actions']),
        )]);
        $lines = array_merge($lines, ['', '## Refusals after the fix (probing)', '', self::table(
            ['severity', 'actor', 'count', 'consecutive', 'targets', 'organizations', 'operations', 'first', 'last'],
            array_map(fn (array $p) => [$p['severity'], $p['actor'], $p['count'], $p['consecutive'] ? 'yes' : 'no', $p['targets'], $p['organization_ids'], $p['operation_ids'], $p['first_at'], $p['last_at']], $data['probes']),
        )]);
        $lines = array_merge($lines, ['', '## Writes no operation explains', '', self::table(
            ['severity', 'verdict', 'call', 'instance', 'function', 'id', 'body code', 'at', 'owner service', 'owner', 'evidence', 'sys_datalog', 'nearest operation'],
            array_map(fn (array $u) => [$u['severity'], $u['verdict'], $u['provider_call_id'], $u['instance_key'], $u['function'], $u['primary_id'], $u['body_code'], $u['created_at'], $u['owner_service_id'], self::ownerOf($u), $u['evidence_call_ids'], $u['sys_datalog'], $u['nearest_operation'] === null ? null : implode(' ', array_filter($u['nearest_operation']))], $data['unattributed']),
        )]);

        return implode("\n", $lines)."\n";
    }

    /** @param array<string,mixed> $row */
    private static function ownerOf(array $row): string
    {
        return implode(' ', array_filter([$row['owner_site'] === null ? null : 'site '.$row['owner_site'], $row['owner_name'], $row['owner_address']], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * @param  list<string>  $head
     * @param  list<list<mixed>>  $rows
     */
    private static function table(array $head, array $rows): string
    {
        if ($rows === []) {
            return '_none_';
        }
        $cell = fn (mixed $v): string => str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], is_array($v) ? implode(', ', array_map('strval', $v)) : (string) ($v ?? '—'));
        $out = ['| '.implode(' | ', $head).' |', '|'.str_repeat(' --- |', count($head))];
        foreach ($rows as $row) {
            $out[] = '| '.implode(' | ', array_map($cell, $row)).' |';
        }

        return implode("\n", $out);
    }

    private static function clean(mixed $value, SecretMask $mask, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $item) {
                $out[$k] = self::clean($item, $mask, is_string($k) ? $k : $key);
            }

            return $out;
        }
        if (! is_string($value)) {
            return $value;
        }
        $value = match (true) {
            $key === 'owner_name' => self::maskName($value),
            preg_match('/^[^@\s]+@[^@\s]+$/', $value) === 1 => self::maskAddress($value),
            default => $value,
        };

        return $mask->text($value);
    }

    /** `oh1yz8n6_shop` → `oh1yz8n6_s***`; a name without a platform prefix keeps its first character only. */
    public static function maskName(string $name): string
    {
        if (preg_match('/^((?:c\d+)?oh[a-z0-9]{1,6}_)(.?)/i', $name, $m) === 1) {
            return $m[1].$m[2].'***';
        }

        return mb_substr($name, 0, 1).'***';
    }

    /** `info@other.cz` → `i***@other.cz` */
    public static function maskAddress(string $address): string
    {
        [$local, $domain] = explode('@', $address, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
