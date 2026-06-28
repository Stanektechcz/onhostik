<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerProgramController extends Controller
{
    public function settings(): View
    {
        $raw = DB::table('partner_program_settings')->get()->keyBy('key');

        $settings = collect([
            'affiliate_enabled', 'affiliate_commission_rate', 'affiliate_cookie_days', 'affiliate_min_payout',
            'referral_enabled', 'referral_reward_amount', 'referral_reward_currency', 'referral_code_length',
            'codes_enabled', 'codes_discount_type', 'codes_max_uses_per_code',
            'banners_enabled',
            'payout_schedule', 'payout_min_amount', 'payout_currency',
            'auto_approve_threshold', 'eligible_days_after_payment',
        ])->mapWithKeys(fn ($key) => [$key => $raw->get($key)?->value]);

        return view('admin.partner-program-settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'affiliate_enabled'           => ['boolean'],
            'affiliate_commission_rate'   => ['required', 'integer', 'min:1', 'max:100'],
            'affiliate_cookie_days'       => ['required', 'integer', 'min:1', 'max:365'],
            'affiliate_min_payout'        => ['required', 'integer', 'min:0'],
            'referral_enabled'            => ['boolean'],
            'referral_reward_amount'      => ['required', 'integer', 'min:0'],
            'referral_reward_currency'    => ['required', 'string', 'in:CZK,EUR,USD'],
            'referral_code_length'        => ['required', 'integer', 'min:4', 'max:20'],
            'codes_enabled'               => ['boolean'],
            'codes_discount_type'         => ['required', 'string', 'in:percent,fixed'],
            'codes_max_uses_per_code'     => ['required', 'integer', 'min:1'],
            'banners_enabled'             => ['boolean'],
            'payout_schedule'             => ['required', 'string', 'in:weekly,monthly,quarterly'],
            'payout_min_amount'           => ['required', 'integer', 'min:0'],
            'payout_currency'             => ['required', 'string', 'in:CZK,EUR,USD'],
            'auto_approve_threshold'      => ['required', 'integer', 'min:0'],
            'eligible_days_after_payment' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data as $key => $value) {
            DB::table('partner_program_settings')
                ->where('key', $key)
                ->update(['value' => (string) ($value ?? '0'), 'updated_at' => now()]);
        }

        return back()->with('status', 'Nastavení partnerského programu bylo uloženo.');
    }
}
