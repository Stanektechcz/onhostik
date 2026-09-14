<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Models;

use Onhost\Platform\Eloquent\Model;

/** Zone template with placeholders ({ipv4}, {ipv6}, {domain}, {mail_host}, {dkim}). */
final class DnsTemplate extends Model
{
    protected static string $idPrefix = 'dtp';

    protected $table = 'dns_templates';

    protected function casts(): array
    {
        return ['name' => 'array', 'records' => 'array'];
    }

    /** @param array<string,string|null> $vars @return list<array{name:string,type:string,content:string,ttl:int,prio:int|null,managed_by:string,protected:bool}> */
    public function render(array $vars): array
    {
        $out = [];
        foreach ((array) $this->records as $record) {
            $missing = false;
            $fill = function (string $text) use ($vars, &$missing): string {
                return preg_replace_callback('/\{([a-z0-9_]+)\}/', function ($m) use ($vars, &$missing) {
                    $value = $vars[$m[1]] ?? null;
                    if ($value === null || $value === '') {
                        $missing = true;

                        return '';
                    }

                    return (string) $value;
                }, $text) ?? $text;
            };
            $name = $fill((string) ($record['name'] ?? '@'));
            $content = $fill((string) $record['content']);
            if ($missing) {
                continue; // records whose placeholder has no value (e.g. no IPv6, no DKIM key) are skipped, never emitted empty
            }
            $out[] = ['name' => $name, 'type' => strtoupper((string) $record['type']), 'content' => $content, 'ttl' => (int) ($record['ttl'] ?? 3600), 'prio' => isset($record['prio']) ? (int) $record['prio'] : null, 'managed_by' => (string) ($record['managed_by'] ?? 'system'), 'protected' => (bool) ($record['protected'] ?? in_array(strtoupper((string) $record['type']), ['MX', 'NS', 'SOA'], true))];
        }

        return $out;
    }
}
