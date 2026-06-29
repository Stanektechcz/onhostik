<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DnsController extends Controller
{
    public function show(DomainRegistration $domain): View
    {
        $this->authorize('view', $domain);

        $records = $this->fetchRecords($domain->fqdn());
        $isMock  = config('provisioning.mock_mode', true);

        return view('panel.dns.show', compact('domain', 'records', 'isMock'));
    }

    public function store(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('view', $domain);

        $validated = $request->validate([
            'type'  => ['required', 'in:A,AAAA,CNAME,MX,TXT,NS,CAA'],
            'name'  => ['required', 'string', 'max:255'],
            'rdata' => ['required', 'string', 'max:512'],
            'ttl'   => ['nullable', 'integer', 'min:60', 'max:86400'],
            'prio'  => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $integration = $this->getIntegration();

        if ($integration !== null && !config('provisioning.mock_mode', true)) {
            $client = new WedosWapiClient($integration);
            $client->upsertDnsRecord($domain->fqdn(), [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'rdata' => $validated['rdata'],
                'ttl'  => $validated['ttl'] ?? 3600,
                'prio' => $validated['prio'] ?? 0,
            ]);
        }

        $mockNote = config('provisioning.mock_mode', true) ? ' (mock)' : '';
        return back()->with('status', "DNS záznam {$validated['type']} byl uložen{$mockNote}.");
    }

    /** @return list<array<string, mixed>> */
    private function fetchRecords(string $fqdn): array
    {
        $integration = $this->getIntegration();

        if ($integration === null || config('provisioning.mock_mode', true)) {
            return $this->mockRecords($fqdn);
        }

        try {
            $client   = new WedosWapiClient($integration);
            $info     = $client->getDomainInfo($fqdn);
            $dnsRows  = $info['dns_rows'] ?? null;
            return is_array($dnsRows) ? array_values($dnsRows) : $this->mockRecords($fqdn);
        } catch (\Throwable) {
            return $this->mockRecords($fqdn);
        }
    }

    private function getIntegration(): ?IntegrationSetting
    {
        return IntegrationSetting::where('provider', 'wedos')
            ->where('is_active', true)
            ->first();
    }

    /** @return list<array<string, mixed>> */
    private function mockRecords(string $fqdn): array
    {
        return [
            ['type' => 'A',   'name' => '@',      'rdata' => '37.27.65.82',                         'ttl' => 3600,  'prio' => 0],
            ['type' => 'A',   'name' => 'www',    'rdata' => '37.27.65.82',                         'ttl' => 3600,  'prio' => 0],
            ['type' => 'A',   'name' => 'mail',   'rdata' => '37.27.65.82',                         'ttl' => 3600,  'prio' => 0],
            ['type' => 'MX',  'name' => '@',      'rdata' => "mail.{$fqdn}.",                       'ttl' => 3600,  'prio' => 10],
            ['type' => 'TXT', 'name' => '@',      'rdata' => 'v=spf1 include:onhost.cz ~all',       'ttl' => 3600,  'prio' => 0],
            ['type' => 'TXT', 'name' => '_dmarc', 'rdata' => 'v=DMARC1; p=none; rua=mailto:dmarc@onhost.cz', 'ttl' => 3600, 'prio' => 0],
            ['type' => 'NS',  'name' => '@',      'rdata' => 'ns1.onhost.cz.',                      'ttl' => 86400, 'prio' => 0],
            ['type' => 'NS',  'name' => '@',      'rdata' => 'ns2.onhost.cz.',                      'ttl' => 86400, 'prio' => 0],
        ];
    }
}
