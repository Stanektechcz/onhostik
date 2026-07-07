<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\DashboardWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardWidgetController extends Controller
{
    private const AVAILABLE_WIDGETS = [
        'services'     => 'Moje služby',
        'invoices'     => 'Nedávné faktury',
        'tickets'      => 'Tickety',
        'credit'       => 'Kredit',
        'activity'     => 'Aktivita',
        'announcements'=> 'Oznámení',
    ];

    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $saved = DashboardWidget::where('user_id', $userId)
            ->orderBy('position')
            ->pluck('is_visible', 'widget_key')
            ->toArray();

        $widgets = [];
        foreach (self::AVAILABLE_WIDGETS as $key => $label) {
            $widgets[] = [
                'key'        => $key,
                'label'      => $label,
                'is_visible' => $saved[$key] ?? true,
            ];
        }

        return view('panel.dashboard-widgets.index', compact('widgets'));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widgets'             => ['required', 'array'],
            'widgets.*.key'       => ['required', 'string', 'in:' . implode(',', array_keys(self::AVAILABLE_WIDGETS))],
            'widgets.*.position'  => ['required', 'integer', 'min:0'],
            'widgets.*.is_visible'=> ['required', 'boolean'],
        ]);

        $userId = $request->user()->id;
        foreach ($validated['widgets'] as $widget) {
            DashboardWidget::updateOrCreate(
                ['user_id' => $userId, 'widget_key' => $widget['key']],
                ['position' => $widget['position'], 'is_visible' => $widget['is_visible']]
            );
        }

        return response()->json(['status' => 'ok']);
    }
}
