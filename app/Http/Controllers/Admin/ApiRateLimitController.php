<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Api\Models\ApiTokenRateLimit;
use App\Domains\Api\Services\ApiRateLimitService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiRateLimitController extends Controller
{
    public function __construct(private readonly ApiRateLimitService $service)
    {
    }

    public function index(): View
    {
        $summary       = $this->service->summary();
        $tokenStatuses = $this->service->activeTokenStatus();
        $configured    = ApiTokenRateLimit::orderBy('token_id')->get();

        return view('admin.api-rate-limits', [
            'summary'       => $summary,
            'tokenStatuses' => $tokenStatuses,
            'configured'    => $configured,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token_id'            => ['required', 'integer', 'min:1'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'requests_per_hour'   => ['required', 'integer', 'min:1', 'max:100000'],
            'requests_per_day'    => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $this->service->setLimits((int) $data['token_id'], [
            'requests_per_minute' => (int) $data['requests_per_minute'],
            'requests_per_hour'   => (int) $data['requests_per_hour'],
            'requests_per_day'    => (int) $data['requests_per_day'],
        ]);

        return redirect()->route('admin.api-rate-limits.index')
            ->with('success', "Limity pro token #{$data['token_id']} byly nastaveny.");
    }

    public function destroy(ApiTokenRateLimit $apiRateLimit): RedirectResponse
    {
        $apiRateLimit->delete();

        return redirect()->route('admin.api-rate-limits.index')
            ->with('success', 'Konfigurace limitů byla odebrána (použijí se výchozí limity).');
    }
}
