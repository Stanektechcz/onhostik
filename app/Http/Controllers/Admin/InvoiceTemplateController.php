<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceTemplateController extends Controller
{
    public function index(): View
    {
        $templates = InvoiceTemplate::with('customer')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.invoice-templates.index', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name'        => ['required', 'string', 'max:100'],
            'currency'    => ['required', 'string', 'size:3'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'line_items'  => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price'  => ['required', 'integer', 'min:0'],
        ]);

        InvoiceTemplate::create($validated);

        return back()->with('status', 'Šablona faktury uložena.');
    }

    public function destroy(InvoiceTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', 'Šablona smazána.');
    }
}
