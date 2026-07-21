<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        $domains = DomainRegistration::query()
            ->whereHas('service', fn ($query) => $query->where('customer_id', $customer->id))
            ->with('service')
            ->latest('id')
            ->paginate(15);

        return view('panel.domains.index', ['domains' => $domains]);
    }

    public function show(DomainRegistration $domain): View
    {
        $this->authorize('view', $domain);

        return view('panel.domains.show', [
            'domain' => $domain->load('service.provisioningTasks'),
        ]);
    }

    public function toggleAutoRenew(DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('update', $domain);

        $domain->update(['auto_renew' => ! $domain->auto_renew]);

        $msg = $domain->auto_renew
            ? __('panel.domains.auto_renew_enabled')
            : __('panel.domains.auto_renew_disabled');

        return back()->with('status', $msg);
    }

    public function updateNameservers(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validate([
            'nameservers'   => ['required', 'array', 'min:1', 'max:4'],
            'nameservers.*' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/',
            ],
        ]);

        $nameservers = array_values(
            array_filter($validated['nameservers'], fn (string $ns) => $ns !== '')
        );

        $domain->update(['nameservers' => $nameservers]);

        activity('domain')
            ->performedOn($domain)
            ->causedBy($request->user())
            ->withProperties(['nameservers' => $nameservers])
            ->log('domain.nameservers_updated');

        return back()->with('status', __('panel.domains.nameservers_updated'));
    }

    /**
     * Point several domains at the same nameservers in one go (audit F90).
     *
     * Migrating DNS provider means touching every domain you own; doing that
     * one at a time is where records get missed.
     */
    public function bulkNameservers(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403);

        $validated = $request->validate([
            'domain_ids'    => ['required', 'array', 'min:1'],
            'domain_ids.*'  => ['required', 'integer'],
            'nameservers'   => ['required', 'array', 'min:1', 'max:4'],
            'nameservers.*' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/',
            ],
        ]);

        $nameservers = array_values(array_filter($validated['nameservers'], static fn (string $ns): bool => $ns !== ''));

        // Scope to the caller's own domains — never trust ids from the form.
        $domains = DomainRegistration::query()
            ->whereIn('id', $validated['domain_ids'])
            ->whereHas('service', fn ($q) => $q->where('customer_id', $customer->id))
            ->get();

        if ($domains->isEmpty()) {
            return back()->withErrors(['domain_ids' => 'Nebyla vybrána žádná vaše doména.']);
        }

        foreach ($domains as $domain) {
            $domain->update(['nameservers' => $nameservers]);

            activity('domain')
                ->performedOn($domain)
                ->causedBy($request->user())
                ->withProperties(['nameservers' => $nameservers, 'bulk' => true])
                ->log('domain.nameservers_updated');
        }

        return back()->with('status', "Nameservery byly změněny u {$domains->count()} domén.");
    }
}
