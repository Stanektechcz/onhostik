<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use App\Services\ViesVatValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ZipArchive;

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

        $vatWarning = null;
        $vatNumber  = $validated['vat_number'] ?? null;
        if ($vatNumber !== null && $vatNumber !== '') {
            $vies   = app(ViesVatValidator::class);
            $result = $vies->validate($vatNumber);
            if ($result === false) {
                $vatWarning = __('panel.account.vat_invalid');
            } elseif ($result === null) {
                $vatWarning = __('panel.account.vat_unavailable');
            }
        }

        $customer->update([
            'type'                => $validated['type'],
            'company_name'        => $validated['company_name'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'vat_number'          => $vatNumber,
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

        $redirect = back()->with('status', __('panel.account.billing_saved'));

        if ($vatWarning !== null) {
            $redirect = $redirect->with('warning', $vatWarning);
        }

        return $redirect;
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

        $tokens = $user?->tokens()->latest()->get() ?? collect();

        return view('panel.account.security', compact(
            'user',
            'twoFactorEnabled',
            'twoFactorConfirmed',
            'showingQrCode',
            'hasTwoFactorSecret',
            'recoveryCodes',
            'tokens',
        ));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', new StrongPassword(), 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Současné heslo není správné.']);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        activity()->causedBy($user)->log('password_changed');

        return back()->with('status', 'Heslo bylo úspěšně změněno.');
    }

    public function notificationPreferences(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $channels = ['mail', 'database'];
        $types    = ['renewal', 'invoice', 'payment', 'support', 'backup', 'monitor'];
        $prefs    = $user->notification_preferences ?? [];

        return view('panel.account.notification-preferences', compact('user', 'channels', 'types', 'prefs'));
    }

    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $channels = ['mail', 'database'];
        $types    = ['renewal', 'invoice', 'payment', 'support', 'backup', 'monitor'];

        // Build opt-out map: if a checkbox is missing from POST it is unchecked → user wants to opt out
        $prefs = [];
        foreach ($channels as $channel) {
            $submitted = $request->input($channel, []);
            $optOut    = array_values(array_diff($types, (array) $submitted));
            $prefs[$channel] = $optOut;
        }

        $user->update(['notification_preferences' => $prefs]);

        activity()->causedBy($user)->log('notification_preferences_updated');

        return back()->with('status', 'Předvolby notifikací byly uloženy.');
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

        // Store the request timestamp so the automated GDPR erasure command
        // can anonymise the account after the 30-day grace period.
        $user->update(['deletion_requested_at' => now()]);

        $ticket = $tickets->open(
            $customer,
            $user,
            'Žádost o smazání účtu — GDPR čl. 17',
            "Zákazník žádá o smazání účtu a všech osobních údajů.\n\nDůvod: {$reason}\n\nE-mail: {$user->email}\n\nAutomatická anonymizace proběhne po 30 dnech (php artisan gdpr:erase-requested).",
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

    /**
     * GDPR Article 20 — Right to Data Portability.
     * Returns a ZIP archive with the user's personal data as JSON files.
     */
    public function exportData(Request $request): Response
    {
        $user     = $request->user();
        $customer = $user?->customer;

        abort_if($user === null, 403);

        $tmpPath = sys_get_temp_dir() . '/onhost_dsar_' . $user->id . '_' . time() . '.zip';

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Profile
        $zip->addFromString('profile.json', json_encode([
            'name'       => $user->name,
            'email'      => $user->email,
            'created_at' => $user->created_at?->toISOString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');

        if ($customer !== null) {
            // Billing info (address from billing address relation, not direct columns)
            $billingAddr = $customer->billingAddress();
            $zip->addFromString('billing.json', json_encode([
                'company_name'        => $customer->company_name,
                'email'               => $customer->email,
                'phone'               => $customer->phone,
                'street'              => $billingAddr?->street,
                'city'                => $billingAddr?->city,
                'zip'                 => $billingAddr?->zip,
                'country_code'        => $customer->country_code,
                'registration_number' => $customer->registration_number,
                'vat_number'          => $customer->vat_number,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');

            // Orders
            $orders = $customer->orders()->with('items')->latest()->get()->map(fn ($o) => [
                'id'         => $o->id,
                'status'     => $o->status,
                'total'      => (string) $o->total,
                'created_at' => $o->created_at?->toISOString(),
            ]);
            $zip->addFromString('orders.json', json_encode($orders, JSON_PRETTY_PRINT) ?: '[]');

            // Invoices
            $invoices = $customer->invoices()->latest()->get()->map(fn ($i) => [
                'number'     => $i->number,
                'type'       => $i->type,
                'status'     => $i->status,
                'total'      => (string) $i->total,
                'due_date'   => $i->due_date,
                'paid_at'    => $i->paid_at?->toISOString(),
            ]);
            $zip->addFromString('invoices.json', json_encode($invoices, JSON_PRETTY_PRINT) ?: '[]');

            // Services
            $services = $customer->services()->withTrashed()->get()->map(fn ($s) => [
                'label'         => $s->label,
                'status'        => $s->status->value,
                'created_at'    => $s->created_at?->toISOString(),
                'next_due_date' => $s->next_due_date?->toISOString(),
            ]);
            $zip->addFromString('services.json', json_encode($services, JSON_PRETTY_PRINT) ?: '[]');

            // Support tickets
            $tickets = $customer->supportTickets()->latest()->get()->map(fn ($t) => [
                'subject'    => $t->subject,
                'status'     => $t->status,
                'created_at' => $t->created_at?->toISOString(),
                'closed_at'  => $t->closed_at?->toISOString(),
            ]);
            $zip->addFromString('support_tickets.json', json_encode($tickets, JSON_PRETTY_PRINT) ?: '[]');
        }

        // Notifications
        $notifications = $user->notifications()->latest()->get()->map(fn ($n) => [
            'type'       => class_basename($n->type),
            'read_at'    => $n->read_at?->toISOString(),
            'created_at' => $n->created_at?->toISOString(),
        ]);
        $zip->addFromString('notifications.json', json_encode($notifications, JSON_PRETTY_PRINT) ?: '[]');

        $zip->close();

        activity()->causedBy($user)->log('gdpr.data_export');

        $content  = (string) file_get_contents($tmpPath);
        @unlink($tmpPath);

        return response($content, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="onhost_data_export_' . now()->format('Ymd') . '.zip"',
            'Content-Length'      => strlen($content),
        ]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
