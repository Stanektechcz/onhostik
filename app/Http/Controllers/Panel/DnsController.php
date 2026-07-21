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
    /**
     * One-click record sets for the mail providers customers actually use
     * (audit F91). Values are the providers' documented public endpoints.
     *
     * @var array<string, array{label: string, records: list<array<string, mixed>>}>
     */
    public const TEMPLATES = [
        'google_workspace' => [
            'label'   => 'Google Workspace',
            'records' => [
                ['type' => 'MX',  'name' => '@', 'rdata' => 'smtp.google.com.', 'ttl' => 3600, 'prio' => 1],
                ['type' => 'TXT', 'name' => '@', 'rdata' => 'v=spf1 include:_spf.google.com ~all', 'ttl' => 3600, 'prio' => 0],
            ],
        ],
        'microsoft_365' => [
            'label'   => 'Microsoft 365',
            'records' => [
                ['type' => 'TXT',   'name' => '@',                'rdata' => 'v=spf1 include:spf.protection.outlook.com -all', 'ttl' => 3600, 'prio' => 0],
                ['type' => 'CNAME', 'name' => 'autodiscover',     'rdata' => 'autodiscover.outlook.com.',                      'ttl' => 3600, 'prio' => 0],
            ],
        ],
        'onhost_mail' => [
            'label'   => 'OnHost mail',
            'records' => [
                ['type' => 'MX',  'name' => '@',      'rdata' => 'mail.onhost.cz.',                'ttl' => 3600, 'prio' => 10],
                ['type' => 'TXT', 'name' => '@',      'rdata' => 'v=spf1 include:onhost.cz ~all',  'ttl' => 3600, 'prio' => 0],
                ['type' => 'TXT', 'name' => '_dmarc', 'rdata' => 'v=DMARC1; p=none;',              'ttl' => 3600, 'prio' => 0],
            ],
        ],
    ];

    public function show(DomainRegistration $domain): View
    {
        $this->authorize('view', $domain);

        $records = $this->fetchRecords($domain->fqdn());
        $isMock  = config('provisioning.mock_mode', true);

        return view('panel.dns.show', [
            'domain'      => $domain,
            'records'     => $records,
            'isMock'      => $isMock,
            'templates'   => self::TEMPLATES,
            'dnssecKeys'  => $this->fetchDnssec($domain->fqdn()),
        ]);
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

    /**
     * Update one existing record (audit F83 — the editor could only ever add).
     *
     * WEDOS identifies records by row id, which is why the id travels in the
     * request rather than the URL: the records live in the registrar, not in
     * our database, so there is no local model to route-bind.
     */
    public function update(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('view', $domain);

        $validated = $request->validate([
            'row_id' => ['required', 'integer', 'min:1'],
            'type'   => ['required', 'in:A,AAAA,CNAME,MX,TXT,NS,CAA'],
            'name'   => ['required', 'string', 'max:255'],
            'rdata'  => ['required', 'string', 'max:512'],
            'ttl'    => ['nullable', 'integer', 'min:60', 'max:86400'],
            'prio'   => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $integration = $this->getIntegration();

        if ($integration !== null && ! config('provisioning.mock_mode', true)) {
            (new WedosWapiClient($integration))->upsertDnsRecord($domain->fqdn(), [
                'row_id' => (int) $validated['row_id'],
                'type'   => $validated['type'],
                'name'   => $validated['name'],
                'rdata'  => $validated['rdata'],
                'ttl'    => $validated['ttl'] ?? 3600,
                'prio'   => $validated['prio'] ?? 0,
            ]);
        }

        activity('domain')
            ->performedOn($domain)
            ->causedBy($request->user())
            ->withProperties(['row_id' => $validated['row_id'], 'type' => $validated['type'], 'name' => $validated['name']])
            ->log('dns.record_updated');

        $mockNote = config('provisioning.mock_mode', true) ? ' (mock)' : '';

        return back()->with('status', "DNS záznam {$validated['type']} byl upraven{$mockNote}.");
    }

    /** Delete one record by its registrar row id (audit F83). */
    public function destroy(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('view', $domain);

        $validated = $request->validate([
            'row_id' => ['required', 'integer', 'min:1'],
        ]);

        $integration = $this->getIntegration();

        if ($integration !== null && ! config('provisioning.mock_mode', true)) {
            (new WedosWapiClient($integration))->deleteDnsRecord($domain->fqdn(), (int) $validated['row_id']);
        }

        activity('domain')
            ->performedOn($domain)
            ->causedBy($request->user())
            ->withProperties(['row_id' => $validated['row_id']])
            ->log('dns.record_deleted');

        $mockNote = config('provisioning.mock_mode', true) ? ' (mock)' : '';

        return back()->with('status', "DNS záznam byl smazán{$mockNote}.");
    }

    /**
     * Apply a ready-made record set (audit F91).
     *
     * Mail providers all need the same handful of MX/TXT/CNAME records and
     * typing them by hand is where people make mistakes.
     */
    public function applyTemplate(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('view', $domain);

        $validated = $request->validate([
            'template' => ['required', 'in:' . implode(',', array_keys(self::TEMPLATES))],
        ]);

        $template = self::TEMPLATES[$validated['template']];
        $records  = $template['records'];

        $integration = $this->getIntegration();

        if ($integration !== null && ! config('provisioning.mock_mode', true)) {
            $client = new WedosWapiClient($integration);

            foreach ($records as $record) {
                $client->upsertDnsRecord($domain->fqdn(), $record);
            }
        }

        activity('domain')
            ->performedOn($domain)
            ->causedBy($request->user())
            ->withProperties(['template' => $validated['template'], 'records' => count($records)])
            ->log('dns.template_applied');

        $mockNote = config('provisioning.mock_mode', true) ? ' (mock)' : '';

        return back()->with('status', "Šablona {$template['label']} byla aplikována ({$mockNote}" . count($records) . ' záznamů).');
    }

    /**
     * Publish a DS record so the parent zone can validate this domain (audit 64).
     *
     * The registrar client already spoke DNSSEC; there was simply no way for a
     * customer to reach it. A DS record is what turns DNSSEC on at the registry:
     * without a UI the feature existed only for whoever had API access.
     */
    public function dnssecStore(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('view', $domain);

        $validated = $request->validate([
            // The four fields of a DS record (RFC 4034). Algorithm and digest
            // type are constrained to the values registries actually accept, so
            // a typo is rejected here rather than by the registrar much later.
            'key_tag'     => ['required', 'integer', 'min:0', 'max:65535'],
            'algorithm'   => ['required', 'integer', 'in:8,10,13,14,15,16'],
            'digest_type' => ['required', 'integer', 'in:1,2,4'],
            'digest'      => ['required', 'string', 'regex:/^[0-9a-fA-F]+$/', 'max:512'],
        ], [
            'digest.regex' => 'Digest musí být hexadecimální řetězec.',
        ]);

        $integration = $this->getIntegration();

        if ($integration !== null && ! config('provisioning.mock_mode', true)) {
            (new WedosWapiClient($integration))->addDnssecKey($domain->fqdn(), [
                'key_tag'     => (int) $validated['key_tag'],
                'algorithm'   => (int) $validated['algorithm'],
                'digest_type' => (int) $validated['digest_type'],
                'digest'      => $validated['digest'],
            ]);
        }

        // The digest is a public value, but the key tag is the useful audit
        // handle — record it, not the whole DS blob.
        activity('domain')
            ->performedOn($domain)
            ->causedBy($request->user())
            ->withProperties(['key_tag' => $validated['key_tag'], 'algorithm' => $validated['algorithm']])
            ->log('dnssec.key_added');

        $mockNote = config('provisioning.mock_mode', true) ? ' (mock)' : '';

        return back()->with('status', "DNSSEC klíč {$validated['key_tag']} byl přidán{$mockNote}.");
    }

    /** Remove a DS record by its key tag (audit 64). */
    public function dnssecDestroy(Request $request, DomainRegistration $domain): RedirectResponse
    {
        $this->authorize('view', $domain);

        $validated = $request->validate([
            'key_tag' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $integration = $this->getIntegration();

        if ($integration !== null && ! config('provisioning.mock_mode', true)) {
            (new WedosWapiClient($integration))->deleteDnssecKey($domain->fqdn(), (int) $validated['key_tag']);
        }

        activity('domain')
            ->performedOn($domain)
            ->causedBy($request->user())
            ->withProperties(['key_tag' => $validated['key_tag']])
            ->log('dnssec.key_deleted');

        $mockNote = config('provisioning.mock_mode', true) ? ' (mock)' : '';

        return back()->with('status', "DNSSEC klíč {$validated['key_tag']} byl odebrán{$mockNote}.");
    }

    /**
     * @return list<array<string, mixed>> published DS records
     */
    private function fetchDnssec(string $fqdn): array
    {
        $integration = $this->getIntegration();

        if ($integration === null || config('provisioning.mock_mode', true)) {
            // Empty in mock: fabricating a fake DS key would tell a customer
            // their domain is signed when nothing is signing it — worse than
            // showing an honest "DNSSEC is off".
            return [];
        }

        try {
            $response = (new WedosWapiClient($integration))->getDnssecKeys($fqdn);
            $keys     = $response['keys'] ?? $response['dnssec'] ?? null;

            return is_array($keys) ? array_values(array_filter($keys, 'is_array')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetchRecords(string $fqdn): array
    {
        $integration = $this->getIntegration();

        if ($integration === null || config('provisioning.mock_mode', true)) {
            return $this->mockRecords($fqdn);
        }

        try {
            // getDnsRecords (not getDomainInfo) — it returns the rows WITH the
            // registrar row ids that update/delete need.
            $response = (new WedosWapiClient($integration))->getDnsRecords($fqdn);
            $rows     = $response['rows'] ?? $response['dns_rows'] ?? null;

            return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : $this->mockRecords($fqdn);
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
        // row_id mirrors what the registrar returns — the edit/delete forms
        // need it to identify a record, in mock mode as well as live.
        return [
            ['row_id' => 1, 'type' => 'A',   'name' => '@',      'rdata' => '37.27.65.82',                         'ttl' => 3600,  'prio' => 0],
            ['row_id' => 2, 'type' => 'A',   'name' => 'www',    'rdata' => '37.27.65.82',                         'ttl' => 3600,  'prio' => 0],
            ['row_id' => 3, 'type' => 'A',   'name' => 'mail',   'rdata' => '37.27.65.82',                         'ttl' => 3600,  'prio' => 0],
            ['row_id' => 4, 'type' => 'MX',  'name' => '@',      'rdata' => "mail.{$fqdn}.",                       'ttl' => 3600,  'prio' => 10],
            ['row_id' => 5, 'type' => 'TXT', 'name' => '@',      'rdata' => 'v=spf1 include:onhost.cz ~all',       'ttl' => 3600,  'prio' => 0],
            ['row_id' => 6, 'type' => 'TXT', 'name' => '_dmarc', 'rdata' => 'v=DMARC1; p=none; rua=mailto:dmarc@onhost.cz', 'ttl' => 3600, 'prio' => 0],
            ['row_id' => 7, 'type' => 'NS',  'name' => '@',      'rdata' => 'ns1.onhost.cz.',                      'ttl' => 86400, 'prio' => 0],
            ['row_id' => 8, 'type' => 'NS',  'name' => '@',      'rdata' => 'ns2.onhost.cz.',                      'ttl' => 86400, 'prio' => 0],
        ];
    }
}
