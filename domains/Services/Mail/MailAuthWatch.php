<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail;

use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Dns\RecordResolver;
use Throwable;

/**
 * Whether the mail a domain sends can be believed by the receiver: SPF, DKIM and DMARC as PUBLIC DNS shows them (W2-3).
 *
 * This is not `PublicDnsCheck` (which compares what the internet answers with what the platform published and repairs our
 * own zones). It reads, for every mail domain ONhost hosts, the three records the receiving server reads and judges them
 * the way a receiver would:
 *
 *   SPF    exactly one record (two make the result a permanent error), it admits our sending hosts, and evaluating it
 *          needs at most ten DNS lookups (more is a permanent error, too)
 *   DKIM   the selector we sign with exists in DNS and carries the very key we publish (`p=` equal)
 *   DMARC  present (once), with a policy other than `none`, and a `rua` address for the reports
 *
 * The result is written to `tags.mail_auth` and shown as the `mail_auth` finding. Advice only — nothing is changed, no
 * message is sent. A domain that answers nothing at all (no MX, TXT or NS) is not judged: `dns_get_record()` cannot tell a
 * failed lookup from an empty answer, so the run says "could not be read" (collection_error) and never "missing".
 */
final class MailAuthWatch
{
    public const MAX_LOOKUPS = 10;

    public function __construct(private readonly RecordResolver $dns) {}

    /** @return array{checked:int, bad:int, warn:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'bad' => 0, 'warn' => 0, 'errors' => 0];
        $ids = MailDomain::query()->where('state', 'active')->pluck('service_id')->unique()->values()->all();
        if ($ids === []) {
            return $stats;
        }
        $services = Service::query()->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereIn('family', ['web', 'managed', 'mail'])
            ->whereIn('id', $ids)->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            $domains = [];
            foreach (MailDomain::query()->where('service_id', $service->id)->where('state', 'active')->orderBy('domain')->get() as $mail) {
                $domains[] = $this->inspect($service, $mail);
            }
            $levels = array_map(fn (array $d) => self::level($d), $domains);
            $tags = (array) ($service->tags ?? []);
            $tags['mail_auth'] = ['domains' => $domains, 'checked_at' => now()->toIso8601String()] + (in_array('error', $levels, true) ? ['error' => 'dns'] : []);
            $service->forceFill(['tags' => $tags])->save();
            $stats['checked']++;
            $stats['bad'] += in_array('bad', $levels, true) ? 1 : 0;
            $stats['warn'] += in_array('warn', $levels, true) ? 1 : 0;
            $stats['errors'] += in_array('error', $levels, true) ? 1 : 0;
        }

        return $stats;
    }

    /**
     * @return array{domain:string, error:?string, spf:array<string,mixed>, dkim:array<string,mixed>, dmarc:array<string,mixed>}
     */
    public function inspect(Service $service, MailDomain $mail): array
    {
        $domain = mb_strtolower(rtrim(trim((string) $mail->domain), '.'));
        $out = ['domain' => $domain, 'error' => null, 'spf' => ['state' => 'error'], 'dkim' => ['state' => 'error'], 'dmarc' => ['state' => 'error']];
        try {
            $txt = $this->txt($domain);
            $mx = array_values(array_filter(array_map(fn (array $r) => rtrim(mb_strtolower((string) ($r['target'] ?? '')), '.'), $this->dns->records($domain, 'MX'))));
            if ($txt === [] && $mx === [] && $this->dns->records($domain, 'NS') === []) {
                $out['error'] = 'no_answer'; // nothing answers: a lookup that failed looks exactly like this

                return $out;
            }
            $out['spf'] = $this->spf($service, $txt, $mx);
            $out['dkim'] = $this->dkim($domain, (string) ($mail->dkim_selector ?? ''), (string) ($mail->dkim_public ?? ''));
            $out['dmarc'] = $this->dmarc($domain);
        } catch (Throwable) {
            $out['error'] = 'dns';
            $out['spf'] = $out['dkim'] = $out['dmarc'] = ['state' => 'error'];
        }

        return $out;
    }

    /** ok | warn | bad | error — the worst of the three records. @param array<string,mixed> $d */
    public static function level(array $d): string
    {
        if (($d['error'] ?? null) !== null) {
            return 'error';
        }
        $spf = (string) ($d['spf']['state'] ?? 'error');
        $dkim = (string) ($d['dkim']['state'] ?? 'error');
        $dmarc = (string) ($d['dmarc']['state'] ?? 'error');
        if (in_array($spf, ['missing', 'multiple', 'without_us', 'too_many_lookups'], true) || in_array($dkim, ['missing', 'mismatch'], true) || in_array($dmarc, ['multiple', 'invalid'], true)) {
            return 'bad';
        }
        if ($spf !== 'ok' || $dkim !== 'ok' || $dmarc !== 'ok') {
            return 'warn'; // permissive SPF, no key on record, DMARC missing / policy none / no rua
        }

        return 'ok';
    }

    /**
     * The advice for one measured domain: concrete, both languages, never applied by us.
     *
     * @param  array<string,mixed>  $d
     * @return list<array{0:string,1:string}> [cs, en] sentences
     */
    public static function advice(array $d): array
    {
        $name = (string) $d['domain'];
        $include = (string) config('onhost.dns.spf_include', '');
        $out = [];
        switch ((string) ($d['spf']['state'] ?? '')) {
            case 'missing':
                $out[] = ["{$name}: chybí SPF — přidejte TXT záznam „v=spf1 mx include:{$include} -all“.", "{$name}: SPF is missing — add a TXT record \"v=spf1 mx include:{$include} -all\"."];
                break;
            case 'multiple':
                $out[] = ["{$name}: SPF má více než jeden záznam, příjemce jej proto odmítne — ponechte jediný TXT záznam začínající „v=spf1“ a ostatní sloučte do něj.", "{$name}: there is more than one SPF record, so receivers reject the result — keep a single TXT record starting with \"v=spf1\" and merge the others into it."];
                break;
            case 'without_us':
                $out[] = ["{$name}: SPF nezahrnuje naše odesílací servery — přidejte „include:{$include}“.", "{$name}: SPF does not admit our sending servers — add \"include:{$include}\"."];
                break;
            case 'too_many_lookups':
                $n = (int) ($d['spf']['lookups'] ?? 0);
                $out[] = ["{$name}: SPF potřebuje víc než ".MailAuthWatch::MAX_LOOKUPS." DNS dotazů (naměřeno {$n}+), příjemce jej odmítne — odeberte nepoužívané „include:“ nebo je nahraďte adresami (ip4:/ip6:).", "{$name}: SPF needs more than ".MailAuthWatch::MAX_LOOKUPS." DNS lookups ({$n}+ measured), receivers reject it — remove unused \"include:\" entries or replace them with addresses (ip4:/ip6:)."];
                break;
            case 'permissive':
                $out[] = ["{$name}: SPF nekončí pravidlem „-all“ (nebo „~all“) — kdokoli může psát za vaši doménu; zakončete jej „-all“.", "{$name}: SPF does not end with \"-all\" (or \"~all\") — anyone may send as your domain; end it with \"-all\"."];
                break;
        }
        switch ((string) ($d['dkim']['state'] ?? '')) {
            case 'missing':
                $sel = (string) ($d['dkim']['selector'] ?? '');
                $out[] = ["{$name}: v DNS chybí klíč DKIM {$sel}._domainkey — publikujte TXT záznam s naším veřejným klíčem, jinak nejde pošta ověřit.", "{$name}: the DKIM key {$sel}._domainkey is missing in DNS — publish the TXT record with our public key, otherwise mail cannot be verified."];
                break;
            case 'mismatch':
                $sel = (string) ($d['dkim']['selector'] ?? '');
                $out[] = ["{$name}: DKIM {$sel}._domainkey v DNS nese jiný klíč, než kterým pošta podepisujeme — nahraďte jej aktuálním klíčem z panelu.", "{$name}: DKIM {$sel}._domainkey carries a different key from the one we sign with — replace it with the current key from the panel."];
                break;
            case 'no_key':
                $out[] = ["{$name}: nemáme uložený klíč DKIM, takže ho nemáme s čím porovnat.", "{$name}: we hold no DKIM key on record, so there is nothing to compare DNS with."];
                break;
        }
        switch ((string) ($d['dmarc']['state'] ?? '')) {
            case 'missing':
                $out[] = ["{$name}: chybí DMARC — přidejte TXT záznam _dmarc.{$name} „v=DMARC1; p=quarantine; rua=mailto:dmarc@{$name}“.", "{$name}: DMARC is missing — add a TXT record _dmarc.{$name} \"v=DMARC1; p=quarantine; rua=mailto:dmarc@{$name}\"."];
                break;
            case 'multiple':
                $out[] = ["{$name}: DMARC má více záznamů, příjemci jej ignorují — ponechte jediný.", "{$name}: there is more than one DMARC record, receivers ignore it — keep a single one."];
                break;
            case 'invalid':
                $out[] = ["{$name}: DMARC záznam nemá platnou politiku p= — použijte „p=quarantine“ nebo „p=reject“.", "{$name}: the DMARC record has no valid p= policy — use \"p=quarantine\" or \"p=reject\"."];
                break;
            case 'weak':
                $out[] = ["{$name}: DMARC má politiku „none“, jen pozoruje a nic nezastaví — po ověření zpráv přejděte na „p=quarantine“.", "{$name}: DMARC policy is \"none\", it only observes and stops nothing — move to \"p=quarantine\" once the reports look right."];
                break;
            case 'no_rua':
                $out[] = ["{$name}: DMARC nemá adresu pro hlášení (rua=) — přidejte „rua=mailto:dmarc@{$name}“, ať se o zneužití domény dozvíte.", "{$name}: DMARC has no report address (rua=) — add \"rua=mailto:dmarc@{$name}\" so you hear about abuse of the domain."];
                break;
        }

        return $out;
    }

    /** @return list<string> every TXT value of a name, one string per record */
    private function txt(string $name): array
    {
        return array_values(array_map(fn (array $r) => (string) ($r['txt'] ?? ''), $this->dns->records($name, 'TXT')));
    }

    /**
     * @param  list<string>  $txt  apex TXT records
     * @param  list<string>  $mx  MX targets of the domain
     * @return array<string,mixed>
     */
    private function spf(Service $service, array $txt, array $mx): array
    {
        $records = array_values(array_filter($txt, fn (string $v) => self::isSpf($v)));
        if ($records === []) {
            return ['state' => 'missing'];
        }
        if (count($records) > 1) {
            return ['state' => 'multiple', 'count' => count($records)];
        }
        $record = mb_strtolower(trim($records[0]));
        $lookups = $this->lookups($record, 0, $seen);
        $include = mb_strtolower((string) config('onhost.dns.spf_include', ''));
        $ourMx = rtrim(mb_strtolower(MailSettings::host($service)), '.');
        $admitted = ($include !== '' && str_contains($record, 'include:'.$include)) || (preg_match('/(^|\s)[+]?mx(\s|$)/', $record) === 1 && in_array($ourMx, $mx, true));
        if (! $admitted) {
            return ['state' => 'without_us', 'lookups' => $lookups];
        }
        if ($lookups > self::MAX_LOOKUPS) {
            return ['state' => 'too_many_lookups', 'lookups' => $lookups];
        }
        // "+all", "?all" or no ending at all: the record says yes (or nothing) to everybody else
        if (preg_match('/(^|\s)[+?]all(\s|$)/', $record) === 1 || preg_match('/(^|\s)[-~]all(\s|$)|(^|\s)redirect=/', $record) !== 1) {
            return ['state' => 'permissive', 'lookups' => $lookups];
        }

        return ['state' => 'ok', 'lookups' => $lookups];
    }

    /** DNS lookups the evaluation of a record costs (includes and redirects followed); counting stops past the limit. */
    private function lookups(string $record, int $depth, ?array &$seen = null): int
    {
        $seen ??= [];
        $count = 0;
        foreach (preg_split('/\s+/', trim($record)) ?: [] as $term) {
            $term = ltrim($term, '+-~?');
            if (preg_match('/^(include:(.+)|redirect=(.+))$/', $term, $m) === 1) {
                $count++;
                $target = rtrim((string) ($m[2] !== '' ? $m[2] : ($m[3] ?? '')), '.');
                if ($depth < self::MAX_LOOKUPS && $target !== '' && ! isset($seen[$target]) && $count <= self::MAX_LOOKUPS) {
                    $seen[$target] = true;
                    foreach ($this->txt($target) as $value) {
                        if (self::isSpf($value)) {
                            $count += $this->lookups(mb_strtolower($value), $depth + 1, $seen);
                            break;
                        }
                    }
                }
            } elseif (preg_match('/^(a|mx|ptr)([:\/].*)?$/', $term) === 1 || str_starts_with($term, 'exists:')) {
                $count++;
            }
            if ($count > self::MAX_LOOKUPS) {
                return $count;
            }
        }

        return $count;
    }

    private static function isSpf(string $value): bool
    {
        return preg_match('/^\s*v=spf1(\s|$)/i', $value) === 1;
    }

    /** @return array<string,mixed> */
    private function dkim(string $domain, string $selector, string $public): array
    {
        $ours = self::keyOf((string) preg_replace('/-----[A-Z ]+-----/', '', $public));
        if ($selector === '' || $ours === '') {
            return ['state' => 'no_key', 'selector' => $selector];
        }
        $found = $this->txt($selector.'._domainkey.'.$domain);
        $records = array_values(array_filter($found, fn (string $v) => trim($v) !== ''));
        if ($records === []) {
            return ['state' => 'missing', 'selector' => $selector];
        }
        foreach ($records as $value) {
            if (preg_match('/(^|;)\s*p=([^;]*)/i', $value, $m) === 1 && self::keyOf($m[2]) === $ours) {
                return ['state' => 'ok', 'selector' => $selector];
            }
        }

        return ['state' => 'mismatch', 'selector' => $selector];
    }

    private static function keyOf(string $key): string
    {
        return (string) preg_replace('/[\s"]+/', '', $key);
    }

    /** @return array<string,mixed> */
    private function dmarc(string $domain): array
    {
        $records = array_values(array_filter($this->txt('_dmarc.'.$domain), fn (string $v) => preg_match('/^\s*v=dmarc1\s*(;|$)/i', $v) === 1));
        if ($records === []) {
            return ['state' => 'missing'];
        }
        if (count($records) > 1) {
            return ['state' => 'multiple'];
        }
        $tags = [];
        foreach (explode(';', $records[0]) as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = array_map('trim', explode('=', $part, 2));
                $tags[mb_strtolower($k)] = $v;
            }
        }
        $policy = mb_strtolower((string) ($tags['p'] ?? ''));
        $rua = trim((string) ($tags['rua'] ?? '')) !== '';
        $state = match (true) {
            ! in_array($policy, ['none', 'quarantine', 'reject'], true) => 'invalid',
            $policy === 'none' => 'weak',
            ! $rua => 'no_rua',
            default => 'ok',
        };

        return ['state' => $state, 'policy' => $policy, 'rua' => $rua];
    }
}
