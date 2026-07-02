<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\GameServerPreset;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameServerPresetController extends Controller
{
    public function index(): View
    {
        return view('admin.game-presets', [
            'presets' => GameServerPreset::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.game-preset-form', ['preset' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validate($request);

        $preset = GameServerPreset::create($validated);

        activity('game_preset')
            ->performedOn($preset)
            ->causedBy($request->user())
            ->withProperties(['game_slug' => $preset->game_slug, 'egg_id' => $preset->egg_id])
            ->log('game_preset.created');

        return redirect()->route('admin.game-presets.index')->with('status', 'Preset byl přidán.');
    }

    public function edit(GameServerPreset $gamePreset): View
    {
        return view('admin.game-preset-form', ['preset' => $gamePreset]);
    }

    public function update(Request $request, GameServerPreset $gamePreset): RedirectResponse
    {
        $validated = $this->validate($request, $gamePreset->id);

        $gamePreset->update($validated);

        activity('game_preset')
            ->performedOn($gamePreset)
            ->causedBy($request->user())
            ->withProperties(['game_slug' => $gamePreset->game_slug])
            ->log('game_preset.updated');

        return redirect()->route('admin.game-presets.edit', $gamePreset)->with('status', 'Preset byl uložen.');
    }

    public function destroy(Request $request, GameServerPreset $gamePreset): RedirectResponse
    {
        activity('game_preset')
            ->performedOn($gamePreset)
            ->causedBy($request->user())
            ->withProperties(['game_slug' => $gamePreset->game_slug])
            ->log('game_preset.deleted');

        $gamePreset->delete();

        return redirect()->route('admin.game-presets.index')->with('status', 'Preset byl smazán.');
    }

    /** @return array<string, mixed> */
    private function validate(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'game_slug'          => ['required', 'string', 'max:60', 'alpha_dash',
                                     Rule::unique('game_server_presets', 'game_slug')->ignore($ignoreId)],
            'description'        => ['nullable', 'string', 'max:500'],
            'nest_id'            => ['required', 'integer', 'min:1', 'max:9999'],
            'egg_id'             => ['required', 'integer', 'min:1', 'max:9999'],
            'default_memory_mb'  => ['required', 'integer', 'min:128', 'max:131072'],
            'default_disk_mb'    => ['required', 'integer', 'min:512', 'max:2097152'],
            'default_cpu_limit'  => ['required', 'integer', 'min:1', 'max:800'],
            'default_swap_mb'    => ['nullable', 'integer', 'min:0', 'max:16384'],
            'default_io_weight'  => ['nullable', 'integer', 'min:10', 'max:1000'],
            'docker_image'       => ['nullable', 'string', 'max:255'],
            'startup'            => ['nullable', 'string', 'max:1000'],
            'environment_json'   => ['nullable', 'string'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        // Parse environment JSON textarea → array
        $envJson = $validated['environment_json'] ?? null;
        unset($validated['environment_json']);

        if (! empty($envJson)) {
            $decoded = json_decode($envJson, true);
            $validated['environment'] = is_array($decoded) ? $decoded : null;
        } else {
            $validated['environment'] = null;
        }

        $validated['is_active']         = $request->boolean('is_active');
        $validated['default_swap_mb']   = (int) ($validated['default_swap_mb'] ?? 0);
        $validated['default_io_weight'] = (int) ($validated['default_io_weight'] ?? 500);
        $validated['sort_order']        = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }
}
