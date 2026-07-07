<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRateLimitConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiRateLimitConfigController extends Controller
{
    public function index(): View
    {
        $configs = ApiRateLimitConfig::with('customer')
            ->orderByDesc('updated_at')
            ->paginate(30);

        return view('admin.api-rate-limit.index', compact('configs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'         => ['nullable', 'integer', 'exists:customers,id'],
            'scope'               => ['required', 'string', 'max:50'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'requests_per_day'    => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_active'           => ['boolean'],
            'note'                => ['nullable', 'string', 'max:255'],
        ]);

        ApiRateLimitConfig::updateOrCreate(
            ['customer_id' => $validated['customer_id'] ?? null, 'scope' => $validated['scope']],
            $validated
        );

        return back()->with('status', 'Konfigurace rate limitu uložena.');
    }

    public function destroy(ApiRateLimitConfig $config): RedirectResponse
    {
        $config->delete();

        return back()->with('status', 'Konfigurace smazána.');
    }
}
