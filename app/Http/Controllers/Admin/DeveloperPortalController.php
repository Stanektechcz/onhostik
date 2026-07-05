<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Developer\Models\OAuthApplication;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DeveloperPortalController extends Controller
{
    public function index(): View
    {
        $apps = OAuthApplication::query()
            ->with('customer.user')
            ->latest()
            ->paginate(30);

        return view('admin.developer-portal.index', compact('apps'));
    }

    public function destroy(OAuthApplication $oauthApp): RedirectResponse
    {
        $oauthApp->delete();

        return back()->with('status', 'OAuth aplikace byla odstraněna.');
    }
}
