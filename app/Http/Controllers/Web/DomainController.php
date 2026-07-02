<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Provisioning\Services\DriverResolver;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(): View
    {
        return view('front.domains');
    }

    /**
     * Single domain availability check via the MOCK WEDOS registrar.
     * Rate-limited (throttle:domain-check).
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

    /**
     * Bulk multi-TLD availability check.
     * POST /domeny/bulk — returns JSON {results: [{tld, fqdn, available}]}.
     * Rate-limited via throttle:domain-check middleware on route.
     */
    public function bulkCheck(Request $request, DriverResolver $drivers): JsonResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:63', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*$/'],
            'tlds'    => ['required', 'array', 'min:1', 'max:20'],
            'tlds.*'  => ['required', 'string', 'min:2', 'max:20', 'regex:/^\.[a-zA-Z]{2,}$/'],
        ]);

        $name      = strtolower((string) $validated['name']);
        /** @var list<string> $tlds */
        $tlds      = (array) $validated['tlds'];
        $results   = [];
        $registrar = $drivers->registrar();

        foreach ($tlds as $tld) {
            $fqdn = $name . $tld;
            try {
                $result    = $registrar->checkDomain($fqdn);
                $results[] = ['tld' => $tld, 'fqdn' => $result->fqdn, 'available' => $result->available];
            } catch (\Throwable) {
                $results[] = ['tld' => $tld, 'fqdn' => $fqdn, 'available' => null];
            }
        }

        return response()->json(['results' => $results]);
    }
}
