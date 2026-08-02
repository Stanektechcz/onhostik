<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Shared\Models\FeatureFlag;
use App\Domains\Shared\Services\Features;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin management of feature flags — enable/disable, percentage rollout and a
 * per-customer allow-list, all without a deploy (audit 500 #383).
 */
class FeatureFlagController extends Controller
{
    public function __construct(private readonly Features $features) {}

    public function index(): View
    {
        return view('admin.feature-flags.index', [
            'flags' => FeatureFlag::query()->orderBy('key')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key'             => ['required', 'string', 'max:64', 'regex:/^[a-z0-9\-_.]+$/', 'unique:feature_flags,key'],
            'label'           => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'rollout_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_enabled'      => ['boolean'],
        ]);

        FeatureFlag::create([
            'key'             => $data['key'],
            'label'           => $data['label'],
            'description'     => $data['description'] ?? null,
            'rollout_percent' => (int) ($data['rollout_percent'] ?? 100),
            'is_enabled'      => $request->boolean('is_enabled'),
        ]);

        $this->features->forget($data['key']);

        return back()->with('status', 'Feature flag byl vytvořen.');
    }

    public function update(Request $request, FeatureFlag $featureFlag): RedirectResponse
    {
        $data = $request->validate([
            'label'           => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'rollout_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'customer_ids'    => ['nullable', 'string', 'max:2000'],
            'is_enabled'      => ['boolean'],
        ]);

        $ids = array_values(array_filter(array_map(
            static fn (string $v): int => (int) trim($v),
            preg_split('/[\s,]+/', (string) ($data['customer_ids'] ?? '')) ?: [],
        )));

        $featureFlag->update([
            'label'           => $data['label'],
            'description'     => $data['description'] ?? null,
            'rollout_percent' => (int) ($data['rollout_percent'] ?? 100),
            'customer_ids'    => $ids === [] ? null : $ids,
            'is_enabled'      => $request->boolean('is_enabled'),
        ]);

        $this->features->forget($featureFlag->key);

        activity('feature-flag')
            ->performedOn($featureFlag)
            ->causedBy($request->user())
            ->withProperties([
                'key'             => $featureFlag->key,
                'is_enabled'      => $featureFlag->is_enabled,
                'rollout_percent' => $featureFlag->rollout_percent,
            ])
            ->log('feature_flag.updated');

        return back()->with('status', 'Feature flag byl aktualizován.');
    }

    public function destroy(FeatureFlag $featureFlag): RedirectResponse
    {
        $key = $featureFlag->key;
        $featureFlag->delete();
        $this->features->forget($key);

        return back()->with('status', 'Feature flag byl odstraněn.');
    }
}
