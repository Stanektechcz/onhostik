<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Navigation\StaffNavigation;
use App\Http\Support\CreditClaimsSeam;
use App\Http\Support\CurrentOrganization;
use App\Http\Support\PublicApiDocs;
use App\Http\Support\PublicContentSeam;
use App\Http\Support\PublicSeo;
use App\Http\Support\ServicePageClaimsSeam;
use App\Http\Support\SurfaceRenderer;
use Illuminate\Http\Request;
use Onhost\Domain\Content\ContentService;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
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

    /** Design concepts with narrated data (owner decision R11): staff and demo mode only, never indexed, marked as a concept. */
    public const CONCEPT_SURFACES = ['mobile', 'widgets'];

    /** Where a signed-in person without a partnership is sent from /partner: the reseller programme with its application form. */
    public const PARTNER_APPLICATION = '/reseller';

    public function __construct(private readonly SurfaceRenderer $renderer, private readonly Authorizer $authorizer, private readonly StaffNavigation $navigation) {}

    public function public(Request $request, ?string $path = null): Response
    {
        $hash = $path === null || $path === '' ? null : '#/'.trim($path, '/');
        // W7-4: /blog/{slug} is an indexable article page; a draft, a withheld or an unknown post is a real 404 (no soft 404)
        if ($path === null || (bool) config('onhost.ui.demo', false) || ! str_starts_with($path, 'blog/') || trim(substr($path, 5), '/') === '') {
            return $this->surface($request, 'public', $hash);
        }
        if (preg_match('~^blog/([a-z0-9]+(?:-[a-z0-9]+)*)/?$~', $path, $m) === 1) {
            try {
                $article = app(ContentService::class)->post($m[1], app()->getLocale() === 'sk' ? 'sk' : 'cs', 'blog');
            } catch (DomainError $e) {
                if ($e->status !== 404) {
                    throw $e;
                }

                return $this->surface($request, 'public', $hash, null, 404);
            }

            return $this->surface($request, 'public', $hash, $article);
        }

        return $this->surface($request, 'public', $hash, null, 404); // a deeper path or a malformed slug under /blog/
    }

    /**
     * W6-6 (owner decision 2026-10-16): the Slovak public site at /sk/… on the same domain. The route is always registered (route:cache);
     * while config('onhost.locale_sk') is off it answers 404. On, it renders the public surface as ?locale=sk does (SetLocale sets 'sk'
     * from the /sk prefix); forms and personal flows under /sk/ carry noindex and stay out of the sitemap and hreflang.
     */
    public function publicSk(Request $request, ?string $path = null): Response
    {
        if (! (bool) config('onhost.locale_sk', false)) {
            abort(404);
        }
        app()->setLocale('sk');
        $response = $this->public($request, $path);
        if (PublicSeo::notIndexed($request->path())) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * W7-3-EN: the English public site at /en/… on the same domain, as /sk/ (W6-6). Always registered (route:cache); 404 while
     * config('onhost.locale_en_urls') is off (until the owner approves lang/en/seo.php, W7-K4). Forms under /en/ carry noindex.
     */
    public function publicEn(Request $request, ?string $path = null): Response
    {
        if (! (bool) config('onhost.locale_en_urls', false)) {
            abort(404);
        }
        app()->setLocale('en');
        $response = $this->public($request, $path);
        if (PublicSeo::notIndexed($request->path())) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
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
    public function asset(Request $request, string $path): BinaryFileResponse|Response
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

            return $this->scriptResponse($request, $js);
        }
        if ($path === 'onhost-shell.js' && ! config('onhost.ui.demo', false)) { // the prototype's surface switcher ("Plochy Onhost", bottom-left) is a design-review tool, not part of the product
            $js = str_replace('function boot() { if (EMBED) return;', 'function boot() { if (EMBED || (window.ONHOST && !window.ONHOST.demo)) return;', (string) file_get_contents($file));

            return $this->scriptResponse($request, $js);
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

            return $this->scriptResponse($request, $js);
        }
        // the public /api and /dokumentace pages (audit 2026-10 D3): the limits, headers, endpoints, errors, webhook retry and the
        // two API guides come from the routes, the config and the dispatcher; the prototype's story stays in demo mode only
        if (in_array($path, ['onhost-public.js', 'onhost-docs.js'], true) && ! config('onhost.ui.demo', false)) {
            $original = ServicePageClaimsSeam::apply((string) file_get_contents($file), $path); // Q1: no DDoS capacity on the SLA page
            $js = $path === 'onhost-public.js' ? PublicApiDocs::publicScript($original) : (PublicApiDocs::docsModule($original) ?? $original);

            return $this->scriptResponse($request, $js);
        }
        if ($path === 'onhost-content.js' && ! config('onhost.ui.demo', false)) { // credit is never paid back in cash (G8 item 9, owner decision 2026-10-05)
            // I-R17 (TASK-0149): the blog is what staff publish (ONHOST_DATA.blog); the prototype's posts are not sent
            $js = PublicContentSeam::contentModule(CreditClaimsSeam::apply((string) file_get_contents($file), ['content']));

            return $this->scriptResponse($request, $js);
        }
        if ($path === 'onhost-svc-web.js' && ! config('onhost.ui.demo', false)) { // customer surfaces never name a registrar vendor (seam #20); an SLA credit is credited, not refunded (G8 item 9)
            $js = CreditClaimsSeam::apply(str_replace("'Hetzner', 'Wedos']", "'Hetzner', 'Forpsi']", ServicePageClaimsSeam::apply((string) file_get_contents($file), $path)), ['svc-web']); // P1: SLA band

            return $this->scriptResponse($request, $js);
        }
        if (ServicePageClaimsSeam::covers($path) && ! config('onhost.ui.demo', false)) { // J-R2 (K8b): the invented eco figures and EPYC stock claims are not served
            $js = ServicePageClaimsSeam::apply((string) file_get_contents($file), $path);

            return $this->scriptResponse($request, $js);
        }
        $response = new BinaryFileResponse($file, 200, ['Content-Type' => SurfaceRenderer::mime($file), 'X-Content-Type-Options' => 'nosniff']);
        // design-system bundle is content-addressed (immutable); prototype scripts short-lived; API seams are versioned by query (mtime)
        $response->setPublic()->setMaxAge(str_contains($path, '_ds/') ? 31536000 : (str_starts_with($path, 'api/') ? 86400 : 300));

        return $response;
    }

    /** W7-2b (MERENI P4): a script transformed in PHP carries a content ETag; a matching If-None-Match gets 304 with no body. */
    private function scriptResponse(Request $request, string $js): Response
    {
        $response = response($js, 200, ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
        $response->setEtag(sha1($js));
        $response->isNotModified($request);

        return $response;
    }

    private function surface(Request $request, string $surface, ?string $hash, ?array $article = null, int $status = 200): Response
    {
        $user = $request->user();
        $demo = (bool) config('onhost.ui.demo', false);
        if ($user === null && in_array($surface, ['panel', 'admin', 'partner'], true) && ! $demo) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        // owner decision R11: the mobile shell and the component gallery are design concepts with narrated data — staff and demo only
        if (in_array($surface, self::CONCEPT_SURFACES, true) && ! $demo) {
            if ($user === null) {
                return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
            }
            if (! ($user instanceof User && StaffActor::account($user))) { // the same test as the staff console below
                return redirect('/panel');
            }
        }
        $boot = $this->boot($request, $surface, $hash, $demo);
        if ($user !== null && $surface === 'admin' && ! ($boot['user']['staff'] ?? false) && ! $demo) {
            return redirect('/panel');
        }
        // audit A3 (P0-2): the partner portal is for the people who run an active partnership; everyone else is shown the
        // programme and its application form instead of the prototype's narrated partner
        if ($user instanceof User && $surface === 'partner' && ! $demo && ! $this->runsPartnership($user, $boot['user'] ?? null)) {
            return redirect(self::PARTNER_APPLICATION);
        }
        $html = $this->renderer->render($surface, $boot, $demo); // the credit/bonus/archive sentences are corrected inside render(), in the template only (G8 item 9, H2)
        if ($surface === 'public' && ! $demo) {
            $html = PublicSeo::withHead($html, $request->path(), $article); // N15 + W7-3: title, description, canonical, OG, JSON-LD and hreflang through the head seam
        }
        if ($status === 404) { // W7-4: a missing post is never indexed
            $html = PublicSeo::withNoindex($html);
        }
        $headers = ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin-when-cross-origin'];
        // W7-3: forms and personal flows are never indexed, and nothing public is outside production (staging carries its own portal_url)
        if ($status === 404 || in_array($surface, self::CONCEPT_SURFACES, true) || ($surface === 'public' && (PublicSeo::notIndexed($request->path()) || ! PublicSeo::indexable()))) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        return response($html, $status, $headers);
    }

    /**
     * The organization the surfaces act for (the boot's current organization, the one the session bridge sends as
     * X-Organization) is an active partner and this person may read its portal (partner.portal.read, as the partner API asks).
     *
     * @param  array<string,mixed>|null  $boot
     */
    private function runsPartnership(User $user, ?array $boot): bool
    {
        $organization = $boot['organization']['id'] ?? null;
        if (! is_string($organization) || ($boot['partner'] ?? null) === null) {
            return false;
        }

        return $this->authorizer->can($user, 'partner.portal.read', CommandScope::organization($organization));
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
            'user' => $user instanceof User ? $this->userBoot($user, $request) : null,
            'links' => ['login' => '/prihlaseni', 'panel' => '/panel', 'admin' => '/sprava', 'partner' => '/partner', 'status' => '/stav'],
        ];
    }

    private function userBoot(User $user, Request $request): array
    {
        $memberships = OrganizationMembership::query()->where('user_id', $user->id)->current()->orderBy('joined_at')->get();
        $organizations = Organization::query()->whereIn('id', $memberships->pluck('organization_id'))->get();
        // TASK-0070: the organization chosen in this session (the switcher), else the first membership as before; the session bridge
        // sends it as X-Organization and the panel data script is loaded for it
        $chosen = CurrentOrganization::chosen($request, $user);
        $current = ($chosen !== null ? $organizations->firstWhere('id', $chosen) : null) ?? $organizations->first();
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
            'organizations' => $organizations->map(fn (Organization $o) => ['id' => $o->id, 'name' => $o->name, 'role' => $memberships->firstWhere('organization_id', $o->id)?->role_key, 'current' => $current !== null && $o->id === $current->id])->values()->all(),
            'partner' => $partner === null ? null : ['code' => $partner->code, 'tier' => $partner->tier],
            'mfa' => $user->totp_confirmed_at !== null,
            // the staff console's navigation (audit 2026-10 B2): the items this person may open, each with the reads it may make
            'nav' => StaffActor::account($user) ? $this->navigation->for($user) : [],
        ];
    }

    /** Prototype role ids (onhost-shell.js ROLES): admin | klient | partner | noc | fakturace, and `none` for staff no persona fits (R10). */
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

        // owner decision R10: a member of staff whose permissions fit no persona is not an admin; the console follows `nav`
        return 'none';
    }

    private function hash(?string $path): ?string
    {
        return $path === null || $path === '' ? null : '#/'.trim($path, '/');
    }
}
