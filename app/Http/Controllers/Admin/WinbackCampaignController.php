<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\WinbackCampaign;
use App\Notifications\WinbackCampaignNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WinbackCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = WinbackCampaign::with('creator')->latest()->paginate(20);
        return view('admin.winback-campaigns.index', compact('campaigns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'target_segment' => ['required', 'in:churned,at_risk,healthy,vip'],
            'message'        => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $validated['created_by'] = $request->user()->id;

        $campaign = WinbackCampaign::create($validated);

        return redirect()->route('admin.winback-campaigns.index')->with('status', "Kampaň \"{$campaign->name}\" vytvořena.");
    }

    public function send(Request $request, WinbackCampaign $winbackCampaign): RedirectResponse
    {
        if ($winbackCampaign->sent_at !== null) {
            return back()->withErrors(['error' => 'Tato kampaň již byla odeslána.']);
        }

        $customers = Customer::where('segment', $winbackCampaign->target_segment)
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($customers as $customer) {
            if ($customer->user !== null) {
                $customer->user->notify(new WinbackCampaignNotification($winbackCampaign));
                $sent++;
            }
        }

        $winbackCampaign->update(['sent_at' => now(), 'sent_count' => $sent]);

        return back()->with('status', "Kampaň odeslána {$sent} zákazníkům.");
    }
}
