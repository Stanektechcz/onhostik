<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Actions\PauseDunningAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DunningController extends Controller
{
    public function index(): View
    {
        $now = now();

        return view('admin.dunning.index', [
            'invoices' => Invoice::query()
                ->where('status', InvoiceStatus::Overdue)
                ->with('customer.user')
                ->orderBy('due_date')
                ->paginate(25),
            'stats' => [
                'total'    => Invoice::where('status', InvoiceStatus::Overdue)->count(),
                'paused'   => Invoice::where('status', InvoiceStatus::Overdue)
                    ->where('dunning_paused_until', '>', $now)->count(),
                'stage1'   => Invoice::where('status', InvoiceStatus::Overdue)
                    ->whereBetween('due_date', [$now->copy()->subDays(3)->toDateString(), $now->copy()->subDays(1)->toDateString()])->count(),
                'stage3'   => Invoice::where('status', InvoiceStatus::Overdue)
                    ->whereBetween('due_date', [$now->copy()->subDays(7)->toDateString(), $now->copy()->subDays(4)->toDateString()])->count(),
                'critical' => Invoice::where('status', InvoiceStatus::Overdue)
                    ->where('due_date', '<', $now->copy()->subDays(7)->toDateString())->count(),
            ],
        ]);
    }

    public function pause(Invoice $invoice, Request $request, PauseDunningAction $action): RedirectResponse
    {
        $validated = $request->validate(['days' => 'required|integer|min:1|max:90']);

        $action->pause($invoice, $validated['days']);

        return back()->with('status', "Dunning pozastaven na {$validated['days']} dní.");
    }

    public function resume(Invoice $invoice, PauseDunningAction $action): RedirectResponse
    {
        $action->resume($invoice);

        return back()->with('status', 'Dunning byl obnoven.');
    }
}
