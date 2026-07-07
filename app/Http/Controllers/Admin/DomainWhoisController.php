<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DomainWhoisController extends Controller
{
    public function index(): View
    {
        return view('admin.domain-whois.index');
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);

        $domain = strtolower($validated['domain']);

        $data = Cache::remember('whois.' . $domain, 3600, function () use ($domain): array {
            return $this->queryWhois($domain);
        });

        return response()->json($data);
    }

    /** @return array<string,mixed> */
    private function queryWhois(string $domain): array
    {
        $socket = @fsockopen('whois.iana.org', 43, $errno, $errstr, 5);
        if ($socket === false) {
            return ['domain' => $domain, 'error' => 'WHOIS server nedostupný.'];
        }

        fwrite($socket, $domain . "\r\n");
        $raw = '';
        while (!feof($socket)) {
            $raw .= fread($socket, 1024);
        }
        fclose($socket);

        return ['domain' => $domain, 'raw' => $raw, 'queried_at' => now()->toIso8601String()];
    }
}
