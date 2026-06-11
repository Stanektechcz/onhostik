<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.payments', [
            'payments' => Payment::query()
                ->with(['customer', 'invoice'])
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
