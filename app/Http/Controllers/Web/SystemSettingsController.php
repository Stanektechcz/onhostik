<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Navigation\StaffNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\BulkActionService;

/**
 * System settings for staff: provider onboarding (instances, credentials, TLS pinning, nodes, health). The prototype
 * console has no such screen, so this is a server-rendered page in the design system that drives the same staff API
 * (`/v1/staff/integrations…`) the runbook documents; it is linked from the console's user menu.
 *
 * Audit 2026-10 P1-4 / B4: every page is authorized like its navigation item (StaffNavigation) — a member of staff opens a
 * page only when one of the items that lead to it is theirs; being staff is not enough.
 */
final class SystemSettingsController extends Controller
{
    /** The default settings page: whoever may not open it is taken to the first settings page they may open. */
    private const DEFAULT_PAGE = '/sprava/nastaveni/integrace';

    public function __construct(private readonly StaffNavigation $navigation) {}

    public function integrations(Request $request): View|RedirectResponse
    {
        return $this->page($request, self::DEFAULT_PAGE, 'admin.integrations', fn (User $user) => ['environment' => app()->environment()]);
    }

    /** Bulk staff actions (audit §5e-7): start an action across a selection of services and follow the jobs. */
    public function bulk(Request $request): View|RedirectResponse
    {
        return $this->page($request, '/sprava/nastaveni/hromadne-akce', 'admin.bulk', fn (User $user) => ['actions' => BulkActionService::ACTIONS]);
    }

    /** Nastavení systému → Životní cyklus služeb: lhůta na obnovu, uchování archivů, poplatek za stažení (audit §5ab). */
    public function lifecycle(Request $request): View|RedirectResponse
    {
        return $this->page($request, '/sprava/nastaveni/zivotni-cyklus', 'admin.lifecycle');
    }

    /** Nastavení systému → Tarify a verze: a plan is never edited, a change is a new version (Brain card H01). */
    public function plans(Request $request): View|RedirectResponse
    {
        return $this->page($request, '/sprava/nastaveni/tarify', 'admin.plans');
    }

    /** Nastavení systému → Schvalování: requests for the second person of a critical action (four eyes, ApprovalService). */
    public function approvals(Request $request): View|RedirectResponse
    {
        return $this->page($request, '/sprava/nastaveni/schvalovani', 'admin.approvals');
    }

    /** Staff operations board (audit §5e-3): stalled, failed and long-running operations, nodes with drain/resume. */
    public function operations(Request $request): View|RedirectResponse
    {
        return $this->page($request, '/sprava/nastaveni/provoz', 'admin.operations');
    }

    /** @param (callable(User): array<string, mixed>)|null $extra */
    private function page(Request $request, string $path, string $view, ?callable $extra = null): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! StaffActor::account($user)) {
            return redirect('/panel');
        }
        if (! $this->navigation->canOpenPage($user, $path)) {
            $first = $path === self::DEFAULT_PAGE ? $this->navigation->firstPage($user) : null;
            if ($first !== null) {
                return redirect($first);
            }
            abort(403, 'Nemáte přístup k této stránce nastavení.');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view($view, [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
        ] + ($extra === null ? [] : $extra($user)));
    }
}
