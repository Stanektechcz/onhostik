<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

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
     * Domain availability check — placeholder until the WEDOS mock driver
     * lands in Phase 7. Validates input and reports the module status;
     * NEVER calls a real registrar API from a controller.
     */
    public function check(Request $request): RedirectResponse
    {
        $request->validate([
            'domain' => ['required', 'string', 'min:3', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]+$/'],
        ]);

        return back()
            ->withInput()
            ->with('domain_check_status', __('front.domains.check_coming_soon'));
    }
}
