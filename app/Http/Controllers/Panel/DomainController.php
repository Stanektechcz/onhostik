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
}
