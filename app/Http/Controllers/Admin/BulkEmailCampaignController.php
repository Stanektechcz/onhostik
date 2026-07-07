<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\BulkEmailCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class BulkEmailCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = BulkEmailCampaign::with('creator')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.bulk-email.index', compact('campaigns'));
    }

    public function create(): View
    {
        return view('admin.bulk-email.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject'          => ['required', 'string', 'max:255'],
            'body_html'        => ['required', 'string'],
            'target_segment'   => ['nullable', 'string', 'max:50'],
            'target_country'   => ['nullable', 'string', 'size:2'],
            'scheduled_at'     => ['nullable', 'date', 'after:now'],
        ]);

        $query = Customer::query();
        if (! empty($validated['target_segment'])) {
            $query->where('segment', $validated['target_segment']);
        }
        if (! empty($validated['target_country'])) {
            $query->where('country_code', $validated['target_country']);
        }

        $recipientCount = $query->has('user')->count();

        BulkEmailCampaign::create(array_merge($validated, [
            'created_by'      => $request->user()?->id,
            'status'          => 'draft',
            'recipient_count' => $recipientCount,
        ]));

        return redirect()->route('admin.bulk-email-campaigns.index')->with('status', 'Kampaň vytvořena. Příjemců: ' . $recipientCount);
    }

    public function destroy(BulkEmailCampaign $campaign): RedirectResponse
    {
        abort_if($campaign->status === 'sending', 422);
        $campaign->delete();

        return back()->with('status', 'Kampaň smazána.');
    }
}
