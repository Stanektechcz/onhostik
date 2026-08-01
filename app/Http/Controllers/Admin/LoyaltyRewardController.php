<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for the redeemable loyalty reward catalog.
 */
class LoyaltyRewardController extends Controller
{
    public function index(): View
    {
        return view('admin.loyalty-rewards.index', [
            'rewards' => LoyaltyReward::query()->orderBy('sort_order')->orderBy('points_cost')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        LoyaltyReward::create($data);

        return back()->with('status', 'Odměna přidána do katalogu.');
    }

    public function update(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        $reward->update($this->validated($request));

        return back()->with('status', 'Odměna aktualizována.');
    }

    public function destroy(LoyaltyReward $reward): RedirectResponse
    {
        $reward->delete();

        return back()->with('status', 'Odměna odstraněna.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reward_czk'  => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        return [
            'name'                => $v['name'],
            'description'         => $v['description'] ?? null,
            'points_cost'         => (int) $v['points_cost'],
            'reward_type'         => 'credit_czk',
            'reward_value_halere' => (int) round(((float) $v['reward_czk']) * 100),
            'sort_order'          => (int) ($v['sort_order'] ?? 0),
            'is_active'           => (bool) ($v['is_active'] ?? true),
        ];
    }
}
