<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DarkModeController extends Controller
{
    public function toggle(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $user->update(['dark_mode' => ! $user->dark_mode]);

        // The header toggle posts via fetch and wants JSON; a no-JS fallback
        // (plain form submit) still works via the redirect.
        if ($request->wantsJson()) {
            return response()->json(['dark_mode' => $user->dark_mode]);
        }

        return back()->with('status', $user->dark_mode ? 'Tmavý režim zapnut.' : 'Tmavý režim vypnut.');
    }
}
