<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;

/**
 * The graphical console of a customer's own server (Phase C, C9): one page with noVNC, next to the panel, no prototype
 * seam (the surfaces stay byte-identical; the workbench only opens this page).
 *
 * The page carries nothing secret. In the browser it asks `POST /v1/services/{id}/console-token` — the same bus command,
 * permission (`service.console` on the service) and audit as every console — and opens the websocket RELAY with the
 * single-use token it gets back; the relay resolves the token server-side and holds the hypervisor's ticket. The one-time
 * VNC password Proxmox generates for the session travels in that answer and is worthless without the relay's ticket.
 *
 * noVNC is loaded as an ES module from jsDelivr (pinned release), so the page sends a CSP of its own: scripts only from
 * itself with a nonce and that one CDN, no `unsafe-eval`, websocket connections only to the relay. A person who may not
 * open the console of this service gets the same 404 as for a service that does not exist.
 */
final class ServiceConsoleController extends Controller
{
    /** The pinned noVNC release (MPL-2.0, docs/oss-inventory); `core/rfb.js` and its relative imports are ES modules. */
    public const NOVNC = 'https://cdn.jsdelivr.net/gh/novnc/noVNC@v1.5.0/core/rfb.js';

    public function __construct(private readonly Authorizer $authorizer) {}

    public function show(Request $request, string $service): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $model = Service::query()->find($service);
        $scope = $model === null ? null : CommandScope::resource($model->id, (string) $model->organization_id, is_string($model->project_id) && $model->project_id !== '' ? $model->project_id : null);
        abort_unless($model !== null && $model->family === 'cloud' && $this->authorizer->can($user, 'service.console', $scope), 404);
        $nonce = base64_encode(random_bytes(18));
        $relay = rtrim((string) config('onhost.console.relay_url', ''), '/');

        return response()->view('service-console', [
            'service' => $model, 'nonce' => $nonce, 'novnc' => self::NOVNC, 'csrf' => csrf_token(),
            'config' => ['service' => $model->id, 'organization' => $model->organization_id, 'relay' => $relay !== ''],
        ])->header('Content-Security-Policy', self::policy($nonce, $relay))->header('Cache-Control', 'no-store');
    }

    /** The page's own policy: SecurityHeaders keeps a CSP a response already has. */
    public static function policy(string $nonce, string $relay): string
    {
        $connect = ["'self'"];
        if ($relay !== '' && preg_match('~^(https?|wss?)://[^/\s;]+~i', $relay, $m)) {
            $origin = $m[0];
            $connect[] = preg_replace('~^http~i', 'ws', $origin); // the websocket itself
            $connect[] = preg_replace('~^ws~i', 'http', $origin);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            'connect-src '.implode(' ', array_unique($connect)),
            "frame-ancestors 'self'",
            "base-uri 'none'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
