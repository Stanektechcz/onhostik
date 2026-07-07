<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerTag;
use App\Http\Controllers\Controller;
use App\Jobs\SendBulkCustomerEmailJob;
use App\Models\BulkCustomerEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BulkCustomerEmailController extends Controller
{
    public function index(): View
    {
        $campaigns = BulkCustomerEmail::with('creator')
            ->latest()
            ->paginate(20);

        $tags = CustomerTag::orderBy('name')->get(['id', 'name', 'color']);

        $countries = Customer::query()
            ->whereNotNull('country_code')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code');

        return view('admin.bulk-customer-email', compact('campaigns', 'tags', 'countries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject'              => ['required', 'string', 'max:255'],
            'body_html'            => ['required', 'string'],
            'body_text'            => ['nullable', 'string'],
            'filter_segment'       => ['nullable', 'string', 'in:vip,at_risk,healthy,churned'],
            'filter_country_code'  => ['nullable', 'string', 'size:2'],
            'filter_tag_id'        => ['nullable', 'integer', 'exists:customer_tags,id'],
            'filter_has_overdue'   => ['nullable', 'boolean'],
        ]);

        $filters = array_filter([
            'segment'      => $validated['filter_segment']      ?? null,
            'country_code' => $validated['filter_country_code'] ?? null,
            'tag_id'       => $validated['filter_tag_id']       ?? null,
            'has_overdue'  => $validated['filter_has_overdue']  ?? null,
        ]);

        $campaign = BulkCustomerEmail::create([
            'created_by' => $request->user()?->id,
            'subject'    => $validated['subject'],
            'body_html'  => $validated['body_html'],
            'body_text'  => $validated['body_text'] ?? null,
            'filters'    => $filters,
            'status'     => 'draft',
        ]);

        return redirect()
            ->route('admin.bulk-email.show', $campaign)
            ->with('status', 'E-mailová kampaň byla uložena jako koncept.');
    }

    public function show(BulkCustomerEmail $bulkEmail): View
    {
        $bulkEmail->load('creator');
        $recipientCount = $bulkEmail->buildRecipientQuery()->count();

        return view('admin.bulk-customer-email-show', compact('bulkEmail', 'recipientCount'));
    }

    public function send(BulkCustomerEmail $bulkEmail): RedirectResponse
    {
        abort_if(! $bulkEmail->isDraft(), 403, 'Kampaň již byla odeslána nebo probíhá.');

        $customers = $bulkEmail->buildRecipientQuery()->get();

        if ($customers->isEmpty()) {
            return back()->withErrors(['send' => 'Žádní zákazníci neodpovídají vybraným filtrům.']);
        }

        $bulkEmail->update([
            'status'            => 'sending',
            'recipients_count'  => $customers->count(),
            'sent_count'        => 0,
            'sent_at'           => now(),
        ]);

        foreach ($customers as $customer) {
            SendBulkCustomerEmailJob::dispatch(
                $bulkEmail->id,
                (string) $customer->email,
                (string) ($customer->company_name ?? ''),
            );
        }

        return redirect()
            ->route('admin.bulk-email.show', $bulkEmail)
            ->with('status', "Odesílání zahájeno: {$customers->count()} e-mailů zařazeno do fronty.");
    }

    public function previewCount(Request $request): JsonResponse
    {
        $filters = array_filter([
            'segment'      => $request->input('filter_segment'),
            'country_code' => $request->input('filter_country_code'),
            'tag_id'       => $request->input('filter_tag_id'),
            'has_overdue'  => $request->boolean('filter_has_overdue') ?: null,
        ]);

        $count = BulkCustomerEmail::buildFilterQuery($filters)->count();

        return response()->json(['count' => $count]);
    }

    public function destroy(BulkCustomerEmail $bulkEmail): RedirectResponse
    {
        abort_if(! $bulkEmail->isDraft(), 403, 'Smazat lze pouze koncepty.');

        $bulkEmail->delete();

        return redirect()
            ->route('admin.bulk-email.index')
            ->with('status', 'Kampaň byla smazána.');
    }
}
