<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Dns\Enums\DnsRecordType;
use App\Domains\Dns\Models\DnsRecord;
use App\Domains\Dns\Models\DnsZone;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DnsRecordController extends Controller
{
    public function store(Request $request, DnsZone $dnsZone): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $dnsZone->customer_id !== $customer->id, 403);

        $types = array_column(DnsRecordType::cases(), 'value');

        $validated = $request->validate([
            'type'     => ['required', 'string', 'in:' . implode(',', $types)],
            'name'     => ['required', 'string', 'max:253'],
            'content'  => ['required', 'string', 'max:1024'],
            'ttl'      => ['required', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $type = DnsRecordType::from($validated['type']);

        $record = $dnsZone->records()->create([
            'type'     => $type,
            'name'     => trim((string) $validated['name']),
            'content'  => trim((string) $validated['content']),
            'ttl'      => (int) $validated['ttl'],
            'priority' => $type->hasPriority() ? ($validated['priority'] ?? null) : null,
        ]);

        activity('dns')
            ->performedOn($dnsZone)
            ->causedBy($request->user())
            ->withProperties(['record_id' => $record->id, 'type' => $type->value, 'name' => $record->name])
            ->log('dns.record_created');

        return back()->with('status', "Záznam {$type->value} přidán.");
    }

    public function update(Request $request, DnsZone $dnsZone, DnsRecord $dnsRecord): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $dnsZone->customer_id !== $customer->id, 403);
        abort_if($dnsRecord->dns_zone_id !== $dnsZone->id, 403);

        $validated = $request->validate([
            'content'  => ['required', 'string', 'max:1024'],
            'ttl'      => ['required', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $dnsRecord->update([
            'content'  => trim((string) $validated['content']),
            'ttl'      => (int) $validated['ttl'],
            'priority' => $dnsRecord->type->hasPriority() ? ($validated['priority'] ?? null) : null,
        ]);

        activity('dns')
            ->performedOn($dnsZone)
            ->causedBy($request->user())
            ->withProperties(['record_id' => $dnsRecord->id, 'type' => $dnsRecord->type->value])
            ->log('dns.record_updated');

        return back()->with('status', 'Záznam upraven.');
    }

    public function destroy(Request $request, DnsZone $dnsZone, DnsRecord $dnsRecord): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $dnsZone->customer_id !== $customer->id, 403);
        abort_if($dnsRecord->dns_zone_id !== $dnsZone->id, 403);

        $dnsRecord->delete();

        activity('dns')
            ->performedOn($dnsZone)
            ->causedBy($request->user())
            ->withProperties(['type' => $dnsRecord->type->value, 'name' => $dnsRecord->name])
            ->log('dns.record_deleted');

        return back()->with('status', 'Záznam odstraněn.');
    }
}
