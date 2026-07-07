<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRateController extends Controller
{
    public function index(): View
    {
        $rates = TaxRate::orderBy('country_code')->orderBy('type')->paginate(30);

        return view('admin.tax-rates.index', compact('rates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_code'   => ['required', 'string', 'size:2'],
            'name'           => ['required', 'string', 'max:100'],
            'rate_percent'   => ['required', 'numeric', 'min:0', 'max:100'],
            'type'           => ['required', 'string', 'max:50'],
            'is_active'      => ['boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);

        TaxRate::create($validated);

        return back()->with('status', 'Sazba daně přidána.');
    }

    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'rate_percent'   => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active'      => ['boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $taxRate->update($validated);

        return back()->with('status', 'Sazba daně aktualizována.');
    }

    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        $taxRate->delete();

        return back()->with('status', 'Sazba daně smazána.');
    }
}
