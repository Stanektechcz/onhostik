<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecuritySettingsController extends Controller
{
    private const GROUP = 'security';

    private const KEYS = [
        'require_customer_2fa',
        'require_admin_2fa',
    ];

    public function index(): View
    {
        $stored = DB::table('settings')
            ->where('group', self::GROUP)
            ->pluck('payload', 'name')
            ->map(fn ($v) => json_decode((string) $v, true))
            ->toArray();

        return view('admin.security-settings', [
            'stored' => $stored,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'require_customer_2fa' => 'nullable|boolean',
            'require_admin_2fa'    => 'nullable|boolean',
        ]);

        foreach (self::KEYS as $key) {
            $value = isset($validated[$key]) && (bool) $validated[$key];

            DB::table('settings')->updateOrInsert(
                ['group' => self::GROUP, 'name' => $key],
                ['payload' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        return redirect()
            ->route('admin.security-settings.index')
            ->with('status', 'Nastavení zabezpečení bylo uloženo.');
    }
}
