<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\CustomerInternalNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerInternalNoteController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'content'   => ['required', 'string', 'min:2', 'max:5000'],
            'is_pinned' => ['boolean'],
        ]);

        CustomerInternalNote::create([
            'customer_id' => $customer->id,
            'admin_id'    => $request->user()->id,
            'content'     => $data['content'],
            'is_pinned'   => $data['is_pinned'] ?? false,
        ]);

        return back()->with('success', 'Poznámka přidána.');
    }

    public function destroy(Customer $customer, CustomerInternalNote $note): RedirectResponse
    {
        abort_unless($note->customer_id === $customer->id, 404);

        $note->delete();

        return back()->with('success', 'Poznámka smazána.');
    }

    public function pin(Customer $customer, CustomerInternalNote $note): RedirectResponse
    {
        abort_unless($note->customer_id === $customer->id, 404);

        $note->update(['is_pinned' => ! $note->is_pinned]);

        return back();
    }
}
