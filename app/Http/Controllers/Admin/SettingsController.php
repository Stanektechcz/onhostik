<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /** Keys stored in the `settings` table as group=site, name=<key>. */
    private const SITE_KEYS = [
        'company_name', 'company_ic', 'company_dic',
        'company_street', 'company_city', 'company_zip',
        'bank_czk', 'bank_eur',
        'support_email', 'billing_email', 'sales_email',
        'news_ticker',
    ];

    public function index(): View
    {
        $stored = DB::table('settings')
            ->where('group', 'site')
            ->pluck('payload', 'name')
            ->map(fn ($v) => json_decode($v, true))
            ->toArray();

        return view('admin.settings', [
            'stored' => $stored,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name'   => 'nullable|string|max:200',
            'company_ic'     => 'nullable|string|max:20',
            'company_dic'    => 'nullable|string|max:20',
            'company_street' => 'nullable|string|max:200',
            'company_city'   => 'nullable|string|max:100',
            'company_zip'    => 'nullable|string|max:10',
            'bank_czk'       => 'nullable|string|max:50',
            'bank_eur'       => 'nullable|string|max:50',
            'support_email'  => 'nullable|email|max:100',
            'billing_email'  => 'nullable|email|max:100',
            'sales_email'    => 'nullable|email|max:100',
            'news_ticker'    => 'nullable|string|max:200',
            'clear_cache'    => 'nullable|boolean',
        ]);

        foreach (self::SITE_KEYS as $key) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'site', 'name' => $key],
                ['payload' => json_encode($validated[$key] ?? null), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        if ($request->boolean('clear_cache')) {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        }

        return redirect()->route('admin.settings.index')
            ->with('status', 'Nastavení bylo uloženo.');
    }
}
