<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Models\CreditTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ExpiringCreditController extends Controller
{
    public function index(Request $request): View
    {
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $transactions = CreditTransaction::query()
            ->where('type', CreditTransactionType::Deposit)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->whereDoesntHave('expiryDeductions')
            ->with(['customer.user', 'expiryReminders'])
            ->orderBy('expires_at')
            ->paginate(50);

        $counts = [];
        foreach ([7, 30, 90] as $d) {
            $counts[$d] = CreditTransaction::query()
                ->where('type', CreditTransactionType::Deposit)
                ->whereNotNull('expires_at')
                ->where('expires_at', '>=', now())
                ->where('expires_at', '<=', now()->addDays($d))
                ->whereDoesntHave('expiryDeductions')
                ->count();
        }

        return view('admin.expiring-credits', [
            'transactions' => $transactions,
            'days'         => $days,
            'counts'       => $counts,
        ]);
    }
}
