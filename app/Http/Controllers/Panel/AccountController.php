<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function profile(Request $request): View
    {
        $user = $request->user();
        $customer = $user?->customer;

        return view('panel.account.profile', compact('user', 'customer'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user->update(['name' => $validated['name']]);

        activity()->causedBy($user)->log('profile_name_changed');

        return back()->with('status', __('panel.account.profile_saved'));
    }

    public function billing(Request $request): View
    {
        $customer = $this->customer($request);

        return view('panel.account.billing', [
            'customer' => $customer,
            'address'  => $customer->billingAddress(),
        ]);
    }

    /**
     * Billing details upsert — required before a tax document can be
     * issued (street + city + zip + name). Changes are audit-logged by
     * the Customer model's LogsActivity configuration.
     */
    public function updateBilling(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'                => ['required', Rule::in(['person', 'company'])],
            'company_name'        => ['nullable', 'string', 'max:150', 'required_if:type,company'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'vat_number'          => ['nullable', 'string', 'max:20'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'country_code'        => ['required', 'string', 'size:2'],
            'street'              => ['required', 'string', 'max:150'],
            'city'                => ['required', 'string', 'max:100'],
            'zip'                 => ['required', 'string', 'max:12'],
        ]);

        $customer = $this->customer($request);

        $customer->update([
            'type'                => $validated['type'],
            'company_name'        => $validated['company_name'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'vat_number'          => $validated['vat_number'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'country_code'        => mb_strtoupper($validated['country_code']),
        ]);

        $customer->addresses()->updateOrCreate(
            ['type' => 'billing'],
            [
                'street'       => $validated['street'],
                'city'         => $validated['city'],
                'zip'          => $validated['zip'],
                'country_code' => mb_strtoupper($validated['country_code']),
                'is_primary'   => true,
            ],
        );

        return back()->with('status', __('panel.account.billing_saved'));
    }

    public function security(Request $request): View
    {
        $user = $request->user();

        /* two_factor_secret set = setup started (even before confirmation) */
        $hasTwoFactorSecret  = !is_null($user?->two_factor_secret);
        $twoFactorConfirmed  = !is_null($user?->two_factor_confirmed_at);
        /* hasEnabledTwoFactorAuthentication() requires confirmed_at when confirm:true */
        $twoFactorEnabled    = $user?->hasEnabledTwoFactorAuthentication() ?? false;

        /* Show QR code when secret exists but user hasn't confirmed yet */
        $showingQrCode = $hasTwoFactorSecret && !$twoFactorConfirmed;

        $recoveryCodes = [];
        if ($twoFactorEnabled && $twoFactorConfirmed) {
            try {
                $recoveryCodes = json_decode(
                    decrypt((string) $user->two_factor_recovery_codes),
                    true,
                ) ?? [];
            } catch (\Throwable) {
                $recoveryCodes = [];
            }
        }

        return view('panel.account.security', compact(
            'user',
            'twoFactorEnabled',
            'twoFactorConfirmed',
            'showingQrCode',
            'hasTwoFactorSecret',
            'recoveryCodes',
        ));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Současné heslo není správné.']);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        activity()->causedBy($user)->log('password_changed');

        return back()->with('status', 'Heslo bylo úspěšně změněno.');
    }

    public function requestDeletion(Request $request, TicketService $tickets): RedirectResponse
    {
        $user     = $request->user();
        $customer = $user?->customer;

        abort_if($user === null || $customer === null, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reason = $validated['reason'] ?? 'Zákazník neuvedl důvod.';

        $ticket = $tickets->open(
            $customer,
            $user,
            'Žádost o smazání účtu — GDPR čl. 17',
            "Zákazník žádá o smazání účtu a všech osobních údajů.\n\nDůvod: {$reason}\n\nE-mail: {$user->email}",
            TicketPriority::High,
            'billing',
        );

        activity()
            ->causedBy($user)
            ->withProperties(['ticket_id' => $ticket->id, 'reason' => $reason])
            ->log('account.deletion_requested');

        return redirect()
            ->route('panel.support.show', $ticket)
            ->with('status', __('panel.account.deletion_requested'));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
