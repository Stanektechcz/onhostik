<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EmailDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $deliveries = EmailDelivery::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('panel.email-deliveries.index', compact('deliveries'));
    }
}
