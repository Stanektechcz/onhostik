<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBlocklistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IpBlocklistController extends Controller
{
    public function index(): View
    {
        $entries = IpBlocklistEntry::with('blocker')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.ip-blocklist.index', compact('entries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip', 'max:45'],
            'reason'     => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        IpBlocklistEntry::updateOrCreate(
            ['ip_address' => $validated['ip_address']],
            [
                'reason'     => $validated['reason'] ?? null,
                'blocked_by' => $request->user()->id,
                'expires_at' => $validated['expires_at'] ?? null,
            ]
        );

        return back()->with('status', 'IP adresa zablokována.');
    }

    public function destroy(IpBlocklistEntry $ipBlocklistEntry): RedirectResponse
    {
        $ipBlocklistEntry->delete();

        return back()->with('status', 'IP adresa odblokována.');
    }
}
