<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCancellation;
use Illuminate\Contracts\View\View;

class CancellationSurveyController extends Controller
{
    public function index(): View
    {
        $total = ServiceCancellation::count();

        $reasonLabels = ServiceCancellation::REASONS;

        // Counts per reason
        $byCancelReason = ServiceCancellation::query()
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get();

        // Recent 20
        $recent = ServiceCancellation::query()
            ->with(['service', 'user'])
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.cancellation-survey', compact('total', 'byCancelReason', 'reasonLabels', 'recent'));
    }
}
