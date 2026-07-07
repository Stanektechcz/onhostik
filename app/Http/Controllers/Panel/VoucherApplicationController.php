<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherApplicationController extends Controller
{
    public function index(): View
    {
        return view('panel.vouchers.apply');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $voucher = Voucher::where('code', $validated['code'])->first();

        if ($voucher === null) {
            return back()->withErrors(['code' => 'Voucher nenalezen.']);
        }

        if (!$voucher->isValid()) {
            return back()->withErrors(['code' => 'Voucher je neplatný nebo vypršel.']);
        }

        $voucher->increment('used_count');

        return back()->with('status', "Voucher {$voucher->code} byl úspěšně uplatněn.");
    }
}
