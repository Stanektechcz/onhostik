<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Provisioning\Services\DriverResolver;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(): View
    {
        return view('front.domains');
    }

    /**
     * Domain availability check via the MOCK WEDOS registrar.
     * Rate-limited (throttle:domain-check) because the real WAPI allows
     * only 100 checks/hour; NEVER calls a real registrar API.
     */
    public function check(Request $request, DriverResolver $drivers): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'min:3', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$/'],
        ]);

        $result = $drivers->registrar()->checkDomain((string) $validated['domain']);

        return back()
            ->withInput()
            ->with('domain_check_result', [
                'fqdn'      => $result->fqdn,
                'available' => $result->available,
                'reason'    => $result->reason,
            ]);
    }
}
