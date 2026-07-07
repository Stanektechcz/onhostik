<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceInstallment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceInstallmentController extends Controller
{
    public function index(): View
    {
        $installments = InvoiceInstallment::with('invoice', 'customer')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.invoice-installments.index', compact('installments'));
    }
}
