<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Onhost\Domain\Dns\DnssecWatch;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Mail\MailAuthWatch;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\CdnZone;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\ManagedCertificate;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Services\Penpot\PenpotHealth;
use Onhost\Domain\Services\Web\CertificateWatch;
use Onhost\Domain\Services\Web\SecurityHeadersWatch;

/**
 * "Is my service all right?" — answered from what the platform already knows, in one pass and without asking a panel:
 * the state of the service, whether the panel behind it can be reached, the last backup, the certificate, the uptime
 * monitor, operations that failed, how close the service is to its limits.
 *
 * Every finding is a fact with a level and a sentence in both languages, so the assistant (with a model or without one),
 * the panel and support say the same thing. Nothing about money: whoever may see a service — a guest it was shared with
 * included — may read this; what a service costs is not theirs to know.
 */
final class ServiceHealthCheck
{
    /**
     * @return array{service_id:string, name:string, family:string, state:string, verdict:'ok'|'warn'|'bad', findings:list<array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string, observed_at?:?string, state?:string}>, checked_at:string}
     */
    public function run(Service $service): array
    {
        $findings = [$this->state($service), $this->controlPlane($service)];
        if (in_array($service->family, ['web', 'managed', 'game', 'cloud', 'data', 'penpot'], true)) {
            $findings[] = $this->backup($service);
        }
        if ($service->family === 'penpot') { // TASK-0123: what the last probe of the node found (onhost:penpot:sweep)
            $findings[] = PenpotHealth::finding($service);
        }
        if (in_array($service->family, ['web', 'managed'], true)) {
            $findings[] = $this->certificate($service);
            $findings[] = $this->monitor($service);
            $findings[] = $this->securityHeaders($service);
            $findings[] = $this->originExposure($service);
            $findings[] = $this->dnssec($service);
        }
        if (in_array($service->family, ['web', 'managed', 'mail'], true)) {
            $findings[] = $this->mailAuth($service);
        }
        $findings[] = $this->suspensionLeft($service);
        $findings[] = $this->operations($service);
        $findings[] = $this->usage($service);
        $findings = array_values(array_filter($findings));
        $levels = array_column($findings, 'level');

        return [
            'service_id' => $service->id, 'name' => (string) ($service->hostname ?: ($service->label ?: $service->name)), 'family' => (string) $service->family, 'state' => (string) $service->state,
            'verdict' => in_array('bad', $levels, true) ? 'bad' : (in_array('warn', $levels, true) ? 'warn' : 'ok'), 'findings' => $findings, 'checked_at' => now()->toIso8601String(),
        ];
    }

    /** The findings as a few lines of text, the worst first. */
    public static function text(array $check, string $locale = 'cs'): string
    {
        $en = $locale === 'en';
        $order = ['bad' => 0, 'warn' => 1, 'ok' => 2];
        $findings = (array) $check['findings'];
        usort($findings, fn (array $a, array $b) => $order[$a['level']] <=> $order[$b['level']]);
        $mark = ['bad' => '✖', 'warn' => '!', 'ok' => '✓'];
        $head = match ($check['verdict']) {
            'bad' => $en ? "{$check['name']}: something needs your attention." : "{$check['name']}: něco vyžaduje vaši pozornost.",
            'warn' => $en ? "{$check['name']} runs, with a few things to look at." : "{$check['name']} běží, ale pár věcí stojí za pozornost.",
            default => $en ? "{$check['name']} is all right." : "{$check['name']} je v pořádku.",
        };

        return $head."\n".implode("\n", array_map(fn (array $f) => $mark[$f['level']].' '.($en ? $f['en'] : $f['cs']), $findings));
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    private static function finding(string $key, string $level, string $cs, string $en): array
    {
        return ['key' => $key, 'level' => in_array($level, ['ok', 'warn', 'bad'], true) ? $level : 'warn', 'cs' => $cs, 'en' => $en];
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    private function state(Service $service): array
    {
        return match (true) {
            in_array($service->state, [ServiceStateMachine::ACTIVE], true) => self::finding('state', 'ok', 'Služba je aktivní.', 'The service is active.'),
            $service->state === ServiceStateMachine::DEGRADED => self::finding('state', 'warn', 'Služba běží v omezeném režimu.', 'The service runs in a degraded mode.'),
            $service->state === ServiceStateMachine::SUSPENDED => self::finding('state', 'bad', 'Služba je pozastavená.', 'The service is suspended.'),
            $service->state === ServiceStateMachine::FAILED => self::finding('state', 'bad', 'Poslední změna služby selhala a služba čeká na zásah.', 'The last change of the service failed and it waits for attention.'),
            default => self::finding('state', 'warn', "Služba je ve stavu {$service->state}.", "The service is {$service->state}."),
        };
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null */
    private function controlPlane(Service $service): ?array
    {
        $control = ControlPlaneStatus::of($service);
        if ($control['available']) {
            return null; // nothing to say
        }

        return self::finding('control_plane', 'warn', 'Správa služby je teď nedostupná ('.$control['state'].'); služba sama běží dál, změny počkají.', 'Managing the service is unavailable right now ('.$control['state'].'); the service itself keeps running, changes wait.');
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    private function backup(Service $service): array
    {
        $base = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('kind', '!=', 'final')->orderByDesc('finished_at');
        $newest = (clone $base)->first();
        // a set that lacks a database is not the latest good backup: the previous whole one is
        $last = (clone $base)->whereNull('meta->incomplete')->first();
        if ($newest !== null && $newest->finished_at !== null && $newest->id !== $last?->id && data_get($newest->meta, 'incomplete') !== null) {
            $lacks = BackupCompleteness::missingOf($newest);
            $what = $lacks !== [] ? 'chybí DB '.implode(', ', $lacks) : 'zálohu se nepodařilo přečíst celou';
            $whatEn = $lacks !== [] ? 'database '.implode(', ', $lacks).' is missing' : 'it could not be read back in full';
            $good = $last?->finished_at;

            return Freshness::stamp(self::finding('backup', 'warn',
                "Poslední záloha je neúplná ({$newest->finished_at->format('j. n. Y H:i')}): {$what}. ".($good !== null ? 'Poslední úplná záloha: '.$good->format('j. n. Y H:i').'.' : 'Úplnou zálohu zatím nemáme.'),
                "The last backup is incomplete ({$newest->finished_at->format('j. n. Y H:i')}): {$whatEn}. ".($good !== null ? 'Last complete backup: '.$good->format('j. n. Y H:i').'.' : 'There is no complete backup yet.')),
                Freshness::assess('backup', $good, max(1, (int) config('onhost.backups.coverage_days', 3)) * 1440));
        }
        if ($last === null || $last->finished_at === null) {
            return Freshness::stamp(self::finding('backup', 'warn', 'Služba zatím nemá žádnou dokončenou zálohu.', 'The service has no finished backup yet.'), Freshness::assess('backup', null));
        }
        $days = (int) floor($last->finished_at->diffInDays(now(), true));
        $when = $last->finished_at->format('j. n. Y H:i');
        $limit = max(1, (int) config('onhost.backups.coverage_days', 3));

        $finding = $days > $limit
            ? self::finding('backup', 'warn', "Poslední záloha je {$days} dní stará ({$when}).", "The last backup is {$days} days old ({$when}).")
            : self::finding('backup', 'ok', "Poslední záloha: {$when}.", "Last backup: {$when}.");

        // the age rule above already words an old backup; the stamp adds the machine-readable state
        return Freshness::stamp($finding, Freshness::assess('backup', $last->finished_at, $limit * 1440));
    }

    /**
     * What a suspension switched off and the resume could not switch on again.
     *
     * A running service whose `suspension_paused` memory is not empty is not whole: the panel refused to switch those
     * cron jobs, FTP accounts, schedules or mailboxes back on, so the site serves but the customer's scheduled jobs
     * do not run. It used to be invisible — the memory was wiped and the service reported ACTIVE.
     *
     * @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null
     */
    private function suspensionLeft(Service $service): ?array
    {
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            return null; // a suspended service is meant to have things switched off
        }
        $left = array_filter((array) data_get($service->tags, SuspensionDepth::TAG, []), fn ($ids) => (array) $ids !== []);
        if ($left === []) {
            return null;
        }
        $names = ['cron' => ['naplánované úlohy', 'scheduled jobs'], 'ftp' => ['FTP účty', 'FTP accounts'], 'schedule' => ['plány serveru', 'server schedules'], 'app' => ['aplikace', 'apps'], 'mail' => ['odesílání pošty', 'outgoing mail']];
        $cs = implode(', ', array_map(fn (string $kind) => $names[$kind][0] ?? $kind, array_keys($left)));
        $en = implode(', ', array_map(fn (string $kind) => $names[$kind][1] ?? $kind, array_keys($left)));

        return self::finding('suspension_left', 'warn', "Po obnovení služby se nepodařilo znovu zapnout: {$cs}. Zkoušíme to dál a řešíme to.", "After the service was resumed these could not be switched on again: {$en}. We keep trying and are on it.");
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    private function certificate(Service $service): array
    {
        $managed = ManagedCertificate::query()->where('service_id', $service->id)->where('state', 'issued')->orderByDesc('expires_at')->first();
        if ($managed !== null && $managed->expires_at !== null) {
            $left = (int) floor(now()->diffInDays($managed->expires_at, false));
            $until = $managed->expires_at->format('j. n. Y');

            // a certificate the platform issued itself is a record it keeps, not a reading taken from a node
            $record = ['state' => Freshness::CURRENT, 'observed_at' => ($managed->updated_at ?? $managed->created_at)?->toIso8601String(), 'age_minutes' => null];

            return Freshness::stamp(match (true) {
                $left < 0 => self::finding('certificate', 'bad', "Certifikát HTTPS vypršel {$until}.", "The HTTPS certificate expired on {$until}."),
                $left <= 10 => self::finding('certificate', 'warn', "Certifikát HTTPS vyprší za {$left} dní ({$until}); obnova probíhá automaticky.", "The HTTPS certificate expires in {$left} days ({$until}); it renews by itself."),
                default => self::finding('certificate', 'ok', "Certifikát HTTPS platí do {$until}.", "The HTTPS certificate is valid until {$until}."),
            }, $record);
        }
        // What the node really serves, as the daily watch last saw it (`CertificateWatch`). Before that existed this
        // answered from `access.certificate` — a flag written once, at the first issue — so the platform kept saying
        // "the certificate is issued" on the day after it expired.
        $seen = (array) data_get($service->tags, 'tls', []);
        if (($seen['expires_at'] ?? null) !== null) {
            $expires = CarbonImmutable::parse((string) $seen['expires_at']);
            $left = (int) floor(now()->diffInDays($expires, false));
            $until = $expires->format('j. n. Y');
            $names = implode(', ', array_slice(array_map('strval', (array) ($seen['names'] ?? [])), 0, 3));

            return Freshness::stamp(match (true) {
                ! empty($seen['mismatch']) => self::finding('certificate', 'bad', "Certifikát, kterým se web hlásí, neplatí pro tuhle doménu (je vystavený na {$names}); prohlížeč návštěvníka ukáže varování.", "The certificate the site presents is not valid for this domain (it covers {$names}); a visitor's browser shows a warning."),
                $left < 0 => self::finding('certificate', 'bad', "Certifikát HTTPS vypršel {$until}; obnovu jsme si vyžádali.", "The HTTPS certificate expired on {$until}; a new one has been requested."),
                $left <= CertificateWatch::renewAtDays() => self::finding('certificate', 'warn', "Certifikát HTTPS vyprší za {$left} dní ({$until}); obnovu jsme si vyžádali.", "The HTTPS certificate expires in {$left} days ({$until}); a new one has been requested."),
                default => self::finding('certificate', 'ok', "Certifikát HTTPS platí do {$until}.", "The HTTPS certificate is valid until {$until}."),
            }, Freshness::assess('certificate', $seen['checked_at'] ?? null));
        }
        $state = (string) data_get($service->tags, 'access.certificate', '');

        // only a flag written once at the first issue: nothing was ever measured on the node
        return Freshness::stamp($state === 'issued'
            ? self::finding('certificate', 'ok', 'Certifikát HTTPS je vystavený.', 'The HTTPS certificate is issued.')
            : self::finding('certificate', 'warn', 'Web zatím nemá vystavený certifikát HTTPS.', 'The site has no HTTPS certificate yet.'), Freshness::assess('certificate', null));
    }

    /**
     * What the site sends in its response headers, as the nightly look last saw it (`SecurityHeadersWatch`). Advice only; a
     * look that failed is said as failed — and keeps the last known reading beside it — never as "fine".
     *
     * @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null
     */
    private function securityHeaders(Service $service): ?array
    {
        $seen = (array) data_get($service->tags, 'security_headers', []);
        if ($seen === []) {
            return null; // never looked at: the nightly collector has not reached this site yet
        }
        if (! empty($seen['error'])) {
            $why = $seen['error'] === 'refused' ? ['adresa webu není veřejná, nic jsme neposlali', 'the address of the site is not public, nothing was sent'] : ['web neodpověděl', 'the site did not answer'];

            return Freshness::stamp(self::finding('security_headers', 'warn', "Bezpečnostní hlavičky webu se nepodařilo ověřit: {$why[0]}.", "The site's security headers could not be checked: {$why[1]}."),
                ['state' => Freshness::COLLECTION_ERROR, 'observed_at' => $seen['checked_at'] ?? null, 'age_minutes' => null]);
        }
        $missing = array_values(array_intersect(SecurityHeadersWatch::CHECKS, array_map('strval', (array) ($seen['missing'] ?? []))));
        $assessment = Freshness::assess('security_headers', $seen['checked_at'] ?? null);
        if ($missing === []) {
            return Freshness::stamp(self::finding('security_headers', 'ok', 'Web posílá sledované bezpečnostní hlavičky (HSTS, CSP, ochrana proti vložení do rámu, nosniff, Referrer-Policy, příznaky cookies).', 'The site sends the monitored security headers (HSTS, CSP, framing protection, nosniff, Referrer-Policy, cookie flags).'), $assessment);
        }
        $cs = implode('; ', array_map(fn (string $k) => SecurityHeadersWatch::ADVICE[$k][0], $missing));
        $en = implode('; ', array_map(fn (string $k) => SecurityHeadersWatch::ADVICE[$k][1], $missing));

        return Freshness::stamp(self::finding('security_headers', 'warn', 'Webu chrání méně, než by mohl — doporučení (sami nic neměníme): '.$cs.'.', 'The site is less protected than it could be — advice (we change nothing ourselves): '.$en.'.'), $assessment);
    }

    /**
     * A site behind the CDN whose node address is handed out by another public name (`OriginExposureWatch`).
     *
     * @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null
     */
    private function originExposure(Service $service): ?array
    {
        $seen = (array) data_get($service->tags, 'origin_exposure', []);
        if ($seen === [] || ! CdnZone::query()->where('service_id', $service->id)->where('state', 'active')->exists()) {
            return null;
        }
        $assessment = Freshness::assess('origin', $seen['checked_at'] ?? null);
        $leaks = (array) ($seen['leaks'] ?? []);
        if ($leaks === []) {
            return Freshness::stamp(self::finding('origin_exposure', 'ok', 'Adresa původního serveru za CDN se z veřejného DNS nezjistí.', 'The address of the server behind the CDN cannot be found in public DNS.'), $assessment);
        }
        $names = implode(', ', array_slice(array_map(fn ($l) => (string) ($l['name'] ?? ''), $leaks), 0, 5));

        return Freshness::stamp(self::finding('origin_exposure', 'warn',
            "Tyto názvy v DNS prozrazují adresu původního serveru a obcházejí CDN: {$names}. Doporučení: nasměrujte je jinam (nebo je pošlete přes CDN) a zvažte omezení přístupu na server jen z adres CDN; sami nic neměníme.",
            "These DNS names reveal the address of the origin server and bypass the CDN: {$names}. Advice: point them elsewhere (or proxy them through the CDN) and consider allowing connections to the server only from the CDN's addresses; we change nothing ourselves."), $assessment);
    }

    /**
     * The DS record at the registry against the key our nameserver signs the domain's zone with (`DnssecWatch`). A missing or
     * foreign DS breaks name resolution for validating resolvers, so it is `bad`; a DS that could not be read is never `ok`.
     *
     * @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null
     */
    private function dnssec(Service $service): ?array
    {
        $host = mb_strtolower(rtrim(trim((string) $service->hostname), '.'));
        $zone = $host === '' ? null : DnsZone::query()->where('organization_id', $service->organization_id)->where('state', 'active')->where('dnssec', true)
            ->get()->filter(fn (DnsZone $z) => $host === $z->name || str_ends_with($host, '.'.$z->name))->sortByDesc(fn (DnsZone $z) => strlen($z->name))->first();
        if ($zone === null) {
            return null;
        }
        $check = (array) $zone->dnssec_check;
        $assessment = Freshness::assess('dnssec', $check['checked_at'] ?? null);
        $name = $zone->name;
        $finding = match ((string) ($check['state'] ?? DnssecWatch::UNKNOWN)) {
            DnssecWatch::OK => self::finding('dnssec', 'ok', "DNSSEC domény {$name}: DS záznam u registru odpovídá našemu klíči.", "DNSSEC for {$name}: the DS record at the registry matches our key."),
            DnssecWatch::MISSING => self::finding('dnssec', 'bad', "DNSSEC domény {$name} je zapnuté, ale registr nemá žádný DS záznam; zveřejněte DS u registru, nebo DNSSEC vypněte.", "DNSSEC is on for {$name}, but the registry holds no DS record; publish the DS at the registry, or switch DNSSEC off."),
            DnssecWatch::MISMATCH => self::finding('dnssec', 'bad', "DS záznam u registru pro {$name} neodpovídá klíči, kterým zónu podepisujeme; validující resolvery mohou doménu přestat překládat. Zveřejněte aktuální DS, nebo DNSSEC vypněte.", "The DS record at the registry for {$name} does not match the key we sign the zone with; validating resolvers may stop resolving the domain. Publish the current DS, or switch DNSSEC off."),
            default => self::finding('dnssec', 'warn', "DS záznam domény {$name} u registru se zatím nepodařilo ověřit.", "The DS record of {$name} at the registry could not be verified yet."),
        };

        return Freshness::stamp($finding, $assessment);
    }

    /**
     * SPF, DKIM and DMARC of the mail domains the service carries, as public DNS showed them at the last nightly look
     * (`MailAuthWatch`). Advice only; a look that failed is said as failed, never as fine, and a service whose domains were
     * never looked at says nothing here (the security posture lists it as not measured).
     *
     * @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null
     */
    private function mailAuth(Service $service): ?array
    {
        $seen = (array) data_get($service->tags, 'mail_auth', []);
        if ($seen === [] || ! MailDomain::query()->where('service_id', $service->id)->where('state', 'active')->exists()) {
            return null;
        }
        $domains = array_values(array_filter((array) ($seen['domains'] ?? []), 'is_array'));
        $assessment = Freshness::assess('mail_auth', $seen['checked_at'] ?? null);
        $levels = array_map(fn (array $d) => MailAuthWatch::level($d), $domains);
        $failed = array_values(array_filter($domains, fn (array $d) => MailAuthWatch::level($d) === 'error'));
        $advice = [];
        foreach ($domains as $d) {
            array_push($advice, ...MailAuthWatch::advice($d));
        }
        if ($failed !== [] && ! in_array('bad', $levels, true)) {
            $names = implode(', ', array_map(fn (array $d) => (string) $d['domain'], $failed));

            return Freshness::stamp(self::finding('mail_auth', 'warn', "Ověření pošty (SPF, DKIM, DMARC) se nepodařilo: DNS domény {$names} neodpovědělo.", "The mail authentication check (SPF, DKIM, DMARC) failed: DNS for {$names} did not answer."),
                ['state' => Freshness::COLLECTION_ERROR, 'observed_at' => $seen['checked_at'] ?? null, 'age_minutes' => null]);
        }
        $cs = implode(' ', array_column($advice, 0));
        $en = implode(' ', array_column($advice, 1));
        $finding = match (true) {
            in_array('bad', $levels, true) => self::finding('mail_auth', 'bad', 'Pošta z vaší domény nemusí projít ověřením u příjemců. '.$cs.' (sami nic neměníme)', 'Mail from your domain may fail verification at receivers. '.$en.' (we change nothing ourselves)'),
            in_array('warn', $levels, true) => self::finding('mail_auth', 'warn', 'Ověření pošty jde zlepšit. '.$cs.' (sami nic neměníme)', 'Mail authentication can be improved. '.$en.' (we change nothing ourselves)'),
            default => self::finding('mail_auth', 'ok', 'SPF (jediný záznam, ne víc než 10 dotazů), DKIM (klíč v DNS odpovídá našemu) a DMARC (politika a adresa pro hlášení) vypadají v pořádku.', 'SPF (a single record, at most 10 lookups), DKIM (the key in DNS matches ours) and DMARC (policy and report address) look right.'),
        };

        return Freshness::stamp($finding, $assessment);
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    private function monitor(Service $service): array
    {
        $monitor = UptimeMonitor::query()->where('service_id', $service->id)->where('enabled', true)->orderBy('created_at')->first();
        if ($monitor === null) {
            return self::finding('monitor', 'warn', 'Monitoring dostupnosti není zapnutý.', 'Uptime monitoring is not switched on.');
        }

        $seen = Freshness::assess('monitor', $monitor->last_checked_at, max(15, (int) ceil(((int) $monitor->interval_seconds) * 3 / 60)));

        return Freshness::stamp($monitor->state === 'down'
            ? self::finding('monitor', 'bad', 'Monitoring hlásí výpadek: '.(string) $monitor->url.'.', 'Monitoring reports an outage: '.(string) $monitor->url.'.')
            : self::finding('monitor', 'ok', 'Monitoring hlásí, že web odpovídá'.($monitor->last_ms ? " ({$monitor->last_ms} ms)" : '').'.', 'Monitoring reports the site answers'.($monitor->last_ms ? " ({$monitor->last_ms} ms)" : '').'.'), $seen);
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null */
    private function operations(Service $service): ?array
    {
        $failed = Operation::query()->where('service_id', $service->id)->where('state', Operation::FAILED)->where('finished_at', '>=', now()->subDay())->orderByDesc('finished_at')->first();
        if ($failed !== null) {
            $action = (string) (is_array($failed->desired) ? ($failed->desired['action'] ?? $failed->kind) : $failed->kind);

            return self::finding('operations', 'warn', "Za posledních 24 hodin selhala operace „{$action}“; podrobnosti jsou v záložce Provoz.", "An operation failed in the last 24 hours ({$action}); details are under Operations.");
        }
        $running = Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->count();

        return $running > 0 ? self::finding('operations', 'ok', "Právě probíhá {$running} operace.", "{$running} operation(s) in progress.") : null;
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}|null */
    private function usage(Service $service): ?array
    {
        $usage = data_get($service->tags, 'usage');
        if (! is_array($usage) || ! is_array($usage['metrics'] ?? null) || $usage['metrics'] === []) {
            return null;
        }
        $top = UsageWatch::top($usage['metrics']);
        if ($top === null) {
            return null;
        }

        $seen = Freshness::assess('usage', $usage['checked_at'] ?? null);

        return Freshness::stamp(match ((string) ($usage['level'] ?? 'ok')) {
            'full' => self::finding('usage', 'bad', "Služba vyčerpala limit ({$top['key']}, {$top['pct']} %).", "The service has used up its limit ({$top['key']}, {$top['pct']} %)."),
            'critical' => self::finding('usage', 'bad', "Služba je na {$top['pct']} % svého limitu ({$top['key']}).", "The service is at {$top['pct']} % of its limit ({$top['key']})."),
            'warn' => self::finding('usage', 'warn', "Služba se blíží limitu: {$top['pct']} % ({$top['key']}).", "The service is getting close to its limit: {$top['pct']} % ({$top['key']})."),
            default => self::finding('usage', 'ok', "Využití je v pořádku (nejvíc {$top['pct']} %, {$top['key']}).", "Usage is fine (at most {$top['pct']} %, {$top['key']})."),
        }, $seen);
    }
}
