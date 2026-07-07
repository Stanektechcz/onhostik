<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DarkModeController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['dark_mode' => ! $user->dark_mode]);

        return back()->with('status', $user->dark_mode ? 'Tmavý režim zapnut.' : 'Tmavý režim vypnut.');
    }
}
