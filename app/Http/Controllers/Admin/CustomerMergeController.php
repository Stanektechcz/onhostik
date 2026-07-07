<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\CustomerMerge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerMergeController extends Controller
{
    // Phase 159: real merge UI + action

    public function show(Customer $customer): View
    {
        return view('admin.customer-merge', compact('customer'));
    }

    public function merge(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate([
            'source_customer_id' => 'required|integer',
        ]);

        $sourceId = (int) $request->input('source_customer_id');

        if ($sourceId === $customer->id) {
            abort(422);
        }

        $source = Customer::findOrFail($sourceId);

        Invoice::where('customer_id', $source->id)->update(['customer_id' => $customer->id]);

        $source->delete();

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'Zákazníci sloučeni.');
    }

    // Phase 240: merge request log

    public function index(): View
    {
        $merges = CustomerMerge::orderByDesc('created_at')->paginate(15);

        return view('admin.customer-merges.index', compact('merges'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'primary_customer_id' => 'required|integer',
            'merged_customer_id'  => 'required|integer|different:primary_customer_id',
            'note'                => 'nullable|string|max:500',
        ]);

        CustomerMerge::create([
            'primary_customer_id'  => $validated['primary_customer_id'],
            'merged_customer_id'   => $validated['merged_customer_id'],
            'status'               => 'pending',
            'transferred_entities' => [],
            'performed_by'         => $request->user()->id,
            'note'                 => $validated['note'] ?? null,
        ]);

        return back()->with('status', 'Žádost o sloučení vytvořena.');
    }
}
