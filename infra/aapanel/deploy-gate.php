<?php

declare(strict_types=1);

/*
 * The deployer's judge (TASK-0032, onboarding audit C12/C14). deploy.sh used to run `onhost:doctor || true` after the
 * workers had already been restarted on the new code, so nothing the doctor found could stop a release. This helper is
 * installed next to the deployer (/usr/local/lib/onhost-deploy, root-owned, from a verified SHA) and judges the
 * TARGET's `onhost:doctor --json` by row name — the lists live here, with the deployer, so a target cannot rename or
 * drop a row to pass, and an older target is still judged by today's rules.
 *
 * Standalone on purpose: it never loads the application's vendor tree (that is the code being judged).
 *
 * Every other row is judged too (pre-mortem 2026-09-27: the gate used to judge these 11 rows and wave the rest through,
 * although Doctor.php promises that a FAIL fails a production deploy): in production any FAIL row stops the release
 * unless an `Accept-Gate:` line of the owner's signed tag names it; outside production any non-OK row stops it unless
 * the root-owned expected-nonok list names it (the same rows are WARN on staging by design — exactly the ones production
 * FAILs on, so a staging that ignores them teaches ignoring them). The DRAINED rows are the liveness rows: the gate runs
 * while the units are stopped, so they are reported and judged after the start (systemd state, the 6-minute doctor).
 *
 * Subcommands (exit codes in brackets):
 *   verdict     --report F --doctor-rc N --env E --production 0|1 --sha S [--override V] [--accept-file F] [--expected-file F]
 *               [0 pass · 10 a GATED row failed · 11 a HARD row failed, the report is unusable or lies about the env ·
 *                12 another row failed that no Accept-Gate line (production) or expected-nonok line (staging) names]
 *   nonok       --report F [--production 0|1]  prints the rows a release needs listed (staging) or accepted (production) [0 · 2]
 *   env-assert  --file F --spec F             checks the environment file against a spec, never printing a value [0 · 2 · 13]
 *   parse-env   --file F [--key APP_ENV]      prints the value phpdotenv would load (quotes, comment stripped) [0 · 2]
 *   backup-set  --output F --since YmdHis     prints the set `onhost:platform:backup` wrote in this run [0 · 3 · 4]
 *   override    --value V --sha S             validates "<sha12>:<reason of 10+ chars>", prints the reason [0 · 2]
 *   cookie      --down-file F --out F [--ttl s]  writes a curl config (-K) with the maintenance-bypass cookie (0600) [0 · 2]
 *   hint        --file last-good.json --production 0|1   prints the recovery command [0 · 1]
 *   names                                      prints the HARD, GATED and DRAINED lists as JSON (guard test) [0]
 */

final class OnhostDeployGate
{
    /** Never overridable: a release with one of these not OK must not go live, anywhere. */
    public const HARD = [
        'app|APP_DEBUG off',
        'app|APP_KEY set',
        'storage|database reachable',
        'storage|storage/app writable',
        'identity|roles in the database match the catalog',
    ];

    /** Stop the deploy; overridable on staging (SHA-bound ALLOW_DOCTOR_FAIL) or in production by `Accept-Gate:` lines in the signed tag. */
    public const GATED = [
        'app|APP_URL uses https',
        'storage|queue driver',
        'secrets|secrets driver',
        'tls|CA bundle for outbound TLS',
        'catalog|the metering gap ratchet is not growing',
        'storage|platform backup disk off the server',
    ];

    /**
     * The liveness rows. The gate runs while the drained units are stopped, so these are not OK by construction once the
     * window outlasts AutomationLedger::STALE_MINUTES (and always on a staging that runs no scheduler). Reported, never
     * gating here: deploy.sh requires every expected unit active after the start, and the runbooks the doctor six
     * minutes later.
     */
    public const DRAINED = [
        'automation|scheduler running',
        'automation|queue worker alive',
        'mail|outbox is leaving',
    ];

    public const REASON_MIN = 10;

    /** @param array<string, string> $opt */
    public static function verdict(array $opt): int
    {
        $production = ($opt['production'] ?? '1') !== '0'; // anything but an explicit 0 is production (fail closed)
        $rc = (string) ($opt['doctor-rc'] ?? '');
        if ($rc !== '0' && $rc !== '1') { // 1 = the doctor found a FAIL row (judged below); anything else is a crash
            return self::hard("the doctor did not finish (exit {$rc})");
        }
        $report = self::readReport((string) ($opt['report'] ?? ''));
        if ($report === null) {
            return self::hard('the doctor report is not JSON with a checks list');
        }
        $env = (string) ($opt['env'] ?? '');
        if (! is_string($report['environment'] ?? null) || $report['environment'] !== $env) {
            return self::hard('the doctor ran as environment "'.(is_string($report['environment'] ?? null) ? $report['environment'] : '?').'", the deployer decided "'.$env.'" from .env');
        }
        $rows = self::rows($report);
        $hard = [];
        foreach (self::HARD as $name) {
            if (($rows[$name] ?? null) !== 'OK') {
                $hard[] = $name.' ('.($rows[$name] ?? 'missing').')';
            }
        }
        $gated = [];
        foreach (self::GATED as $name) {
            if (($rows[$name] ?? null) !== 'OK') {
                $gated[$name] = $rows[$name] ?? 'missing';
            }
        }
        $expectedFile = (string) ($opt['expected-file'] ?? '');
        if ($production && $expectedFile !== '') {
            fwrite(STDOUT, "IGNORED the expected-nonok list: production accepts a row only by an Accept-Gate line in the signed tag\n");
        }
        $expected = $production ? [] : self::expectedList($expectedFile);
        $others = []; // rows outside the lists that need the owner's Accept-Gate (production) or a line of the expected list (staging)
        foreach ($rows as $name => $status) {
            if ($status === 'OK' || in_array($name, self::HARD, true) || in_array($name, self::GATED, true)) {
                continue;
            }
            if (in_array($name, self::DRAINED, true)) {
                fwrite(STDOUT, "REPORT {$name} ({$status}) — judged after the units start\n");
            } elseif ($production && $status !== 'FAIL') {
                fwrite(STDOUT, "REPORT {$name} ({$status})\n"); // WARN is what the doctor itself calls non-blocking
            } elseif (! $production && isset($expected[$name])) {
                fwrite(STDOUT, "EXPECTED {$name} ({$status})\n");
            } else {
                $others[$name] = $status;
            }
        }
        foreach (array_keys($expected) as $name) {
            if (($rows[$name] ?? null) === 'OK' || ! isset($rows[$name])) {
                fwrite(STDOUT, "CLEARED {$name} (".(isset($rows[$name]) ? 'OK now' : 'not in the report')."): the expected list may drop it\n");
            }
        }
        foreach ($hard as $name) {
            fwrite(STDOUT, "HARD-FAIL {$name}\n");
        }
        if ($hard !== []) {
            fwrite(STDOUT, "VERDICT hard\n");

            return 11;
        }
        $accepted = self::acceptances($opt, $production, array_keys($gated));
        $open = [];
        foreach ($gated as $name => $status) {
            if (isset($accepted[$name])) {
                fwrite(STDOUT, "ACCEPTED {$name} ({$status}) by {$accepted[$name]}\n");
            } else {
                $open[] = $name;
                fwrite(STDOUT, "GATED-FAIL {$name} ({$status})\n");
            }
        }
        $refused = [];
        foreach ($others as $name => $status) {
            if ($production && isset($accepted[$name])) { // outside production $accepted holds GATED rows of the override only
                fwrite(STDOUT, "ACCEPTED {$name} ({$status}) by {$accepted[$name]}\n");
            } else {
                $refused[] = $name;
                fwrite(STDOUT, "ROW-FAIL {$name} ({$status})".($production ? ' — no Accept-Gate line in the signed tag names it' : ' — not on the expected-nonok list')."\n");
            }
        }
        if ($refused !== []) {
            fwrite(STDOUT, "VERDICT row\n");

            return 12;
        }
        if ($open !== []) {
            fwrite(STDOUT, "VERDICT gated\n");

            return 10;
        }
        fwrite(STDOUT, 'VERDICT pass'.($accepted !== [] ? ' (with acceptances)' : '')."\n");

        return 0;
    }

    /**
     * @param  array{checks: list<mixed>}  $report
     * @return array<string, string> `area|check` → status, in report order
     */
    private static function rows(array $report): array
    {
        $rows = [];
        foreach ($report['checks'] as $row) {
            if (is_array($row) && is_string($row['area'] ?? null) && is_string($row['check'] ?? null)) {
                $rows[$row['area'].'|'.$row['check']] = (string) ($row['status'] ?? '');
            }
        }

        return $rows;
    }

    /** @return array<string, true> the rows of a root-owned expected-nonok list (one `area|check` per line; `#` comments) */
    private static function expectedList(string $file): array
    {
        $list = [];
        if ($file === '' || ! is_file($file)) {
            return $list; // deploy.sh refuses a staging release without the file; here no list means no row is expected
        }
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_starts_with($line, '#')) {
                $list[$line] = true;
            }
        }

        return $list;
    }

    /**
     * The rows a release needs the owner's decision on: outside production the non-OK rows the expected-nonok list must
     * name; in production the GATED rows that are not OK and every other FAIL row — the candidates for Accept-Gate lines.
     */
    public static function nonok(string $reportFile, bool $production): int
    {
        $report = self::readReport($reportFile);
        if ($report === null) {
            fwrite(STDERR, "cannot read a doctor report with a checks list from {$reportFile}\n");

            return 2;
        }
        foreach (self::rows($report) as $name => $status) {
            if ($status === 'OK' || in_array($name, self::HARD, true) || in_array($name, self::DRAINED, true)) {
                continue;
            }
            $gated = in_array($name, self::GATED, true);
            if ($production ? ($gated || $status === 'FAIL') : ! $gated) {
                fwrite(STDOUT, $name."\n");
            }
        }

        return 0;
    }

    /**
     * The environment file against a spec (staging-launch.md S3; the deployer runs it before every staging release). Spec
     * lines: `KEY=value` must equal · `KEY=` empty or absent · `KEY?` set · `KEY!=value` must differ · `KEY~=regex`
     * must match; `#` starts a comment line. A value in `<…>` is a placeholder nobody filled: refused. Values are read
     * like phpdotenv (parseEnv) and never printed — the file holds secrets, and a mismatching secret must not show. A key
     * defined twice with different values, or a value holding `${`, is a MISMATCH whatever the rule says.
     */
    public static function envAssert(string $file, string $spec): int
    {
        if (! is_file($file) || ! is_readable($file) || ! is_file($spec) || ! is_readable($spec)) {
            fwrite(STDERR, "cannot read {$file} or {$spec}\n");

            return 2;
        }
        $failed = false;
        foreach (preg_split('/\R/', (string) file_get_contents($spec)) ?: [] as $n => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^([A-Z][A-Z0-9_]*)(\?|!=|~=|=)(.*)$/', $line, $m) !== 1 || ($m[2] === '?' && trim($m[3]) !== '')) {
                fwrite(STDERR, 'spec line '.($n + 1)." is not KEY=value, KEY=, KEY?, KEY!=value or KEY~=regex\n");

                return 2;
            }
            [, $key, $op, $want] = $m;
            $want = trim($want);
            if (preg_match('/<[^>]*>/', $want) === 1) {
                fwrite(STDOUT, "UNFILLED {$key}: the spec still holds a placeholder\n");
                $failed = true;

                continue;
            }
            // not parseEnv's fail-closed '': an empty value would pass `KEY=` and `KEY!=` while phpdotenv loads the last
            // definition; and a `${VAR}` value resolves to something this never saw (review of the post-round-3 commits)
            $values = array_values(array_unique(self::envValues($file, $key)));
            $why = count($values) > 1 ? 'defined twice with different values' : (str_contains($values[0] ?? '', '${') ? 'interpolates ${…}, which is not asserted' : '');
            if ($why !== '') {
                fwrite(STDOUT, "MISMATCH {$key}: {$why}\n");
                $failed = true;

                continue;
            }
            $value = $values[0] ?? '';
            $ok = match ($op) {
                '=' => $value === $want,
                '!=' => $value !== $want,
                '?' => $value !== '',
                default => @preg_match('~'.str_replace('~', '\~', $want).'~', $value) === 1,
            };
            $rule = match ($op) {
                '=' => $want === '' ? 'empty or absent' : '= '.$want,
                '!=' => 'anything but '.$want,
                '?' => 'set',
                default => 'matching '.$want,
            };
            fwrite(STDOUT, $ok ? "OK {$key}\n" : "MISMATCH {$key}: expected {$rule}\n");
            $failed = $failed || ! $ok;
        }

        return $failed ? 13 : 0;
    }

    /**
     * @param  array<string, string>  $opt
     * @param  list<string>  $failing
     * @return array<string, string> row name → who accepted it
     */
    private static function acceptances(array $opt, bool $production, array $failing): array
    {
        $accepted = [];
        $override = (string) ($opt['override'] ?? '');
        if ($override !== '') {
            if ($production) {
                fwrite(STDOUT, "REFUSED ALLOW_DOCTOR_FAIL: production accepts a GATED row only by an Accept-Gate line in the signed tag\n");
            } elseif (self::reason($override, (string) ($opt['sha'] ?? '')) === null) {
                fwrite(STDOUT, 'REFUSED ALLOW_DOCTOR_FAIL: not bound to this SHA or the reason is shorter than '.self::REASON_MIN." characters\n");
            } else {
                foreach ($failing as $name) {
                    $accepted[$name] = 'ALLOW_DOCTOR_FAIL';
                }
            }
        }
        $file = (string) ($opt['accept-file'] ?? '');
        if ($production && $file !== '' && is_file($file)) {
            foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
                // only the signed message counts: deploy.sh hands over the body before the signature, and a line after
                // an armor line is never read — git leaves bytes after `-----END … SIGNATURE-----` unverified (round 3)
                if (str_starts_with(trim($line), '-----BEGIN ')) {
                    break;
                }
                if (preg_match('/^Accept-Gate:\s*(.+?)\s+(?:—|--)\s+(.+)$/u', trim($line), $m) !== 1) {
                    continue;
                }
                $name = trim($m[1]);
                // any row but a HARD one: since the pre-mortem of 2026-09-27 every FAIL row stops a production release
                if (in_array($name, self::HARD, true) || preg_match('/^[^|]+\|.+$/', $name) !== 1 || mb_strlen(trim($m[2])) < self::REASON_MIN) {
                    fwrite(STDOUT, "IGNORED Accept-Gate for {$name}: only a row that is not HARD, named area|check, with a reason of ".self::REASON_MIN."+ characters\n");

                    continue;
                }
                $accepted[$name] = 'Accept-Gate in the signed tag';
            }
        }

        return $accepted;
    }

    /** @return array{environment?: mixed, checks: list<mixed>}|null */
    private static function readReport(string $path): ?array
    {
        $raw = $path !== '' && is_file($path) ? (string) file_get_contents($path) : '';
        $data = json_decode(trim($raw), true);
        if (! is_array($data)) { // a PHP notice printed before the JSON: take the outermost object
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            $data = $start !== false && $end !== false && $end > $start ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;
        }
        if (! is_array($data) || ! is_array($data['checks'] ?? null) || ! array_is_list($data['checks'])) {
            return null;
        }

        return $data;
    }

    private static function hard(string $why): int
    {
        fwrite(STDOUT, "HARD-FAIL {$why}\nVERDICT hard\n");

        return 11;
    }

    /** The reason of a "<sha12>:<reason>" value when it is bound to $sha, else null. */
    public static function reason(string $value, string $sha): ?string
    {
        if (preg_match('/^([0-9a-f]{12}):(.+)$/s', trim($value), $m) !== 1 || strlen($sha) < 12 || $m[1] !== strtolower(substr($sha, 0, 12))) {
            return null;
        }
        $reason = trim(preg_replace('/\s+/', ' ', $m[2]) ?? '');

        return mb_strlen($reason) >= self::REASON_MIN ? $reason : null;
    }

    /**
     * What phpdotenv loads for $key: the last definition wins; quotes are stripped, and an unquoted value ends at its
     * first `#` — also right after `=` (`KEY=     # comment` is empty, the way `.env.example` writes an empty key;
     * VERIFIED against vlucas/phpdotenv 5 on 2026-09-27, before which this read the comment as the value). Two
     * definitions with different values are ambiguous and print nothing — the caller then treats the environment as
     * production (fail closed); envAssert reads envValues itself and refuses the ambiguity by name.
     */
    public static function parseEnv(string $file, string $key): ?string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }
        $values = self::envValues($file, $key);

        return count(array_unique($values)) === 1 ? $values[0] : '';
    }

    /**
     * Every definition of $key in $file, in order, each read the way phpdotenv reads it (parseEnv).
     *
     * @return list<string>
     */
    private static function envValues(string $file, string $key): array
    {
        $values = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?'.preg_quote($key, '/').'\s*=(.*)$/', $line, $m) !== 1) {
                continue;
            }
            $value = ltrim($m[1]);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $end = strpos($value, $value[0], 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            } else {
                $value = trim(explode('#', $value, 2)[0]);
            }
            $values[] = $value;
        }

        return $values;
    }

    /** The set named by `onhost:platform:backup` ("Set platform-backups/<Ymd-His> on disk …"), only when written at or after $since. */
    public static function backupSet(string $output, string $since): int
    {
        $text = (string) preg_replace('/\e\[[0-9;]*m/', '', $output);
        if (preg_match_all('#^\s*Set (platform-backups/(\d{8}-\d{6})) on disk #m', $text, $m) !== 1) {
            fwrite(STDERR, "no single 'Set platform-backups/<ts> on disk' line: the backup did not run (switched off?)\n");

            return 3;
        }
        if (preg_match('/^\d{8}-\d{6}$/', $since) !== 1 || strcmp($m[2][0], $since) < 0) {
            fwrite(STDERR, "the set {$m[1][0]} is older than this run ({$since})\n");

            return 4;
        }
        fwrite(STDOUT, $m[1][0]."\n");

        return 0;
    }

    /**
     * A curl config file (`curl -K`; `-H @file` would need curl 7.55+) whose Cookie header passes Laravel's maintenance
     * check (MaintenanceModeBypassCookie::isValid — HMAC of the expiry with the secret `artisan down --with-secret` wrote
     * into storage/framework/down). The secret is read from that file and never printed; the config is 0600, expires
     * with the run and is deleted by the deployer.
     */
    public static function cookie(string $downFile, string $out, int $ttl): int
    {
        $down = is_file($downFile) ? json_decode((string) file_get_contents($downFile), true) : null;
        $secret = is_array($down) && is_string($down['secret'] ?? null) ? $down['secret'] : '';
        if ($secret === '') {
            fwrite(STDERR, "no bypass secret in {$downFile}\n");

            return 2;
        }
        $expires = time() + max(60, min($ttl, 3600));
        $value = base64_encode((string) json_encode(['expires_at' => $expires, 'mac' => hash_hmac('sha256', (string) $expires, $secret)]));
        $old = umask(0077);
        $written = file_put_contents($out, 'header = "Cookie: laravel_maintenance='.rawurlencode($value)."\"\n");
        umask($old);
        @chmod($out, 0600);

        return $written === false ? 2 : 0;
    }

    public static function hint(string $file, bool $production): int
    {
        $last = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($last) || preg_match('/^[0-9a-f]{40}$/', (string) ($last['sha'] ?? '')) !== 1) {
            fwrite(STDOUT, "no last good release is recorded here: fix forward (a new REF), or restore by hand (docs/runbooks/release-and-rollback.md)\n");

            return 1;
        }
        $tag = (string) ($last['tag'] ?? '');
        if ($production && $tag === '') {
            fwrite(STDOUT, "the last good release {$last['sha']} has no tag; production deploys only signed tags — ask the owner for one\n");

            return 1;
        }
        $ref = $production ? $tag : ($tag !== '' ? $tag : $last['sha']);
        fwrite(STDOUT, "REF={$ref} EXPECTED_SHA={$last['sha']} DEPLOY_OPERATOR=<you> /usr/local/sbin/onhost-deploy\n");

        return 0;
    }

    /**
     * @param  list<string>  $argv
     * @return array<string, string>
     */
    public static function options(array $argv): array
    {
        $opt = [];
        for ($i = 0; $i < count($argv); $i++) {
            if (str_starts_with($argv[$i], '--')) {
                $name = substr($argv[$i], 2);
                if (str_contains($name, '=')) {
                    [$name, $value] = explode('=', $name, 2);
                    $opt[$name] = $value;
                } else {
                    $opt[$name] = $argv[$i + 1] ?? '';
                    $i++;
                }
            }
        }

        return $opt;
    }

    /** @param list<string> $argv */
    public static function main(array $argv): int
    {
        $command = $argv[1] ?? '';
        $opt = self::options(array_slice($argv, 2));

        switch ($command) {
            case 'verdict':
                return self::verdict($opt);
            case 'nonok':
                return self::nonok((string) ($opt['report'] ?? ''), ($opt['production'] ?? '0') !== '0');
            case 'env-assert':
                return self::envAssert((string) ($opt['file'] ?? ''), (string) ($opt['spec'] ?? ''));
            case 'parse-env':
                $value = self::parseEnv((string) ($opt['file'] ?? ''), (string) ($opt['key'] ?? 'APP_ENV'));
                if ($value === null) {
                    fwrite(STDERR, "cannot read {$opt['file']}\n");

                    return 2;
                }
                fwrite(STDOUT, $value."\n");

                return 0;
            case 'backup-set':
                return self::backupSet(is_file((string) ($opt['output'] ?? '')) ? (string) file_get_contents((string) $opt['output']) : '', (string) ($opt['since'] ?? ''));
            case 'override':
                $reason = self::reason((string) ($opt['value'] ?? ''), (string) ($opt['sha'] ?? ''));
                if ($reason === null) {
                    fwrite(STDERR, 'expected "<first 12 characters of the target SHA>:<reason of at least '.self::REASON_MIN." characters>\"\n");

                    return 2;
                }
                fwrite(STDOUT, $reason."\n");

                return 0;
            case 'cookie':
                return self::cookie((string) ($opt['down-file'] ?? ''), (string) ($opt['out'] ?? ''), (int) ($opt['ttl'] ?? 900));
            case 'hint':
                return self::hint((string) ($opt['file'] ?? ''), ($opt['production'] ?? '1') !== '0');
            case 'names':
                fwrite(STDOUT, (string) json_encode(['hard' => self::HARD, 'gated' => self::GATED, 'drained' => self::DRAINED], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

                return 0;
            default:
                fwrite(STDERR, "usage: deploy-gate.php verdict|nonok|env-assert|parse-env|backup-set|override|cookie|hint|names [--options]\n");

                return 64;
        }
    }
}

exit(OnhostDeployGate::main(array_values($argv)));
