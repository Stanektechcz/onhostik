<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Services\Web\RestoreTest;
use Onhost\Domain\Services\Web\SecurityHeadersWatch;

/**
 * Security posture of one service (W2-1): a fixed catalog of 22 controls, each answered ONLY from evidence ONhost has.
 *
 * The rule of this class is the truthfulness rule of the platform: a control is `pass` only when a measurement that is
 * still current says so; `fail` only when a current measurement says so; everything else is `unknown` — never measured,
 * measurement old, collector failed, or no source for it exists at all (then the explanation is "ONhost toto zatím
 * neměří"). `not_applicable` is for controls that do not concern the kind of service. There is deliberately no overall
 * score and no "secure" verdict: counts of pass / fail / unknown are all there is.
 *
 * Read-only: nothing is collected here and no panel is asked. Every result carries its evidence (source and the time it was
 * observed) and an advice that is never applied by us.
 */
final class SecurityPosture
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const UNKNOWN = 'unknown';

    public const NOT_APPLICABLE = 'not_applicable';

    /** @var array<string,array{0:string,1:string}> id => [cs, en] title, in the order they are reported */
    public const CATALOG = [
        'iam-mfa' => ['Dvoufázové ověření členů', 'Two-factor sign-in of members'],
        'iam-least-privilege' => ['Nejmenší nutná oprávnění', 'Least privilege'],
        'iam-access-review' => ['Pravidelná kontrola přístupů', 'Periodic access review'],
        'server-patching' => ['Aktualizace serveru', 'Server patching'],
        'network-exposure' => ['Vystavení do sítě', 'Network exposure'],
        'ssh-hardening' => ['Zabezpečení SSH', 'SSH hardening'],
        'tls-validity' => ['Platný certifikát HTTPS', 'Valid HTTPS certificate'],
        'web-security-headers' => ['Bezpečnostní hlavičky webu', 'Web security headers'],
        'web-session-security' => ['Zabezpečení relací a cookies', 'Session and cookie security'],
        'web-input-validation' => ['Ověřování vstupů webu', 'Web input validation'],
        'web-dependency-updates' => ['Aktualizace závislostí webu', 'Web dependency updates'],
        'web-upload-isolation' => ['Oddělení nahraných souborů', 'Upload isolation'],
        'backup-restore' => ['Ověřená obnova ze zálohy', 'Verified restore from backup'],
        'backup-protection' => ['Aktuální úplná záloha', 'Current complete backup'],
        'mail-authentication' => ['Ověření odesílané pošty (SPF, DKIM, DMARC)', 'Outgoing mail authentication (SPF, DKIM, DMARC)'],
        'dns-change-control' => ['Ochrana DNS (DNSSEC)', 'DNS protection (DNSSEC)'],
        'monitoring-audit' => ['Monitoring a auditní záznam', 'Monitoring and audit trail'],
        'secrets-management' => ['Správa tajemství', 'Secrets management'],
        'deploy-rollback' => ['Nasazení s možností návratu', 'Deploy with rollback'],
        'incident-response' => ['Reakce na incidenty', 'Incident response'],
        'agent-prompt-injection' => ['Odolnost asistenta vůči podvrženým pokynům', 'Assistant resistance to prompt injection'],
        'agent-action-scope' => ['Rozsah akcí asistenta', 'Assistant action scope'],
    ];

    private const WEB = ['web', 'managed'];

    private const BACKUP = ['web', 'managed', 'game', 'cloud', 'data', 'penpot'];

    private const PRIVILEGED = ['manage', 'console', 'data_delete'];

    /** Control => families it concerns; missing = every family. */
    private const SCOPE = [
        'tls-validity' => self::WEB, 'web-security-headers' => self::WEB, 'web-session-security' => self::WEB, 'web-input-validation' => self::WEB,
        'web-dependency-updates' => self::WEB, 'web-upload-isolation' => self::WEB, 'backup-restore' => self::BACKUP, 'backup-protection' => self::BACKUP,
        'ssh-hardening' => ['web', 'managed', 'cloud', 'game', 'data'], 'mail-authentication' => ['web', 'managed', 'mail'],
    ];

    public function __construct(private readonly ServiceHealthCheck $health, private readonly AutomationLedger $ledger) {}

    /**
     * @param  array<string,mixed>|null  $check  a health check already run for this service (the monthly report has one)
     * @return array{service_id:string, name:string, family:string, controls:list<array<string,mixed>>, summary:array{pass:int, fail:int, unknown:int, not_applicable:int}, checked_at:string}
     */
    public function run(Service $service, ?array $check = null): array
    {
        $check ??= $this->health->run($service);
        $findings = [];
        foreach ((array) ($check['findings'] ?? []) as $finding) {
            $findings[(string) $finding['key']] = $finding;
        }
        $controls = [];
        foreach (self::CATALOG as $id => $title) {
            $controls[] = ['id' => $id, 'title' => ['cs' => $title[0], 'en' => $title[1]]] + $this->control($id, $service, $findings);
        }
        $summary = ['pass' => 0, 'fail' => 0, 'unknown' => 0, 'not_applicable' => 0];
        foreach ($controls as $control) {
            $summary[$control['status']]++;
        }

        return ['service_id' => $service->id, 'name' => (string) $check['name'], 'family' => (string) $service->family, 'controls' => $controls, 'summary' => $summary, 'checked_at' => now()->toIso8601String()];
    }

    /**
     * @param  array<string,array<string,mixed>>  $findings  health findings by key
     * @return array{status:string, evidence:list<array<string,mixed>>, advice:array{cs:string,en:string}, note:?array{cs:string,en:string}}
     */
    private function control(string $id, Service $service, array $findings): array
    {
        if (isset(self::SCOPE[$id]) && ! in_array($service->family, self::SCOPE[$id], true)) {
            return $this->result(self::NOT_APPLICABLE, [], null, ['cs' => 'Tohle se tohoto druhu služby netýká.', 'en' => 'This does not concern this kind of service.']);
        }

        return match ($id) {
            'iam-mfa' => $this->mfa($service),
            'iam-least-privilege' => $this->leastPrivilege($service),
            'network-exposure' => $this->fromFinding($findings['origin_exposure'] ?? null, 'origin_exposure', ['cs' => 'ONhost toto zatím neměří; adresu původního serveru kontroluje jen u webů za CDN.', 'en' => 'ONhost does not measure this yet; it checks the origin server address only for sites behind the CDN.']),
            'tls-validity' => $this->fromFinding($findings['certificate'] ?? null, 'certificate'),
            'web-security-headers' => $this->headers($service, false),
            'web-session-security' => $this->headers($service, true),
            'web-upload-isolation' => $this->uploads($service),
            'backup-restore' => $this->restore($service),
            'backup-protection' => $this->fromFinding($findings['backup'] ?? null, 'backup'),
            'mail-authentication' => $this->mail($service, $findings['mail_auth'] ?? null),
            'dns-change-control' => $this->fromFinding($findings['dnssec'] ?? null, 'dnssec', ['cs' => 'ONhost toto zatím neměří; ověřuje jen DS záznam u domén s DNSSEC zapnutým v naší DNS zóně.', 'en' => 'ONhost does not measure this yet; it only verifies the DS record of domains with DNSSEC switched on in our DNS zone.']),
            'monitoring-audit' => $this->monitoring($service),
            default => $this->unmeasured(),
        };
    }

    /** No source of evidence exists for this control. */
    private function unmeasured(): array
    {
        return $this->result(self::UNKNOWN, [], null, ['cs' => 'ONhost toto zatím neměří.', 'en' => 'ONhost does not measure this yet.']);
    }

    /**
     * @param  list<array<string,mixed>>  $evidence
     * @param  ?array{cs:string,en:string}  $advice
     * @param  ?array{cs:string,en:string}  $note
     */
    private function result(string $status, array $evidence, ?array $advice, ?array $note = null): array
    {
        return ['status' => $status, 'evidence' => $evidence, 'advice' => $advice ?? ['cs' => '', 'en' => ''], 'note' => $note];
    }

    /** @return array{source:string, observed_at:?string, state:string, detail?:string} */
    private function evidence(string $source, ?string $observedAt, string $state, ?string $detail = null): array
    {
        return ['source' => $source, 'observed_at' => $observedAt, 'state' => $state] + ($detail !== null ? ['detail' => $detail] : []);
    }

    /** What an unreliable measurement says instead of a verdict. @return array{cs:string,en:string} */
    private function notCurrent(string $state, ?string $observedAt): array
    {
        $when = $observedAt !== null ? CarbonImmutable::parse($observedAt)->format('j. n. Y H:i') : null;

        return match ($state) {
            Freshness::COLLECTION_ERROR => ['cs' => 'Poslední měření selhalo'.($when ? "; poslední známý údaj je z {$when}" : '').', takže to teď neumíme říct.', 'en' => 'The last measurement failed'.($when ? "; the last known reading is from {$when}" : '').', so we cannot say right now.'],
            Freshness::STALE => ['cs' => "Údaj je zastaralý (naposledy změřeno {$when}), takže to teď neumíme říct.", 'en' => "The reading is old (last observed {$when}), so we cannot say right now."],
            default => ['cs' => 'Tohle jsme zatím neměřili.', 'en' => 'This has not been measured yet.'],
        };
    }

    /**
     * A health finding as a control: a current `ok` is `pass`, a current `warn`/`bad` is `fail` with the finding's own
     * sentence as the advice, anything not current (or absent) is `unknown`.
     *
     * @param  ?array<string,mixed>  $finding
     */
    private function fromFinding(?array $finding, string $source, ?array $absentNote = null): array
    {
        if ($finding === null) {
            return $this->result(self::UNKNOWN, [], null, $absentNote ?? ['cs' => 'ONhost toto zatím neměří.', 'en' => 'ONhost does not measure this yet.']);
        }
        $state = (string) ($finding['state'] ?? Freshness::CURRENT);
        $evidence = [$this->evidence($source, $finding['observed_at'] ?? null, $state)];
        if ($state !== Freshness::CURRENT) {
            return $this->result(self::UNKNOWN, $evidence, null, $this->notCurrent($state, $finding['observed_at'] ?? null));
        }

        return $finding['level'] === 'ok'
            ? $this->result(self::PASS, $evidence, null)
            : $this->result(self::FAIL, $evidence, ['cs' => (string) $finding['cs'], 'en' => (string) $finding['en']]);
    }

    private function mfa(Service $service): array
    {
        $members = OrganizationMembership::query()->current()->where('organization_id', $service->organization_id)->where('role_key', '!=', 'guest')->with('user')->get();
        $now = now()->toIso8601String();
        if ($members->isEmpty()) {
            return $this->result(self::UNKNOWN, [], null, ['cs' => 'V organizaci jsme nenašli žádného člena, o kterém bychom to mohli říct.', 'en' => 'No member of the organization was found to say it about.']);
        }
        $without = $members->filter(fn (OrganizationMembership $m) => ! $m->user instanceof User || ! $m->user->hasMfa())->count();
        $evidence = [$this->evidence('organization_members', $now, Freshness::CURRENT, "{$members->count()} members, {$without} without a confirmed second factor")];

        return $without === 0
            ? $this->result(self::PASS, $evidence, null)
            : $this->result(self::FAIL, $evidence, ['cs' => "{$without} z {$members->count()} členů organizace nemá potvrzené druhé ověření (aplikace nebo klíč). Vyzvěte je, ať si ho v nastavení účtu zapnou; ONhost jim ho sám nezapne.", 'en' => "{$without} of {$members->count()} organization members have no confirmed second factor (app or key). Ask them to turn it on in their account settings; ONhost does not turn it on for them."]);
    }

    private function leastPrivilege(Service $service): array
    {
        $grants = ServiceAccessGrant::query()->where('service_id', $service->id)->whereIn('state', [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE])->get();
        if ($grants->isEmpty()) {
            return $this->result(self::UNKNOWN, [$this->evidence('service_access_grants', now()->toIso8601String(), Freshness::CURRENT, 'no active shares')], null,
                ['cs' => 'Službu nikomu nesdílíte; oprávnění samotných členů organizace ONhost zatím nevyhodnocuje.', 'en' => 'The service is shared with no one; ONhost does not yet evaluate the permissions of the organization\'s own members.']);
        }
        $risky = $grants->filter(fn (ServiceAccessGrant $g) => $g->expires_at === null && array_intersect(self::PRIVILEGED, (array) $g->capabilities) !== []);
        $evidence = [$this->evidence('service_access_grants', now()->toIso8601String(), Freshness::CURRENT, "{$grants->count()} shares, {$risky->count()} with manage/console/data delete and no end date")];

        return $risky->isEmpty()
            ? $this->result(self::PASS, $evidence, null)
            : $this->result(self::FAIL, $evidence, ['cs' => "{$risky->count()} sdílení s právem spravovat, konzolí nebo mazáním dat nemá datum konce. Nastavte jim platnost do data, nebo je odeberte, až je nepotřebují.", 'en' => "{$risky->count()} shares with manage, console or data-delete rights have no end date. Give them an expiry date, or remove them when no longer needed."]);
    }

    /** Headers (cookie flags = false) or the cookie flags (true) of the nightly response-header look. */
    private function headers(Service $service, bool $cookies): array
    {
        $seen = (array) data_get($service->tags, 'security_headers', []);
        if ($seen === []) {
            return $this->result(self::UNKNOWN, [], null, $this->notCurrent(Freshness::UNKNOWN, null));
        }
        $assessment = Freshness::assess('security_headers', $seen['checked_at'] ?? null);
        $evidence = [$this->evidence('security_headers', $assessment['observed_at'], ! empty($seen['error']) ? Freshness::COLLECTION_ERROR : $assessment['state'])];
        if (! empty($seen['error']) || $assessment['state'] !== Freshness::CURRENT) {
            return $this->result(self::UNKNOWN, $evidence, null, $this->notCurrent(! empty($seen['error']) ? Freshness::COLLECTION_ERROR : $assessment['state'], $assessment['observed_at']));
        }
        $missing = array_values(array_filter(array_intersect(SecurityHeadersWatch::CHECKS, array_map('strval', (array) ($seen['missing'] ?? []))),
            fn (string $k) => str_starts_with($k, 'cookie_') === $cookies));
        if ($missing === []) {
            return $this->result(self::PASS, $evidence, null);
        }

        return $this->result(self::FAIL, $evidence, [
            'cs' => implode('; ', array_map(fn (string $k) => SecurityHeadersWatch::ADVICE[$k][0], $missing)).'.',
            'en' => implode('; ', array_map(fn (string $k) => SecurityHeadersWatch::ADVICE[$k][1], $missing)).'.',
        ]);
    }

    /** The nightly look for code in upload folders: all it can say is what it found (or did not find) on that day. */
    private function uploads(Service $service): array
    {
        $seen = (array) data_get($service->tags, 'integrity', []);
        if ($seen === []) {
            return $this->result(self::UNKNOWN, [], null, $this->notCurrent(Freshness::UNKNOWN, null));
        }
        $assessment = Freshness::assess('integrity', $seen['checked_at'] ?? null);
        $evidence = [$this->evidence('site_integrity', $assessment['observed_at'], $assessment['state'], 'code in upload folders / web-shell fingerprints')];
        if ($assessment['state'] !== Freshness::CURRENT) {
            return $this->result(self::UNKNOWN, $evidence, null, $this->notCurrent($assessment['state'], $assessment['observed_at']));
        }
        if ((array) ($seen['findings'] ?? []) === []) {
            return $this->result(self::PASS, $evidence, null, ['cs' => 'Při poslední kontrole jsme ve složkách pro nahrané soubory nenašli spustitelný kód.', 'en' => 'At the last look no executable code was found in the upload folders.']);
        }

        return $this->result(self::FAIL, $evidence, ['cs' => 'Ve složkách pro nahrané soubory jsme našli spustitelný kód nebo otisk webshellu. Zkontrolujte soubory, změňte hesla a zakažte spouštění skriptů v nahrávacích složkách.', 'en' => 'Executable code or a web-shell fingerprint was found in the upload folders. Check the files, change the passwords and disable script execution in the upload folders.']);
    }

    private function restore(Service $service): array
    {
        $test = RestoreTest::health($service);
        if (($test['last_at'] ?? null) === null) {
            return $this->result(self::UNKNOWN, [], ['cs' => 'Obnovu ze zálohy jsme u této služby ještě neověřili. Požádejte o test obnovy, nebo si zálohu zkuste obnovit do zkušebního prostředí.', 'en' => 'A restore from backup has not been tested for this service yet. Ask for a restore test, or try restoring a backup into a trial environment.'], $this->notCurrent(Freshness::UNKNOWN, null));
        }
        $cadence = app(RestoreTest::class)->cadenceDays($service) ?? 35;
        $assessment = Freshness::assess('restore_test', (string) $test['last_at'], $cadence * 2 * 1440);
        $ok = ($test['outcome'] ?? null) === 'ok';
        $evidence = [$this->evidence('restore_test', $assessment['observed_at'], $assessment['state'], $ok ? 'restored in a test' : 'the test did not pass')];
        if ($assessment['state'] !== Freshness::CURRENT) {
            return $this->result(self::UNKNOWN, $evidence, null, $this->notCurrent($assessment['state'], $assessment['observed_at']));
        }

        return $ok
            ? $this->result(self::PASS, $evidence, null)
            : $this->result(self::FAIL, $evidence, ['cs' => 'Poslední test obnovy záloha neprošel. Zálohu nepovažujte za použitelnou, dokud neprojde další test; napište nám, pomůžeme.', 'en' => 'The last restore test did not pass. Do not rely on the backup until the next test passes; write to us and we will help.']);
    }

    /** @param ?array<string,mixed> $finding */
    private function mail(Service $service, ?array $finding): array
    {
        if (! MailDomain::query()->where('service_id', $service->id)->where('state', 'active')->exists()) {
            return $this->result(self::NOT_APPLICABLE, [], null, ['cs' => 'Služba nenese žádnou poštovní doménu.', 'en' => 'The service carries no mail domain.']);
        }

        return $this->fromFinding($finding, 'mail_auth', ['cs' => 'Poštovní domény této služby jsme ještě nekontrolovali (kontrola běží každou noc).', 'en' => 'The mail domains of this service have not been checked yet (the check runs every night).']);
    }

    /** The uptime monitor of the site (web) and the platform's audit chain verification — both must say so. */
    private function monitoring(Service $service): array
    {
        $evidence = [];
        $statuses = [];
        $advice = ['cs' => [], 'en' => []];
        if (in_array($service->family, self::WEB, true)) {
            $monitor = UptimeMonitor::query()->where('service_id', $service->id)->where('enabled', true)->orderBy('created_at')->first();
            if ($monitor === null) {
                $evidence[] = $this->evidence('uptime_monitor', null, Freshness::UNKNOWN, 'not switched on');
                $statuses[] = self::FAIL;
                $advice['cs'][] = 'Zapněte monitoring dostupnosti webu.';
                $advice['en'][] = 'Switch on uptime monitoring for the site.';
            } else {
                $seen = Freshness::assess('monitor', $monitor->last_checked_at, max(15, (int) ceil(((int) $monitor->interval_seconds) * 3 / 60)));
                $evidence[] = $this->evidence('uptime_monitor', $seen['observed_at'], $seen['state']);
                $statuses[] = $seen['state'] === Freshness::CURRENT ? self::PASS : self::UNKNOWN;
            }
        }
        $audit = $this->ledger->last('audit.verify');
        if ($audit === null) {
            $evidence[] = $this->evidence('audit_chain', null, Freshness::UNKNOWN, 'no verification recorded');
            $statuses[] = self::UNKNOWN;
        } elseif (($audit['error'] ?? null) !== null || ($audit['stats']['ok'] ?? null) === false) {
            $evidence[] = $this->evidence('audit_chain', $audit['at'] ?? null, Freshness::COLLECTION_ERROR, 'verification found a break or failed');
            $statuses[] = self::FAIL;
            $advice['cs'][] = 'Kontrola řetězce auditního záznamu platformy hlásí problém; provoz ONhost ho řeší.';
            $advice['en'][] = 'The check of the platform\'s audit-record chain reports a problem; ONhost operations is on it.';
        } else {
            $seen = Freshness::assess('audit_chain', $audit['at'] ?? null, 2 * 1440);
            $evidence[] = $this->evidence('audit_chain', $seen['observed_at'], $seen['state'], 'platform audit chain verified');
            $statuses[] = $seen['state'] === Freshness::CURRENT ? self::PASS : self::UNKNOWN;
        }
        $status = in_array(self::FAIL, $statuses, true) ? self::FAIL : (in_array(self::UNKNOWN, $statuses, true) ? self::UNKNOWN : self::PASS);

        return $this->result($status, $evidence, $status === self::FAIL ? ['cs' => implode(' ', $advice['cs']), 'en' => implode(' ', $advice['en'])] : null,
            $status === self::UNKNOWN ? ['cs' => 'Aspoň jedno z měření chybí nebo je zastaralé.', 'en' => 'At least one of the measurements is missing or old.'] : null);
    }
}
