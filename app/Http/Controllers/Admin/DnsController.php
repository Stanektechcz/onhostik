<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Dns\Models\DnsZone;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DnsController extends Controller
{
    public function index(): View
    {
        $zones = DnsZone::query()
            ->with('customer')
            ->withCount('records')
            ->latest('id')
            ->paginate(30);

        return view('admin.dns.index', compact('zones'));
    }

    public function show(DnsZone $dnsZone): View
    {
        $dnsZone->load('customer');

        return view('admin.dns.show', [
            'zone'    => $dnsZone,
            'records' => $dnsZone->records()->orderBy('type')->orderBy('name')->get(),
        ]);
    }
}
