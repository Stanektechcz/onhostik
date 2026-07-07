<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCreditTransferController extends Controller
{
    public function __construct(private readonly CreditLedger $ledger) {}

    public function index(): View
    {
        $customers = Customer::orderBy('company_name')->get(['id', 'company_name', 'preferred_currency']);

        $balances = [];
        foreach ($customers as $customer) {
            $balances[$customer->id] = $this->ledger->getBalance($customer)->getMinorAmount()->toInt();
        }

        return view('admin.credit-transfer.index', compact('customers', 'balances'));
    }

    public function transfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_customer_id' => ['required', 'integer', 'exists:customers,id', 'different:to_customer_id'],
            'to_customer_id'   => ['required', 'integer', 'exists:customers,id'],
            'amount_haler'     => ['required', 'integer', 'min:100'],
            'note'             => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Customer $from */
        $from = Customer::findOrFail($validated['from_customer_id']);
        /** @var Customer $to */
        $to = Customer::findOrFail($validated['to_customer_id']);

        $amount = Money::ofMinor($validated['amount_haler'], $from->preferred_currency->value);
        $balance = $this->ledger->getBalance($from);

        if ($balance->getMinorAmount()->toInt() < $validated['amount_haler']) {
            return back()->withErrors(['amount_haler' => 'Zákazník nemá dostatek kreditu.']);
        }

        $adminId = (int) $request->user()->id;
        $note = $validated['note'] ?? 'Převod kreditu';

        $this->ledger->deduct($from, $amount, 'Převod kreditu zákazníkovi #' . $to->id . ' — ' . $note);
        $this->ledger->deposit($to, $amount, 'Přijatý kredit od zákazníka #' . $from->id . ' — ' . $note, createdBy: $adminId);

        activity('credit')
            ->withProperties([
                'from'         => $from->id,
                'to'           => $to->id,
                'amount_haler' => $validated['amount_haler'],
                'note'         => $note,
            ])
            ->log('credit.transfer');

        return back()->with('status', 'Kredit ' . number_format($validated['amount_haler'] / 100, 2) . ' Kč převeden.');
    }
}
