<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Dns\Enums\DnsZoneStatus;
use App\Domains\Dns\Models\DnsZone;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DnsZoneController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        return view('panel.dns-manager.index', [
            'zones' => $customer->dnsZones()->withCount('records')->latest('id')->get(),
        ]);
    }

    public function show(Request $request, DnsZone $dnsZone): View
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $dnsZone->customer_id !== $customer->id, 403);

        return view('panel.dns-manager.show', [
            'zone'    => $dnsZone,
            'records' => $dnsZone->records()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/'],
        ]);

        $domain = strtolower(trim((string) $validated['domain']));

        if ($customer->dnsZones()->where('domain', $domain)->exists()) {
            return back()->withErrors(['domain' => 'Tato doména je již přidána.'])->withInput();
        }

        $zone = $customer->dnsZones()->create([
            'domain'   => $domain,
            'status'   => DnsZoneStatus::Pending,
            'provider' => 'mock',
        ]);

        activity('dns')
            ->performedOn($zone)
            ->causedBy($request->user())
            ->withProperties(['domain' => $domain])
            ->log('dns.zone_created');

        return redirect()->route('panel.dns-manager.show', $zone)->with('status', 'DNS zóna přidána. Namiřte NS záznamy na naše servery.');
    }

    public function destroy(Request $request, DnsZone $dnsZone): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $dnsZone->customer_id !== $customer->id, 403);

        $domain = $dnsZone->domain;
        $dnsZone->delete();

        activity('dns')
            ->causedBy($request->user())
            ->withProperties(['domain' => $domain])
            ->log('dns.zone_deleted');

        return redirect()->route('panel.dns-manager.index')->with('status', "DNS zóna {$domain} odstraněna.");
    }
}
