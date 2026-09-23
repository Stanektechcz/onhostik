<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\Website;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * What the internet answers for a customer's domain, against what the platform published for it.
 *
 * Everything the platform does with DNS it did **blind**: it wrote the site's address and the mail records into its
 * own zone and never looked at what the world sees. A customer whose DNS is elsewhere never adds them, a record
 * falls out of a zone, a domain is moved — and the first anyone hears of it is that the site is off or that mail
 * bounces, usually from the customer. It could not even see when its own publish had not landed: the autoconfig SRV
 * record the platform built was one its own validator refused, so mail domains were left with no records at all and
 * nothing noticed for as long as that bug lived (audit rows 79–80).
 *
 * It repairs what is ours — a record missing from a zone the platform runs is put back, because that is not the
 * customer's to be told about — and it tells the customer about what only they can change: a domain that points
 * somewhere else, mail records their own DNS provider has to hold. It never changes a record the customer made
 * themselves (`syncSystemRecords` leaves those alone).
 */
final class PublicDnsCheck
{
    /** The problems worth a customer's attention, in the order they break things. */
    public const KINDS = ['site_missing', 'site_elsewhere', 'mx_missing', 'mx_elsewhere', 'spf_missing', 'spf_without_us', 'dkim_missing', 'dkim_mismatch', 'dmarc_missing'];

    public function __construct(
        private readonly RecordResolver $dns,
        private readonly DomainPointing $pointing,
        private readonly DnsService $zones,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{checked:int, problems:int, repaired:int, told:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'problems' => 0, 'repaired' => 0, 'told' => 0, 'errors' => 0];
        $services = Service::query()
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('family', ['web', 'managed', 'mail'])->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            try {
                $repaired = $this->repair($service);
                $problems = $this->problems($service);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            $stats['checked']++;
            $stats['repaired'] += $repaired;
            $tags = (array) ($service->tags ?? []);
            $before = (array) ($tags['dns_check'] ?? []);
            $tags['dns_check'] = ['problems' => $problems, 'checked_at' => now()->toIso8601String(), 'told_on' => $before['told_on'] ?? null];
            if ($problems !== []) {
                $stats['problems']++;
                // a zone the platform has just repaired needs no letter: the next look sees whether it landed
                if ($repaired === 0 && (string) ($before['told_on'] ?? '') !== now()->toDateString()) {
                    $tags['dns_check']['told_on'] = now()->toDateString();
                    $this->tell($service, $problems);
                    $stats['told']++;
                }
            }
            $service->forceFill(['tags' => $tags])->save();
        }

        return $stats;
    }

    /**
     * What the world does not answer the way the platform published it.
     *
     * @return list<array{kind:string, domain:string, detail:string, expected:string}>
     */
    public function problems(Service $service): array
    {
        $problems = [];
        foreach ($this->site($service) as $problem) {
            $problems[] = $problem;
        }
        foreach (MailDomain::query()->where('service_id', $service->id)->where('state', 'active')->get() as $domain) {
            foreach ($this->mail($service, $domain) as $problem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /** Whether the domain of a site points at the node that serves it. @return list<array<string,string>> */
    private function site(Service $service): array
    {
        if (! in_array($service->family, ['web', 'managed'], true)) {
            return [];
        }
        $site = Website::query()->where('service_id', $service->id)->first();
        $domain = mb_strtolower((string) ($site->domain ?? $service->hostname ?? ''));
        $addresses = DomainPointing::addressesOf($service);
        if ($domain === '' || $addresses === []) {
            return []; // nothing to compare against; the platform does not invent an address
        }
        $answers = $this->pointing->answers($domain);
        if ($answers === []) {
            return [['kind' => 'site_missing', 'domain' => $domain, 'detail' => 'doména zatím neodpovídá žádnou adresou', 'expected' => implode(', ', $addresses)]];
        }
        if (array_intersect($answers, $addresses) !== []) {
            return [];
        }

        return [['kind' => 'site_elsewhere', 'domain' => $domain, 'detail' => 'doména míří na '.implode(', ', array_slice($answers, 0, 3)), 'expected' => implode(', ', $addresses)]];
    }

    /** Whether mail for a domain arrives here and is accepted where it is sent. @return list<array<string,string>> */
    private function mail(Service $service, MailDomain $mail): array
    {
        $domain = mb_strtolower((string) $mail->domain);
        $host = rtrim(mb_strtolower(MailSettings::host($service)), '.');
        $problems = [];

        $targets = array_map(fn (array $r) => rtrim(mb_strtolower((string) ($r['target'] ?? '')), '.'), $this->dns->records($domain, 'MX'));
        $targets = array_values(array_filter($targets));
        if ($targets === []) {
            $problems[] = ['kind' => 'mx_missing', 'domain' => $domain, 'detail' => 'doména nemá žádný MX záznam, pošta nemá kam přijít', 'expected' => $host];
        } elseif (! in_array($host, $targets, true)) {
            $problems[] = ['kind' => 'mx_elsewhere', 'domain' => $domain, 'detail' => 'pošta domény chodí na '.implode(', ', array_slice($targets, 0, 3)), 'expected' => $host];
        }

        $txt = array_map(fn (array $r) => (string) ($r['txt'] ?? ''), $this->dns->records($domain, 'TXT'));
        $spf = array_values(array_filter($txt, fn (string $value) => str_starts_with(mb_strtolower(trim($value)), 'v=spf1')));
        $include = (string) config('onhost.dns.spf_include', '');
        if ($spf === []) {
            $problems[] = ['kind' => 'spf_missing', 'domain' => $domain, 'detail' => 'doména nemá SPF, odeslaná pošta skončí ve spamu', 'expected' => 'v=spf1 mx include:'.$include.' -all'];
        } elseif ($include !== '' && ! str_contains(mb_strtolower(implode(' ', $spf)), mb_strtolower($include)) && ! preg_match('/(^|\s)mx(\s|$)/', mb_strtolower(implode(' ', $spf)))) {
            $problems[] = ['kind' => 'spf_without_us', 'domain' => $domain, 'detail' => 'SPF domény nás nezahrnuje: '.mb_substr($spf[0], 0, 120), 'expected' => 'include:'.$include.' nebo mx'];
        }

        $selector = (string) ($mail->dkim_selector ?? '');
        $public = preg_replace('/\s+|-----[A-Z ]+-----/', '', (string) ($mail->dkim_public ?? ''));
        if ($selector !== '' && (string) $public !== '') {
            $found = implode(' ', array_map(fn (array $r) => (string) ($r['txt'] ?? ''), $this->dns->records($selector.'._domainkey.'.$domain, 'TXT')));
            if (trim($found) === '') {
                $problems[] = ['kind' => 'dkim_missing', 'domain' => $domain, 'detail' => 'podpis DKIM není v DNS, pošta se nedá ověřit', 'expected' => $selector.'._domainkey'];
            } elseif (! str_contains(str_replace(' ', '', $found), mb_substr((string) $public, 0, 40))) {
                $problems[] = ['kind' => 'dkim_mismatch', 'domain' => $domain, 'detail' => 'v DNS je jiný klíč DKIM, než který podepisuje poštu', 'expected' => $selector.'._domainkey'];
            }
        }

        $dmarc = array_map(fn (array $r) => mb_strtolower((string) ($r['txt'] ?? '')), $this->dns->records('_dmarc.'.$domain, 'TXT'));
        if (array_filter($dmarc, fn (string $value) => str_starts_with(trim($value), 'v=dmarc1')) === []) {
            $problems[] = ['kind' => 'dmarc_missing', 'domain' => $domain, 'detail' => 'doména nemá DMARC; velké schránky jí věří méně', 'expected' => 'v=DMARC1; p=quarantine'];
        }

        return $problems;
    }

    /**
     * What is ours to put right: a record missing from a zone the platform runs. The customer's own records are
     * never touched, and a domain whose DNS is elsewhere is not repaired — it is reported.
     *
     * @return int how many zones were put right
     */
    private function repair(Service $service): int
    {
        $context = CommandContext::system('dns.check')->withScope($service->organization_id);
        $repaired = 0;
        foreach (MailDomain::query()->where('service_id', $service->id)->where('state', 'active')->get() as $mail) {
            $zone = $this->ours((string) $mail->domain, $service);
            if ($zone === null) {
                continue;
            }
            $records = MailSettings::records((string) $mail->domain, MailSettings::host($service), $mail->dkim_selector, $mail->dkim_public);
            $repaired += $this->zones->syncSystemRecords($zone, $records, $context, 'kontrola poštovních záznamů', 'mail:'.mb_strtolower((string) $mail->domain)) === null ? 0 : 1;
        }

        return $repaired;
    }

    /** The zone of a domain when the platform runs it for this customer. */
    private function ours(string $domain, Service $service): ?DnsZone
    {
        return DnsZone::query()->where('name', mb_strtolower($domain))->where('organization_id', $service->organization_id)->where('state', 'active')->first();
    }

    private function tell(Service $service, array $problems): void
    {
        $context = CommandContext::system('dns.check')->withScope($service->organization_id);
        $this->audit->record($context, 'service.dns.problem', 'succeeded', ['problems' => $problems], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.dns.problem', 'service', $service->id, [
            'hostname' => $service->hostname, 'label' => $service->label, 'family' => $service->family,
            'problems' => $problems, 'kinds' => array_values(array_unique(array_column($problems, 'kind'))),
        ], $service->organization_id));
    }
}
