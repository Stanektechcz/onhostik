<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Support\SurfaceRenderer;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Platform\Commands\CommandScope;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the five product surfaces (handoff docs-backend-handoff §1) from the pristine prototype files:
 *   /            Onhost.dc.html          public web (hash routes #/stav, #/blog/…)
 *   /panel/…     Onhost-app.dc.html      customer panel
 *   /sprava/…    Onhost-admin.dc.html    staff console
 *   /partner/…   Onhost-partner.dc.html  partner portal
 *   /m/…         Onhost-mobil.dc.html    mobile app shell
 *   /widgets     Onhost-widgets.dc.html  component gallery
 * Server paths map onto the surfaces' hash routes (`/panel/sluzby` → `#/sluzby`, `/stav` → `#/stav`).
 */
final class SurfaceController extends Controller
{
    /** Public marketing paths that map 1:1 onto hash routes of Onhost.dc.html (docs-audit-implementace §route table). */
    public const PUBLIC_PATHS = ['sluzby', 'sluzba', 'ceny', 'ceny-a-sla', 'webhosting', 'gamehosting', 'technika', 'jak-fungujeme', 'blog', 'znalostni-baze', 'napoveda', 'dokumentace', 'api', 'stav', 'zmeny', 'lide', 'reseller', 'verejne-zakazky', 'kosik', 'prihlaseni', 'registrace', 'obnova-hesla'];

    public function __construct(private readonly SurfaceRenderer $renderer, private readonly Authorizer $authorizer) {}

    public function public(Request $request, ?string $path = null): Response
    {
        $hash = $path === null || $path === '' ? null : '#/'.trim($path, '/');

        return $this->surface($request, 'public', $hash);
    }

    public function panel(Request $request, ?string $path = null): Response
    {
        return $this->surface($request, 'panel', $this->hash($path));
    }

    public function admin(Request $request, ?string $path = null): Response
    {
        return $this->surface($request, 'admin', $this->hash($path));
    }

    public function partner(Request $request, ?string $path = null): Response
    {
        return $this->surface($request, 'partner', $this->hash($path));
    }

    public function mobile(Request $request, ?string $path = null): Response
    {
        return $this->surface($request, 'mobile', $this->hash($path));
    }

    public function widgets(Request $request): Response
    {
        return $this->surface($request, 'widgets', null);
    }

    /** Static prototype assets (design system, scripts, images) with long cache; generated scripts have their own routes. */
    public function asset(string $path): BinaryFileResponse|Response
    {
        $file = $this->renderer->assetPath($path);
        if ($file === null) {
            abort(404);
        }
        // The prototype runtime loader points at unpkg; production serves the vendored builds (CSP without third-party script hosts).
        if ($path === 'support.js' && is_file($this->renderer->root().'/vendor/babel.min.js')) {
            $js = str_replace(
                ['https://unpkg.com/react@18.3.1/umd/react.production.min.js', 'https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js', 'https://unpkg.com/@babel/standalone@7.29.0/babel.min.js'],
                ['/surfaces/vendor/react.production.min.js', '/surfaces/vendor/react-dom.production.min.js', '/surfaces/vendor/babel.min.js'],
                (string) file_get_contents($file),
            );

            return response($js, 200, ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
        }
        if ($path === 'onhost-shell.js' && ! config('onhost.ui.demo', false)) { // the prototype's surface switcher ("Plochy Onhost", bottom-left) is a design-review tool, not part of the product
            $js = str_replace('function boot() { if (EMBED) return;', 'function boot() { if (EMBED || (window.ONHOST && !window.ONHOST.demo)) return;', (string) file_get_contents($file));

            return response($js, 200, ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
        }
        if ($path === 'onhost-command.js' && ! config('onhost.ui.demo', false)) { // one assistant only: the drawer ("Asistent", ⌘J) gives way to the panel's chat (seam #30)
            $chat = 'function panelChat() { var b = Array.prototype.slice.call(document.querySelectorAll(\'button\')).filter(function (x) { return /Zeptat se AI|Ask the AI|Zavřít chat|Close chat/.test(x.textContent || \'\'); })[0]; if (b) { b.click(); return true; } return false; }'
                ."\n    var product = !!(window.ONHOST && !window.ONHOST.demo);\n";
            $js = str_replace(
                [
                    "    var dock = el('button', [",
                    '    document.body.appendChild(dock);',
                    "      if (meta && k === 'j') { e.preventDefault(); if (palOpen) setPalOpen(false); openAsk(!askOpen); return; }",
                    '      assistant: openAsk,',
                ],
                [
                    '    '.$chat."    var dock = el('button', [",
                    '    if (!product) document.body.appendChild(dock);',
                    "      if (meta && k === 'j') { e.preventDefault(); if (palOpen) setPalOpen(false); if (product) { panelChat(); return; } openAsk(!askOpen); return; }",
                    '      assistant: function (open) { if (product) { panelChat(); return; } openAsk(open); },',
                ],
                (string) file_get_contents($file),
            );

            return response($js, 200, ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
        }
        if ($path === 'onhost-svc-web.js' && ! config('onhost.ui.demo', false)) { // customer surfaces never name a registrar vendor (seam #20)
            $js = str_replace("'Hetzner', 'Wedos']", "'Hetzner', 'Forpsi']", (string) file_get_contents($file));

            return response($js, 200, ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
        }
        $response = new BinaryFileResponse($file, 200, ['Content-Type' => SurfaceRenderer::mime($file), 'X-Content-Type-Options' => 'nosniff']);
        // design-system bundle is content-addressed (immutable); prototype scripts short-lived; API seams are versioned by query (mtime)
        $response->setPublic()->setMaxAge(str_contains($path, '_ds/') ? 31536000 : (str_starts_with($path, 'api/') ? 86400 : 300));

        return $response;
    }

    private function surface(Request $request, string $surface, ?string $hash): Response
    {
        $user = $request->user();
        $demo = (bool) config('onhost.ui.demo', false);
        if ($user === null && in_array($surface, ['panel', 'admin', 'partner'], true) && ! $demo) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        $boot = $this->boot($request, $surface, $hash, $demo);
        if ($user !== null && $surface === 'admin' && ! ($boot['user']['staff'] ?? false) && ! $demo) {
            return redirect('/panel');
        }
        $html = $this->renderer->render($surface, $boot, $demo);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin-when-cross-origin']);
    }

    /** The `window.ONHOST` boot object (template-inventory §6.6). */
    public function boot(Request $request, string $surface, ?string $hash, bool $demo): array
    {
        $user = $request->user();

        return [
            'apiBase' => '/v1',
            'csrf' => csrf_token(),
            'surface' => $surface,
            'hash' => $hash,
            'demo' => $demo,
            'turnstile' => (string) config('onhost.turnstile.site_key', '') ?: null, // §5q-6: the surfaces render the widget when a site key is set
            'locale' => $user?->locale ?? 'cs',
            'version' => (string) config('onhost.version', '4.0'),
            'user' => $user instanceof User ? $this->userBoot($user) : null,
            'links' => ['login' => '/prihlaseni', 'panel' => '/panel', 'admin' => '/sprava', 'partner' => '/partner', 'status' => '/stav'],
        ];
    }

    private function userBoot(User $user): array
    {
        $memberships = OrganizationMembership::query()->where('user_id', $user->id)->where('state', 'active')->orderBy('joined_at')->get();
        $organizations = Organization::query()->whereIn('id', $memberships->pluck('organization_id'))->get();
        $current = $organizations->first();
        $partner = $current === null ? null : Partner::query()->where('organization_id', $current->id)->where('state', 'active')->first();
        $role = $this->role($user, $partner !== null);

        return [
            'id' => $user->id, 'email' => $user->email, 'name' => $user->name, 'locale' => $user->locale ?? 'cs', 'staff' => (bool) $user->is_staff, 'role' => $role,
            'since' => $user->created_at?->toIso8601String(), 'email_verified' => $user->email_verified_at !== null, 'member_role' => $current === null ? null : $memberships->firstWhere('organization_id', $current->id)?->role_key,
            'organization' => $current === null ? null : [
                'id' => $current->id, 'name' => $current->name, 'slug' => $current->slug, 'currency' => $current->currency, 'billing_mode' => $current->billing_mode, 'ui_mode' => $current->ui_mode,
                // billing identity for pre-filling the checkout (the signed-in customer's own organization only)
                'type' => $current->type, 'ico' => $current->ico, 'dic' => $current->dic, 'vat_id' => $current->vat_id, 'street' => $current->street, 'city' => $current->city, 'postal_code' => $current->postal_code, 'country' => $current->country,
            ],
            'organizations' => $organizations->map(fn (Organization $o) => ['id' => $o->id, 'name' => $o->name])->values()->all(),
            'partner' => $partner === null ? null : ['code' => $partner->code, 'tier' => $partner->tier],
            'mfa' => $user->totp_confirmed_at !== null,
        ];
    }

    /** Prototype role ids (onhost-shell.js ROLES): admin | klient | partner | noc | fakturace. */
    private function role(User $user, bool $partner): string
    {
        if (! $user->is_staff) {
            return $partner ? 'partner' : 'klient';
        }
        $can = fn (string $permission) => $this->authorizer->can($user, $permission, CommandScope::global());
        if ($can('staff.service.manage') || $can('support.ticket.manage') || $can('iam.role.manage')) {
            return 'admin';
        }
        if ($can('incident.manage')) {
            return 'noc';
        }
        if ($can('billing.invoice.manage') || $can('billing.dunning.manage')) {
            return 'fakturace';
        }

        return 'admin';
    }

    private function hash(?string $path): ?string
    {
        return $path === null || $path === '' ? null : '#/'.trim($path, '/');
    }
}
