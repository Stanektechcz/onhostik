<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceWindow;
use Illuminate\Contracts\View\View;

class MaintenanceWindowController extends Controller
{
    public function index(): View
    {
        $windows = MaintenanceWindow::whereIn('status', ['scheduled', 'in_progress'])
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->paginate(10);

        return view('panel.maintenance-windows.index', compact('windows'));
    }
}
