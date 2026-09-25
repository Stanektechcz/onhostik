<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\ManagedCertificate;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Services\Web\CertificateWatch;

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
     * @return array{service_id:string, name:string, family:string, state:string, verdict:'ok'|'warn'|'bad', findings:list<array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string}>, checked_at:string}
     */
    public function run(Service $service): array
    {
        $findings = [$this->state($service), $this->controlPlane($service)];
        if (in_array($service->family, ['web', 'managed', 'game', 'cloud', 'data'], true)) {
            $findings[] = $this->backup($service);
        }
        if (in_array($service->family, ['web', 'managed'], true)) {
            $findings[] = $this->certificate($service);
            $findings[] = $this->monitor($service);
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
        $last = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('kind', '!=', 'final')->orderByDesc('finished_at')->first();
        if ($last === null || $last->finished_at === null) {
            return self::finding('backup', 'warn', 'Služba zatím nemá žádnou dokončenou zálohu.', 'The service has no finished backup yet.');
        }
        $days = (int) floor($last->finished_at->diffInDays(now(), true));
        $when = $last->finished_at->format('j. n. Y H:i');
        $limit = max(1, (int) config('onhost.backups.coverage_days', 3));

        return $days > $limit
            ? self::finding('backup', 'warn', "Poslední záloha je {$days} dní stará ({$when}).", "The last backup is {$days} days old ({$when}).")
            : self::finding('backup', 'ok', "Poslední záloha: {$when}.", "Last backup: {$when}.");
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

            return match (true) {
                $left < 0 => self::finding('certificate', 'bad', "Certifikát HTTPS vypršel {$until}.", "The HTTPS certificate expired on {$until}."),
                $left <= 10 => self::finding('certificate', 'warn', "Certifikát HTTPS vyprší za {$left} dní ({$until}); obnova probíhá automaticky.", "The HTTPS certificate expires in {$left} days ({$until}); it renews by itself."),
                default => self::finding('certificate', 'ok', "Certifikát HTTPS platí do {$until}.", "The HTTPS certificate is valid until {$until}."),
            };
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

            return match (true) {
                ! empty($seen['mismatch']) => self::finding('certificate', 'bad', "Certifikát, kterým se web hlásí, neplatí pro tuhle doménu (je vystavený na {$names}); prohlížeč návštěvníka ukáže varování.", "The certificate the site presents is not valid for this domain (it covers {$names}); a visitor's browser shows a warning."),
                $left < 0 => self::finding('certificate', 'bad', "Certifikát HTTPS vypršel {$until}; obnovu jsme si vyžádali.", "The HTTPS certificate expired on {$until}; a new one has been requested."),
                $left <= CertificateWatch::renewAtDays() => self::finding('certificate', 'warn', "Certifikát HTTPS vyprší za {$left} dní ({$until}); obnovu jsme si vyžádali.", "The HTTPS certificate expires in {$left} days ({$until}); a new one has been requested."),
                default => self::finding('certificate', 'ok', "Certifikát HTTPS platí do {$until}.", "The HTTPS certificate is valid until {$until}."),
            };
        }
        $state = (string) data_get($service->tags, 'access.certificate', '');

        return $state === 'issued'
            ? self::finding('certificate', 'ok', 'Certifikát HTTPS je vystavený.', 'The HTTPS certificate is issued.')
            : self::finding('certificate', 'warn', 'Web zatím nemá vystavený certifikát HTTPS.', 'The site has no HTTPS certificate yet.');
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    private function monitor(Service $service): array
    {
        $monitor = UptimeMonitor::query()->where('service_id', $service->id)->where('enabled', true)->orderBy('created_at')->first();
        if ($monitor === null) {
            return self::finding('monitor', 'warn', 'Monitoring dostupnosti není zapnutý.', 'Uptime monitoring is not switched on.');
        }

        return $monitor->state === 'down'
            ? self::finding('monitor', 'bad', 'Monitoring hlásí výpadek: '.(string) $monitor->url.'.', 'Monitoring reports an outage: '.(string) $monitor->url.'.')
            : self::finding('monitor', 'ok', 'Monitoring hlásí, že web odpovídá'.($monitor->last_ms ? " ({$monitor->last_ms} ms)" : '').'.', 'Monitoring reports the site answers'.($monitor->last_ms ? " ({$monitor->last_ms} ms)" : '').'.');
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

        return match ((string) ($usage['level'] ?? 'ok')) {
            'full' => self::finding('usage', 'bad', "Služba vyčerpala limit ({$top['key']}, {$top['pct']} %).", "The service has used up its limit ({$top['key']}, {$top['pct']} %)."),
            'critical' => self::finding('usage', 'bad', "Služba je na {$top['pct']} % svého limitu ({$top['key']}).", "The service is at {$top['pct']} % of its limit ({$top['key']})."),
            'warn' => self::finding('usage', 'warn', "Služba se blíží limitu: {$top['pct']} % ({$top['key']}).", "The service is getting close to its limit: {$top['pct']} % ({$top['key']})."),
            default => self::finding('usage', 'ok', "Využití je v pořádku (nejvíc {$top['pct']} %, {$top['key']}).", "Usage is fine (at most {$top['pct']} %, {$top['key']})."),
        };
    }
}
