<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('panel.orders.index');
    }

    public function create(): View
    {
        return view('panel.orders.create');
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        return view('panel.orders.show', ['order' => $order]);
    }
}
