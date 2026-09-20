<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Provisioning\BulkActionService;

/**
 * System settings for staff: provider onboarding (instances, credentials, TLS pinning, nodes, health). The prototype
 * console has no such screen, so this is a server-rendered page in the design system that drives the same staff API
 * (`/v1/staff/integrations…`) the runbook documents; it is linked from the console's user menu.
 */
final class SystemSettingsController extends Controller
{
    public function integrations(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! $user->is_staff) {
            return redirect('/panel');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view('admin.integrations', [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
            'environment' => app()->environment(),
        ]);
    }

    /** Bulk staff actions (audit §5e-7): start an action across a selection of services and follow the jobs. */
    public function bulk(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! $user->is_staff) {
            return redirect('/panel');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view('admin.bulk', [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
            'actions' => BulkActionService::ACTIONS,
        ]);
    }

    /** Staff operations board (audit §5e-3): stalled, failed and long-running operations, nodes with drain/resume. */
    /** Nastavení systému → Životní cyklus služeb: lhůta na obnovu, uchování archivů, poplatek za stažení (audit §5ab). */
    public function lifecycle(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! $user->is_staff) {
            return redirect('/panel');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view('admin.lifecycle', [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
        ]);
    }

    /** Nastavení systému → Tarify a verze: a plan is never edited, a change is a new version (Brain card H01). */
    public function plans(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! $user->is_staff) {
            return redirect('/panel');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view('admin.plans', [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
        ]);
    }

    /** Nastavení systému → Schvalování: requests for the second person of a critical action (four eyes, ApprovalService). */
    public function approvals(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! $user->is_staff) {
            return redirect('/panel');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view('admin.approvals', [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
        ]);
    }

    public function operations(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/prihlaseni?next='.urlencode($request->getRequestUri()));
        }
        if (! $user->is_staff) {
            return redirect('/panel');
        }
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return view('admin.operations', [
            'user' => $user,
            'stylesheet' => $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1)),
        ]);
    }
}
