<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Onhost\Platform\Errors\DomainError;

/** RFC-level validation before a record can be staged (blueprint §48.1, S39). */
final class RecordValidator
{
    public const TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'NS', 'CAA', 'ALIAS', 'PTR', 'TLSA', 'SSHFP', 'HTTPS', 'SVCB'];

    public const MIN_TTL = 60;

    public const MAX_TTL = 604800;

    /** @param array<string,mixed> $record @return array{name:string,type:string,content:string,ttl:int,prio:int|null} */
    public function normalize(array $record, string $zone): array
    {
        $type = strtoupper(trim((string) ($record['type'] ?? '')));
        if (! in_array($type, self::TYPES, true)) {
            throw new DomainError('dns_type_unsupported', "Record type {$type} is not supported.", 422);
        }
        $name = $this->relativeName((string) ($record['name'] ?? '@'), $zone);
        $ttl = (int) ($record['ttl'] ?? 3600);
        if ($ttl < self::MIN_TTL || $ttl > self::MAX_TTL) {
            throw new DomainError('dns_ttl_out_of_range', sprintf('TTL must be between %d and %d seconds.', self::MIN_TTL, self::MAX_TTL), 422);
        }
        $content = trim((string) ($record['content'] ?? ''));
        $prio = isset($record['prio']) && $record['prio'] !== '' && $record['prio'] !== null ? (int) $record['prio'] : null;
        if ($content === '') {
            throw new DomainError('dns_content_required', 'Record content is required.', 422);
        }

        switch ($type) {
            case 'A':
                if (filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    throw new DomainError('dns_invalid_ipv4', "{$content} is not a valid IPv4 address.", 422);
                }
                break;
            case 'AAAA':
                if (filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                    throw new DomainError('dns_invalid_ipv6', "{$content} is not a valid IPv6 address.", 422);
                }
                break;
            case 'CNAME':
            case 'NS':
            case 'ALIAS':
            case 'PTR':
                $content = $this->fqdn($content);
                if ($type === 'CNAME' && $name === '@') {
                    throw new DomainError('dns_cname_apex', 'CNAME cannot be used at the zone apex; use ALIAS.', 422);
                }
                break;
            case 'MX':
                $content = $this->fqdn($content);
                $prio ??= 10;
                if ($prio < 0 || $prio > 65535) {
                    throw new DomainError('dns_invalid_priority', 'MX priority must be 0–65535.', 422);
                }
                break;
            case 'SRV':
                if (! preg_match('/^\d{1,5}\s+\d{1,5}\s+\S+$/', $content)) {
                    throw new DomainError('dns_invalid_srv', 'SRV content must be "weight port target".', 422);
                }
                $prio ??= 0;
                if (! str_starts_with($name, '_')) {
                    throw new DomainError('dns_invalid_srv_name', 'SRV names must start with _service._proto.', 422);
                }
                break;
            case 'TXT':
                if (strlen($content) > 4096) {
                    throw new DomainError('dns_txt_too_long', 'TXT content exceeds 4096 characters.', 422);
                }
                $content = trim($content, '"');
                break;
            case 'CAA':
                if (! preg_match('/^\d{1,3}\s+(issue|issuewild|iodef)\s+"[^"]*"$/', $content)) {
                    throw new DomainError('dns_invalid_caa', 'CAA content must be "<flags> issue|issuewild|iodef \"value\"".', 422);
                }
                break;
            case 'TLSA':
            case 'SSHFP':
            case 'HTTPS':
            case 'SVCB':
                if (! preg_match('/^\d+\s+/', $content)) {
                    throw new DomainError('dns_invalid_content', "{$type} content must start with numeric fields.", 422);
                }
                break;
        }
        if ($type !== 'MX' && $type !== 'SRV') {
            $prio = null;
        }

        return ['name' => $name, 'type' => $type, 'content' => $content, 'ttl' => $ttl, 'prio' => $prio];
    }

    /** Conflicts inside the resulting record set (CNAME exclusivity, duplicate rows). @param list<array<string,mixed>> $records */
    public function assertConsistent(array $records): void
    {
        $byName = [];
        foreach ($records as $r) {
            $byName[strtolower($r['name'])][] = $r;
        }
        foreach ($byName as $name => $set) {
            $types = array_unique(array_column($set, 'type'));
            if (in_array('CNAME', $types, true) && count($types) > 1) {
                throw new DomainError('dns_cname_conflict', "{$name} has a CNAME and other record types; CNAME must be alone.", 422);
            }
            $seen = [];
            foreach ($set as $r) {
                $key = $r['type'].'|'.strtolower($r['content']).'|'.($r['prio'] ?? '');
                if (isset($seen[$key])) {
                    throw new DomainError('dns_duplicate_record', "Duplicate {$r['type']} record for {$name}.", 422);
                }
                $seen[$key] = true;
            }
        }
    }

    public function relativeName(string $name, string $zone): string
    {
        $name = strtolower(trim($name));
        $zone = strtolower(rtrim($zone, '.'));
        if ($name === '' || $name === '@' || $name === $zone || $name === $zone.'.') {
            return '@';
        }
        if (str_ends_with($name, '.'.$zone.'.')) {
            $name = substr($name, 0, -strlen('.'.$zone.'.'));
        } elseif (str_ends_with($name, '.'.$zone)) {
            $name = substr($name, 0, -strlen('.'.$zone));
        }
        if (! preg_match('/^(\*\.)?([a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?\.)*[a-z0-9_*](?:[a-z0-9_-]{0,61}[a-z0-9_])?$/', $name)) {
            throw new DomainError('dns_invalid_name', "{$name} is not a valid record name.", 422);
        }

        return $name;
    }

    private function fqdn(string $value): string
    {
        $value = strtolower(rtrim(trim($value), '.'));
        if (! preg_match('/^([a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?\.)+[a-z0-9]{2,63}$|^\.$/', $value) && $value !== '') {
            throw new DomainError('dns_invalid_hostname', "{$value} is not a valid hostname.", 422);
        }

        return $value.'.';
    }
}
