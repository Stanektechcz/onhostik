<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public API changelog + version lifecycle (audit 103).
 *
 * An integrator needs to answer two questions without opening a support ticket:
 * "what changed?" and "how long will the version I built against keep working?".
 * This endpoint answers both — the changelog entries and, per version, its
 * status and sunset date. No auth: it is documentation, and the `Deprecation`
 * headers on live responses point here.
 */
final class ChangelogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var array<string, array{status?: string, sunset?: string|null}> $versions */
        $versions = config('api.versions', []);

        $lifecycle = [];

        foreach ($versions as $name => $meta) {
            $lifecycle[] = [
                'version' => $name,
                'status'  => $meta['status'] ?? 'active',
                'sunset'  => $meta['sunset'] ?? null,
            ];
        }

        return response()->json([
            'versions'  => $lifecycle,
            'changelog' => config('api.changelog', []),
        ]);
    }
}
