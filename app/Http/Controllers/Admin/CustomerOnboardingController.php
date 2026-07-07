<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerOnboardingController extends Controller
{
    public function show(Customer $customer): View
    {
        $user = $customer->user;

        $checklist = [
            [
                'label' => 'Profil vyplněn (jméno, e-mail)',
                'done'  => $user !== null && $user->name !== '' && $user->email !== '',
            ],
            [
                'label' => 'Fakturační údaje (adresa)',
                'done'  => $customer->billingAddress() !== null || $customer->company_name !== null,
            ],
            [
                'label' => 'Dvoufaktorové ověření aktivováno',
                'done'  => $user?->two_factor_confirmed_at !== null,
            ],
            [
                'label' => 'První faktura zaplacena',
                'done'  => DB::table('invoices')
                    ->where('customer_id', $customer->id)
                    ->where('status', 'paid')
                    ->exists(),
            ],
            [
                'label' => 'Aktivní služba',
                'done'  => Service::where('customer_id', $customer->id)
                    ->where('status', 'active')
                    ->exists(),
            ],
            [
                'label' => 'První tiket vytvořen',
                'done'  => DB::table('support_tickets')
                    ->where('customer_id', $customer->id)
                    ->exists(),
            ],
        ];

        $progress = (int) round(
            collect($checklist)->filter(fn ($item) => $item['done'])->count()
            / count($checklist) * 100
        );

        return view('admin.customer-onboarding', compact('customer', 'checklist', 'progress'));
    }
}
