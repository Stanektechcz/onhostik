<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $query = EmailDelivery::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('recipient')) {
            $query->where('recipient', 'like', '%' . $request->input('recipient') . '%');
        }

        $deliveries = $query->paginate(30);

        return view('admin.email-deliveries.index', compact('deliveries'));
    }
}
