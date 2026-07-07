<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $userId   = $request->user()->getAuthIdentifier();
        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get();

        $currentId = $request->session()->getId();

        return view('panel.account.sessions', [
            'sessions'  => $sessions,
            'currentId' => $currentId,
        ]);
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('status', 'Relace byla ukončena.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $userId    = $request->user()->getAuthIdentifier();
        $currentId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentId)
            ->delete();

        return back()->with('status', 'Všechny ostatní relace byly ukončeny.');
    }
}
