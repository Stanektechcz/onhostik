<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $vouchers = Voucher::when(
            $request->status_filter,
            fn($q) => $q->where('is_active', $request->status_filter === 'active')
        )
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'       => 'required|max:50|unique:vouchers,code',
            'type'       => 'required|in:credit,discount_percent,discount_fixed',
            'value'      => 'required|integer|min:1',
            'currency'   => 'nullable|max:3',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
            'is_active'  => 'boolean',
        ]);

        Voucher::create($validated);

        return back()->with('success', 'Voucher vytvořen.');
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $voucher->update(['is_active' => $validated['is_active']]);

        return back()->with('success', 'Voucher aktualizován.');
    }
}
