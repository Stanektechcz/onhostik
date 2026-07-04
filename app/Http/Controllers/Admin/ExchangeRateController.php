<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\ExchangeRate;
use App\Domains\Billing\Services\ExchangeRateService;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index(): View
    {
        $rates = [];
        foreach ([Currency::EUR, Currency::USD] as $currency) {
            $rates[$currency->value] = ExchangeRate::where('currency', $currency->value)
                ->orderByDesc('valid_from')
                ->first();
        }

        $history = ExchangeRate::orderByDesc('valid_from')->orderBy('currency')->limit(30)->get();

        return view('admin.exchange-rates.index', compact('rates', 'history'));
    }

    public function update(Request $request, string $currency, ExchangeRateService $service): RedirectResponse
    {
        $curr = Currency::tryFrom(strtoupper($currency));

        abort_if($curr === null || $curr === Currency::CZK, 404, 'Neplatná měna.');

        $validated = $request->validate([
            'rate' => ['required', 'numeric', 'min:0.01', 'max:9999'],
        ]);

        $service->setRate($curr, (float) $validated['rate']);

        return back()->with('status', "Kurz {$curr->value}/CZK byl nastaven na {$validated['rate']}.");
    }
}
