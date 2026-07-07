<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Api\Models\ApiUsageLog;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $user   = $request->user();
        $tokens = $user?->tokens()->latest()->get() ?? collect();

        $tokenIds = $tokens->pluck('id');

        // All-time totals per token
        $stats = ApiUsageLog::query()
            ->selectRaw('token_id, COUNT(*) as total, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->whereIn('token_id', $tokenIds)
            ->groupBy('token_id')
            ->get()
            ->keyBy('token_id');

        // Last 7 days per token
        $recent = ApiUsageLog::query()
            ->selectRaw('token_id, COUNT(*) as recent_total')
            ->whereIn('token_id', $tokenIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('token_id')
            ->get()
            ->keyBy('token_id');

        return view('panel.api-tokens', compact('tokens', 'stats', 'recent'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'abilities'   => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:' . implode(',', TokenController::ALLOWED_ABILITIES)],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->tokens()->count() >= 5) {
            return back()->withErrors(['name' => 'Maximální počet tokenů (5) byl dosažen.']);
        }

        $abilities = $validated['abilities'] ?? ['read'];
        if (! in_array('read', $abilities, true)) {
            $abilities[] = 'read';
        }

        $plainToken = $user->createToken($validated['name'], $abilities)->plainTextToken;

        return back()->with('new_token', $plainToken)
                     ->with('status', 'API token byl vytvořen. Zkopírujte ho — nebude znovu zobrazen.');
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()?->tokens()->where('id', $tokenId)->delete();

        return back()->with('status', 'API token byl odstraněn.');
    }
}
