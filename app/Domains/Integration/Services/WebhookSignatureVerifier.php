<?php

declare(strict_types=1);

namespace App\Domains\Integration\Services;

use App\Domains\Integration\Models\WebhookEndpoint;
use Illuminate\Http\Request;

final class WebhookSignatureVerifier
{
    public function verify(Request $request, WebhookEndpoint $endpoint): bool
    {
        if (empty($endpoint->secret)) {
            return true; // no secret configured → skip verification
        }

        $incoming = $request->header($endpoint->signature_header);
        if ($incoming === null) {
            return false;
        }

        $body     = $request->getContent();
        $computed = hash_hmac($endpoint->signature_algo, $body, $endpoint->secret);

        // Strip prefix if any (e.g. "sha256=abcdef")
        $incoming = preg_replace('/^sha\d+=/', '', $incoming) ?? $incoming;

        return hash_equals($computed, $incoming);
    }
}
