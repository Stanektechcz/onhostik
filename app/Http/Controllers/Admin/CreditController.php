<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $customers = Customer::query()
            ->with('user')
            ->withSum('creditTransactions as balance_minor', 'amount')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%")
                      ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.credits', [
            'customers'    => $customers,
            'search'       => $search,
            'totalBalance' => (int) CreditTransaction::query()->sum('amount'),
        ]);
    }

    public function transactions(Request $request): View
    {
        $search   = $request->string('q')->toString();
        $typeFilter = $request->string('type')->toString();

        $transactions = CreditTransaction::query()
            ->with('customer.user')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('customer', function ($q) use ($search): void {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when($typeFilter !== '', fn ($q) => $q->where('type', $typeFilter))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.credit-transactions', [
            'transactions' => $transactions,
            'search'       => $search,
            'typeFilter'   => $typeFilter,
            'types'        => \App\Domains\Billing\Enums\CreditTransactionType::cases(),
        ]);
    }
}
