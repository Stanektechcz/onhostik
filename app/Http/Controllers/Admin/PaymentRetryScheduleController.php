<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentRetrySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentRetryScheduleController extends Controller
{
    public function index(): View
    {
        $retries = PaymentRetrySchedule::with(['customer'])->orderByDesc('created_at')->paginate(20);
        return view('admin.payment-retry-schedules.index', compact('retries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id'  => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'retry_at'    => ['required', 'date', 'after:now'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        PaymentRetrySchedule::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Plán opakování vytvořen.');
    }

    public function destroy(PaymentRetrySchedule $paymentRetrySchedule): RedirectResponse
    {
        $paymentRetrySchedule->delete();
        return back()->with('status', 'Plán opakování smazán.');
    }
}
