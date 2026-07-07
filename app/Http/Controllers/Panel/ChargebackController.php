<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Chargeback;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargebackController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $chargebacks = Chargeback::where('customer_id', $customerId)
            ->orderByDesc('received_at')
            ->paginate(15);

        return view('panel.chargebacks.index', compact('chargebacks'));
    }
}
