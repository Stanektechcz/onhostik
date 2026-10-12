<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Carbon\CarbonImmutable;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Freshness;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\SecurityPosture;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\Web\RestoreTest;

/**
 * The monthly customer report (W1-10): per active service, what was checked and when. Built only from
 * ServiceHealthCheck findings (with their observed_at / state) plus the last complete backup and the last recorded
 * restore test. It never gives a verdict: every line names what was looked at and the day it was looked at, and a
 * finding that is stale, never measured or whose collector failed is printed as "neověřeno" with the last observed
 * time — never as fine. A value that is not known is not printed as 0.
 *
 * There is no customer-visible on-call concept in the platform (OnCallRota is staff-only), so that item is not part
 * of the report.
 */
final class MonthlyCustomerReport
{
    /** @var array<string,array{0:string,1:string}> health finding key => label (cs, en), in the order printed */
    private const FINDINGS = [
        'state' => ['Stav služby', 'Service status'],
        'certificate' => ['Certifikát HTTPS', 'HTTPS certificate'],
        'monitor' => ['Monitoring dostupnosti', 'Uptime monitoring'],
        'security_headers' => ['Hlavičky odpovědí webu', 'Site response headers'],
        'origin_exposure' => ['Adresa původního serveru za CDN', 'Origin server address behind the CDN'],
        'dnssec' => ['DNSSEC', 'DNSSEC'],
    ];

    private const BACKUP_FAMILIES = ['web', 'managed', 'game', 'cloud', 'data'];

    public function __construct(private readonly ServiceHealthCheck $health, private readonly SecurityPosture $posture) {}

    /** @return array{title:string, body:string, text:string, sections:array<string,list<string>>}|null */
    public function build(Organization $organization, ?CarbonImmutable $now = null): ?array
    {
        $now ??= CarbonImmutable::now();
        $en = ($organization->locale ?? 'cs') === 'en';
        $services = Service::query()->where('organization_id', $organization->id)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('created_at')->get();
        if ($services->isEmpty()) {
            return null;
        }
        $sections = [];
        foreach ($services as $service) {
            $check = $this->health->run($service);
            $lines = [];
            foreach ($check['findings'] as $finding) {
                if (isset(self::FINDINGS[$finding['key']])) {
                    $lines[] = $this->line(self::FINDINGS[$finding['key']][$en ? 1 : 0], $finding, $now, $en);
                }
            }
            if (in_array($service->family, self::BACKUP_FAMILIES, true)) {
                $lines[] = $this->backupLine($service, $now, $en);
            }
            if (($restore = $this->restoreLine($service, $en)) !== null) {
                $lines[] = $restore;
            }
            $lines[] = $this->postureLine($this->posture->run($service, $check)['summary'], $now, $en);
            $sections[(string) $check['name']] = $lines;
        }
        $sections[$en ? 'About this report' : 'K tomuto přehledu'] = [$en
            ? 'Each line says what was checked and when. It describes those days only; it is not a guarantee for any other day.'
            : 'Každý řádek říká, co jsme zkontrolovali a kdy. Popisuje jen uvedené dny, není to záruka pro jiný den.'];

        $text = '';
        foreach ($sections as $heading => $lines) {
            $text .= mb_strtoupper((string) $heading)."\n".implode("\n", array_map(fn ($l) => '· '.$l, $lines))."\n\n";
        }
        $title = ($en ? 'Monthly service report ' : 'Měsíční přehled služeb ').$now->format('n/Y');

        return ['title' => $title, 'body' => mb_substr(implode(' · ', array_map(fn ($lines) => $lines[0], $sections)), 0, 900), 'text' => trim($text), 'sections' => $sections];
    }

    /** @param array{key:string, level:string, cs:string, en:string, observed_at?:?string, state?:string} $finding */
    private function line(string $label, array $finding, CarbonImmutable $now, bool $en): string
    {
        $state = $finding['state'] ?? null;
        if ($state === null) { // a platform record read just now (service state): checked today
            return "{$label}: ".($en ? $finding['en'] : $finding['cs']).' ('.$this->checked($now, $en).')';
        }
        if ($state !== Freshness::CURRENT) {
            return $this->unverified($label, $state, $finding['observed_at'] ?? null, $en);
        }
        $at = ($finding['observed_at'] ?? null) !== null ? CarbonImmutable::parse((string) $finding['observed_at']) : $now;

        return "{$label}: ".($en ? $finding['en'] : $finding['cs']).' ('.$this->checked($at, $en).')';
    }

    /**
     * Counts only — never a verdict. "Not verified" are the controls ONhost does not measure or whose measurement is old;
     * they are not good news and the line does not call the service secure.
     *
     * @param  array{pass:int, fail:int, unknown:int, not_applicable:int}  $summary
     */
    private function postureLine(array $summary, CarbonImmutable $now, bool $en): string
    {
        $label = $en ? 'Security controls' : 'Bezpečnostní kontroly';

        return $en
            ? "{$label}: {$summary['pass']} met, {$summary['fail']} not met, {$summary['unknown']} not verified (".$this->checked($now, $en).')'
            : "{$label}: {$summary['pass']} splněno, {$summary['fail']} nesplněno, {$summary['unknown']} neověřeno (".$this->checked($now, $en).')';
    }

    private function backupLine(Service $service, CarbonImmutable $now, bool $en): string
    {
        $label = $en ? 'Last complete backup' : 'Poslední úplná záloha';
        // a set marked incomplete is not a backup we can report; the newest whole one is
        $last = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('kind', '!=', 'final')->whereNull('meta->incomplete')->whereNotNull('finished_at')->orderByDesc('finished_at')->first();
        if ($last === null) {
            return $this->unverified($label, Freshness::UNKNOWN, null, $en);
        }
        $limit = max(1, (int) config('onhost.backups.coverage_days', 3)) * 1440;
        $assessment = Freshness::assess('backup', $last->finished_at, $limit);
        if ($assessment['state'] !== Freshness::CURRENT) {
            return $this->unverified($label, $assessment['state'], $assessment['observed_at'], $en);
        }

        return "{$label}: ".$this->day($last->finished_at, $en).' ('.$this->checked($now, $en).')';
    }

    private function restoreLine(Service $service, bool $en): ?string
    {
        $test = RestoreTest::health($service);
        if (($test['last_at'] ?? null) === null) {
            return null; // never recorded: nothing to report
        }
        $at = CarbonImmutable::parse((string) $test['last_at']);
        $ok = ($test['outcome'] ?? null) === 'ok';
        if (($test['kind'] ?? null) === 'verified') { // W2-4: the verified restore add-on — facts and the two measured times, no verdict beyond them
            $label = $en ? 'Verified restore' : 'Ověřená obnova';
            $result = match (true) {
                ! $ok => $en ? 'the restore into an isolated copy did not pass' : 'obnova do oddělené kopie neprošla',
                ($test['verdict'] ?? null) === 'passed' => $en ? 'restored into an isolated copy, the copy answered HTTP 200' : 'obnoveno do oddělené kopie, kopie odpověděla HTTP 200',
                default => $en ? 'restored into an isolated copy, the site was not requested over HTTP' : 'obnoveno do oddělené kopie, web nebyl přes HTTP dotázán',
            };
            $times = [];
            if (is_numeric($test['rpo_seconds'] ?? null)) {
                $times[] = 'RPO '.self::duration((int) $test['rpo_seconds']);
            }
            if (is_numeric($test['rto_seconds'] ?? null)) {
                $times[] = 'RTO '.self::duration((int) $test['rto_seconds']);
            }

            return "{$label}: {$result}".($times === [] ? '' : ' · '.implode(', ', $times)).' ('.$this->checked($at, $en).')';
        }
        $label = $en ? 'Backup restore test' : 'Test obnovy zálohy';
        $result = $ok ? ($en ? 'the backup was restored in a test' : 'záloha se v testu podařilo obnovit') : ($en ? 'the test did not pass' : 'test neprošel');

        return "{$label}: {$result} (".$this->checked($at, $en).')';
    }

    /** A measured time as the report prints it: minutes below two hours, hours below two days, days above. */
    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 120 => $seconds.' s',
            $seconds < 7200 => intdiv($seconds, 60).' min',
            $seconds < 172800 => intdiv($seconds, 3600).' h',
            default => intdiv($seconds, 86400).' d',
        };
    }

    private function unverified(string $label, string $state, ?string $observedAt, bool $en): string
    {
        $at = $observedAt !== null ? CarbonImmutable::parse($observedAt) : null;
        $reason = match ($state) {
            Freshness::COLLECTION_ERROR => $en ? 'the last check failed' : 'poslední kontrola selhala',
            Freshness::STALE => $en ? 'the reading is old' : 'údaj je starý',
            default => $en ? 'not checked yet' : 'zatím nekontrolováno',
        };
        $last = $at !== null ? ($en ? '; last observed ' : '; naposledy změřeno ').$this->day($at, $en).$at->format(' H:i') : '';

        return $label.': '.($en ? 'not verified' : 'neověřeno')." ({$reason}{$last})";
    }

    private function checked(CarbonImmutable $at, bool $en): string
    {
        return ($en ? 'verified ' : 'ověřeno ').$this->day($at, $en);
    }

    private function day(\DateTimeInterface $at, bool $en): string
    {
        return CarbonImmutable::instance($at)->format($en ? 'j M Y' : 'j. n. Y');
    }
}
