<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DnsHealthCheckController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain'   => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.\-]+$/'],
            'type'     => ['required', 'in:A,AAAA,CNAME,MX,TXT,NS'],
            'expected' => ['nullable', 'string', 'max:500'],
        ]);

        $domain   = $validated['domain'];
        $type     = $validated['type'];
        $expected = $validated['expected'] ?? null;

        $records = $this->resolve($domain, $type);

        $match = $expected !== null
            ? collect($records)->contains(fn ($r) => str_contains((string) $r, $expected))
            : true;

        return response()->json([
            'domain'  => $domain,
            'type'    => $type,
            'records' => $records,
            'match'   => $match,
        ]);
    }

    /** @return array<string> */
    private function resolve(string $domain, string $type): array
    {
        $typeMap = [
            'A'     => DNS_A,
            'AAAA'  => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX'    => DNS_MX,
            'TXT'   => DNS_TXT,
            'NS'    => DNS_NS,
        ];

        $flag    = $typeMap[$type] ?? DNS_A;
        $results = @dns_get_record($domain, $flag);

        if ($results === false) {
            return [];
        }

        return array_map(fn ($r) => match ($type) {
            'A'     => $r['ip'] ?? '',
            'AAAA'  => $r['ipv6'] ?? '',
            'CNAME' => $r['target'] ?? '',
            'MX'    => ($r['target'] ?? '') . ' (' . ($r['pri'] ?? 0) . ')',
            'TXT'   => implode('', (array) ($r['entries'] ?? [])),
            'NS'    => $r['target'] ?? '',
            default => '',
        }, $results);
    }
}
