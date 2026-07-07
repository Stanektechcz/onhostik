<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRateApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRateApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = TaxRateApplication::with(['taxRate', 'customer'])
            ->when($request->invoice_id, fn($q) => $q->where('invoice_id', $request->invoice_id))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.tax-rate-applications.index', compact('applications'));
    }
}
