<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('admin.invoices', [
            'invoices' => Invoice::query()
                ->with('customer')
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
