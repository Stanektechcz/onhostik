<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignToCustomerJob;
use App\Jobs\SendNewsletterJob;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = NewsletterCampaign::with('author')
            ->latest()
            ->paginate(20);

        $draftCount   = NewsletterCampaign::where('status', 'draft')->count();
        $sentCount    = NewsletterCampaign::where('status', 'sent')->count();
        $activeCount  = Subscriber::active()->confirmed()->count();

        return view('admin.newsletter.index', compact(
            'campaigns', 'draftCount', 'sentCount', 'activeCount'
        ));
    }

    public function create(): View
    {
        return view('admin.newsletter.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject'         => ['required', 'string', 'max:255'],
            'body_html'       => ['required', 'string'],
            'body_text'       => ['nullable', 'string'],
            'target_audience' => ['nullable', 'string', 'in:all_subscribers,customers_all,customers_vip,customers_healthy,customers_at_risk,customers_churned'],
        ]);

        $campaign = NewsletterCampaign::create([
            ...$validated,
            'status'          => 'draft',
            'target_audience' => $validated['target_audience'] ?? 'all_subscribers',
            'created_by'      => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.newsletter.show', $campaign)
            ->with('status', 'Kampáň "' . $campaign->subject . '" byla uložena jako koncept.');
    }

    public function show(NewsletterCampaign $campaign): View
    {
        $activeCount = Subscriber::active()->confirmed()->count();

        return view('admin.newsletter.show', compact('campaign', 'activeCount'));
    }

    public function edit(NewsletterCampaign $campaign): View
    {
        abort_if(! $campaign->isDraft(), 403, 'Pouze koncepty lze upravovat.');

        return view('admin.newsletter.edit', compact('campaign'));
    }

    public function update(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        abort_if(! $campaign->isDraft(), 403, 'Pouze koncepty lze upravovat.');

        $validated = $request->validate([
            'subject'   => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
        ]);

        $campaign->update($validated);

        return redirect()
            ->route('admin.newsletter.show', $campaign)
            ->with('status', 'Kampaň byla aktualizována.');
    }

    public function destroy(NewsletterCampaign $campaign): RedirectResponse
    {
        abort_if(! $campaign->isDraft(), 403, 'Smazat lze pouze koncepty.');

        $subject = $campaign->subject;
        $campaign->delete();

        return redirect()
            ->route('admin.newsletter.index')
            ->with('status', 'Kampaň "' . $subject . '" byla smazána.');
    }

    public function send(NewsletterCampaign $campaign): RedirectResponse
    {
        abort_if(! $campaign->isDraft(), 403, 'Kampaň již byla odeslána nebo probíhá.');

        $audience = $campaign->target_audience ?? 'all_subscribers';

        if (in_array($audience, NewsletterCampaign::CUSTOMER_AUDIENCES, true)) {
            return $this->sendToCustomers($campaign, $audience);
        }

        $subscribers = Subscriber::active()->confirmed()->get();

        if ($subscribers->isEmpty()) {
            return back()->with('error', 'Žádní aktivní odběratelé k odeslání.');
        }

        $campaign->update([
            'status'            => 'sending',
            'recipients_count'  => $subscribers->count(),
            'sent_count'        => 0,
            'sent_at'           => now(),
        ]);

        foreach ($subscribers as $subscriber) {
            SendNewsletterJob::dispatch($campaign->id, $subscriber->id);
        }

        return redirect()
            ->route('admin.newsletter.show', $campaign)
            ->with('status', "Odesílání zahájeno: {$subscribers->count()} e-mailů zařazeno do fronty.");
    }

    private function sendToCustomers(NewsletterCampaign $campaign, string $audience): RedirectResponse
    {
        $query = Customer::query()->whereNotNull('email');

        if ($audience !== 'customers_all') {
            $segment = str_replace('customers_', '', $audience);
            $query->where('segment', $segment);
        }

        $customers = $query->get(['id', 'email', 'company_name']);

        if ($customers->isEmpty()) {
            return back()->with('error', 'Žádní zákazníci ve vybraném segmentu.');
        }

        $campaign->update([
            'status'           => 'sending',
            'recipients_count' => $customers->count(),
            'sent_count'       => 0,
            'sent_at'          => now(),
        ]);

        foreach ($customers as $customer) {
            SendCampaignToCustomerJob::dispatch($campaign->id, $customer->email, (string) $customer->company_name);
        }

        return redirect()
            ->route('admin.newsletter.show', $campaign)
            ->with('status', "Odesílání zákazníkům zahájeno: {$customers->count()} e-mailů zařazeno do fronty.");
    }

    public function markSent(NewsletterCampaign $campaign): RedirectResponse
    {
        if ($campaign->isSending()) {
            $campaign->update(['status' => 'sent']);
        }

        return back()->with('status', 'Kampaň označena jako odeslaná.');
    }
}
