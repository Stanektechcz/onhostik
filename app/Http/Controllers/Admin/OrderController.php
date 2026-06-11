<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('admin.orders', [
            'orders' => Order::query()
                ->with(['customer', 'items'])
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
