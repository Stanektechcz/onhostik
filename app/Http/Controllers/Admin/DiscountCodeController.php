<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Services\PromoCodeService;
use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiscountCodeController extends Controller
{
    public function __construct(private readonly PromoCodeService $promoCodeService) {}

    public function index(Request $request): View
    {
        $codes = DiscountCode::with('createdBy')
            ->when($request->string('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%"))
            ->when($request->input('status') === 'active', fn ($q) => $q->valid())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = $this->promoCodeService->stats();

        return view('admin.discount-codes', compact('codes', 'stats'));
    }

    public function show(DiscountCode $code): View
    {
        $usages  = $this->promoCodeService->usageHistory($code);
        $summary = $code->usageStats();

        return view('admin.discount-codes-show', compact('code', 'usages', 'summary'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'                  => ['nullable', 'string', 'max:32', 'alpha_num'],
            'type'                  => ['required', 'in:percent,fixed'],
            'value'                 => ['required', 'numeric', 'min:0.01'],
            'currency'              => ['nullable', 'string', 'in:CZK,EUR,USD'],
            'max_uses'              => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'min_order_haler'       => ['nullable', 'integer', 'min:0'],
            'expires_at'            => ['nullable', 'date', 'after:now'],
            'description'           => ['nullable', 'string', 'max:255'],
        ]);

        $code = strtoupper($validated['code'] ?? Str::upper(Str::random(8)));

        if ($validated['type'] === 'percent' && $validated['value'] > 100) {
            return back()->withErrors(['value' => 'Procentuální sleva nemůže být větší než 100 %.']);
        }

        DiscountCode::create([
            'code'                  => $code,
            'type'                  => $validated['type'],
            'value'                 => $validated['value'],
            'currency'              => $validated['type'] === 'fixed' ? ($validated['currency'] ?? 'CZK') : null,
            'max_uses'              => $validated['max_uses'] ?? null,
            'max_uses_per_customer' => $validated['max_uses_per_customer'] ?? null,
            'min_order_haler'       => $validated['min_order_haler'] ?? 0,
            'expires_at'            => $validated['expires_at'] ?? null,
            'description'           => $validated['description'] ?? null,
            'is_active'             => true,
            'created_by_user_id'    => auth()->id(),
            'source'                => 'admin',
        ]);

        return back()->with('status', "Slevový kód '{$code}' byl vytvořen.");
    }

    public function toggle(DiscountCode $code): RedirectResponse
    {
        $code->update(['is_active' => !$code->is_active]);

        return back()->with('status', $code->is_active
            ? "Kód {$code->code} byl aktivován."
            : "Kód {$code->code} byl deaktivován.");
    }

    public function destroy(DiscountCode $code): RedirectResponse
    {
        $code->delete();

        return back()->with('status', "Slevový kód '{$code->code}' byl smazán.");
    }
}
