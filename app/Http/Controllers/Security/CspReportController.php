<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Collects Content-Security-Policy violation reports (audit C24).
 *
 * Report-only mode is only useful if the reports land somewhere: the point is
 * to learn what enforce WOULD break before flipping the switch.
 *
 * The body is attacker-controlled — any origin can POST here, and browsers
 * send it unauthenticated by design. So: no auth (a violation report from a
 * logged-out page still matters), a hard size cap, only known fields are read,
 * and every value is truncated before it reaches the log. Always answers 204
 * so a browser never retries.
 */
class CspReportController extends Controller
{
    /** Bytes of request body we are willing to parse. */
    private const MAX_BYTES = 16_384;

    /** Characters kept per reported field. */
    private const MAX_FIELD = 400;

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();

        if ($raw === '' || strlen($raw) > self::MAX_BYTES) {
            return response()->noContent();
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return response()->noContent();
        }

        foreach ($this->extractReports($decoded) as $report) {
            Log::warning('csp.violation', [
                'directive'    => $this->field($report, 'effective-directive', 'effectiveDirective'),
                'blocked_uri'  => $this->field($report, 'blocked-uri', 'blockedURL'),
                'document_uri' => $this->field($report, 'document-uri', 'documentURL'),
                'disposition'  => $this->field($report, 'disposition'),
                // Deliberately NOT logging `script-sample`: it echoes page
                // content back, which can include whatever the user typed.
            ]);
        }

        return response()->noContent();
    }

    /**
     * Both wire formats: the legacy `{"csp-report": {...}}` and the
     * Reporting-API array `[{"type":"csp-violation","body":{...}}, …]`.
     *
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function extractReports(array $decoded): array
    {
        if (isset($decoded['csp-report']) && is_array($decoded['csp-report'])) {
            return [$decoded['csp-report']];
        }

        $out = [];

        foreach ($decoded as $entry) {
            if (is_array($entry) && isset($entry['body']) && is_array($entry['body'])) {
                $out[] = $entry['body'];
            }
        }

        // Cap the batch — a hostile client could post thousands in one body.
        return array_slice($out, 0, 20);
    }

    /** @param array<string, mixed> $report */
    private function field(array $report, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $report[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, self::MAX_FIELD);
            }
        }

        return null;
    }
}
