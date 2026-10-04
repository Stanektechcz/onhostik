<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Support\PublicApiDocs;
use Illuminate\Http\Response;

/**
 * /dokumentace/api — the API documentation page: first call, headers and limits, webhooks, the index of every error slug and the
 * changelog (all assembled by PublicApiDocs from the code and the configuration), then the self-hosted Redoc rendering of the OpenAPI
 * contract. Nothing here comes from a CDN, and the page's own Content-Security-Policy has no `unsafe-eval` (SecurityHeaders keeps a
 * policy a response already carries; the prototype surfaces need `unsafe-eval` for their in-browser Babel, this page does not).
 */
final class ApiDocsController extends Controller
{
    /** The page's script and style sources: itself only. Redoc's styled-components write <style> elements, hence 'unsafe-inline' for styles and nothing else. */
    public const POLICY = "default-src 'self'; script-src 'self'; worker-src 'self' blob:; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'; object-src 'none'";

    public function show(): Response
    {
        return response()->view('api-docs', [
            'base' => PublicApiDocs::baseUrl(),
            'limits' => PublicApiDocs::limits(),
            'steps' => PublicApiDocs::firstCall(),
            'headers' => PublicApiDocs::headers(),
            'endpoints' => PublicApiDocs::endpoints(),
            'errors' => PublicApiDocs::errorSlugs(),
            'changes' => PublicApiDocs::changelog(),
            'retry' => PublicApiDocs::retrySchedule(),
            'retrySentence' => PublicApiDocs::retrySentence(),
            'envelope' => PublicApiDocs::webhookEnvelope(),
            'families' => PublicApiDocs::eventFamilies(),
            'scopes' => PublicApiDocs::scopes(),
        ])->header('Content-Security-Policy', self::POLICY)->header('Cache-Control', 'public, max-age=300');
    }
}
