<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Http\Controllers\Controller;
use Brick\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        return view('admin.customers', [
            'customers' => Customer::query()
                ->with('user')
                ->withCount(['orders', 'services'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('email', 'like', "%{$search}%")
                          ->orWhere('company_name', 'like', "%{$search}%")
                          ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                    });
                })
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'search'         => $search,
            'totalCount'     => Customer::count(),
            'companyCount'   => Customer::where('type', 'company')->count(),
            'personCount'    => Customer::where('type', 'person')->count(),
            'withServiceCount' => Customer::has('services')->count(),
        ]);
    }

    /** Stream customers as CSV. */
    public function export(Request $request): StreamedResponse
    {
        $search = $request->string('q')->toString();
        $type   = $request->string('type')->toString();

        $query = Customer::query()
            ->with('user')
            ->withCount(['orders', 'services'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sq) use ($search): void {
                    $sq->where('email', 'like', "%{$search}%")
                       ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->orderBy('id');

        $filename = 'zakaznici-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Typ', 'Jméno / Firma', 'E-mail', 'IČO', 'DIČ',
                'Země', 'Měna', 'Objednávky', 'Služby', 'Registrace',
            ], ';');

            $query->chunk(200, function ($customers) use ($out): void {
                foreach ($customers as $customer) {
                    fputcsv($out, [
                        $customer->id,
                        $customer->type === 'company' ? 'firma' : 'osoba',
                        $customer->company_name ?: ($customer->user?->name ?: ''),
                        $customer->email,
                        $customer->registration_number ?: '',
                        $customer->vat_number ?: '',
                        $customer->country_code ?: '',
                        $customer->preferred_currency->value,
                        $customer->orders_count,
                        $customer->services_count,
                        $customer->created_at?->format('d.m.Y') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function show(Customer $customer, CreditLedger $ledger): View
    {
        return view('admin.customer-show', [
            'customer' => $customer->load(['user', 'addresses']),
            'orders'   => $customer->orders()->latest('id')->limit(10)->get(),
            'invoices' => $customer->invoices()->latest('id')->limit(10)->get(),
            'payments' => $customer->payments()->with('invoice')->latest('id')->limit(10)->get(),
            'services' => $customer->services()->with('product')->latest('id')->limit(10)->get(),
            'domains'  => DomainRegistration::query()
                ->whereHas('service', fn ($query) => $query->where('customer_id', $customer->id))
                ->latest('id')
                ->limit(10)
                ->get(),
            'balance' => $ledger->getBalance($customer),
            'ledger'  => $ledger->getHistory($customer, 10),
            'tickets' => $customer->supportTickets()->latest('id')->limit(5)->get(),
        ]);
    }

    /**
     * Manual credit adjustment — signed amount, reason REQUIRED, runs
     * through the append-only ledger (admin id recorded on the row) and
     * is explicitly audit-logged on top of the ledger entry itself.
     */
    public function adjustCredit(Request $request, Customer $customer, CreditLedger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        try {
            $entry = $ledger->adjust(
                customer: $customer,
                amount: Money::of($validated['amount'], $customer->preferred_currency->value),
                description: 'Admin korekce: ' . $validated['reason'],
                createdBy: $admin->id,
            );
        } catch (InsufficientCreditException) {
            return back()->withErrors(['amount' => __('panel.billing.insufficient_credit')]);
        }

        activity('credit')
            ->performedOn($customer)
            ->causedBy($admin)
            ->withProperties([
                'credit_transaction_id' => $entry->id,
                'amount'                => $entry->amount->getMinorAmount()->toInt(),
                'reason'                => $validated['reason'],
            ])
            ->log('credit.admin_adjusted');

        return back()->with('status', __('panel.admin.credit_adjusted'));
    }
}
