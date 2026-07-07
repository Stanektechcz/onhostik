<?php
declare(strict_types=1);
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\UsageAlertConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsageAlertConfigController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $alerts = UsageAlertConfig::where('user_id', $userId)->with(['service'])->get();
        $metrics = ['disk', 'bandwidth', 'cpu', 'ram'];
        return view('panel.usage-alert-configs.index', compact('alerts', 'metrics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id'        => ['required', 'integer'],
            'metric'            => ['required', 'in:disk,bandwidth,cpu,ram'],
            'threshold_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active'         => ['boolean'],
        ]);

        UsageAlertConfig::updateOrCreate(
            [
                'service_id' => $validated['service_id'],
                'user_id'    => $request->user()->id,
                'metric'     => $validated['metric'],
            ],
            [
                'threshold_percent' => $validated['threshold_percent'],
                'is_active'         => $validated['is_active'] ?? true,
            ]
        );

        return back()->with('status', 'Upozornění nastaveno.');
    }

    public function destroy(Request $request, UsageAlertConfig $usageAlertConfig): RedirectResponse
    {
        abort_unless($usageAlertConfig->user_id === $request->user()->id, 403);

        $usageAlertConfig->delete();

        return back()->with('status', 'Upozornění smazáno.');
    }
}
