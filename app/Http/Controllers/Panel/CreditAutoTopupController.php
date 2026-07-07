<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditAutoTopupController extends Controller
{
    public function show(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user     = $request->user();
        $customer = $user->customer;
        $config   = ($customer !== null ? $customer->credit_auto_topup : null) ?? [];

        return view('panel.billing.auto-topup', [
            'enabled'          => (bool) ($config['enabled'] ?? false),
            'threshold_amount' => isset($config['threshold_minor']) ? ($config['threshold_minor'] / 100) : 500,
            'topup_amount'     => isset($config['topup_minor'])     ? ($config['topup_minor'] / 100)     : 1000,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'          => ['boolean'],
            'threshold_amount' => ['required', 'numeric', 'min:100', 'max:50000'],
            'topup_amount'     => ['required', 'numeric', 'min:100', 'max:50000'],
        ]);

        /** @var \App\Models\User $user */
        $user     = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            return back()->withErrors(['error' => 'Zákaznický profil nenalezen.']);
        }

        $customer->update([
            'credit_auto_topup' => [
                'enabled'          => (bool) ($data['enabled'] ?? false),
                'threshold_minor'  => (int) round((float) $data['threshold_amount'] * 100),
                'topup_minor'      => (int) round((float) $data['topup_amount'] * 100),
            ],
        ]);

        return back()->with('success', 'Nastavení automatického dobití bylo uloženo.');
    }
}
