<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\PriceChangeNotification;
use Illuminate\View\View;

class PriceChangeNotificationController extends Controller
{
    public function index(): View
    {
        $notifications = PriceChangeNotification::where('status', 'sent')
            ->orderByDesc('effective_from')
            ->paginate(15);

        return view('panel.price-change-notifications.index', compact('notifications'));
    }
}
