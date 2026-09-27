<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\AutomationLedger;
use Symfony\Component\Process\Process;

/*
 * TASK-0032 (onboarding audit C12/C14): a deploy stops when the readiness check fails. deploy.sh used to run
 * `onhost:doctor || true` after the workers had been restarted on the new code, never verified the backup it took and
 * reset a branch. Now the installed deployer drains, goes down, backs up and verifies, switches to a pinned SHA, and
 * judges the target's doctor by row name (infra/aapanel/deploy-gate.php) before anything starts again.
 *
 * (a) the judge itself through Process on every OS; (b) the row names it gates on exist in a real doctor run;
 * (c) the text of the backup commands deploy.sh parses; (d) the whole script against stubs in a throw-away sandbox
 * (infra/aapanel/ci/deploy-sandbox.sh) — needs Git Bash on Windows, skipped without bash except in CI.
 * ONHOST_DEPLOY_SCRIPT=<path> runs (d) against another script (red evidence against the pre-TASK-0032 deploy.sh).
 */

function deployGateHelper(): string
{
    return base_path('infra/aapanel/deploy-gate.php');
}

/** @param list<string> $args */
function deployGateCall(array $args): Process
{
    $process = new Process(array_merge([PHP_BINARY, deployGateHelper()], $args));
    $process->setTimeout(30);
    $process->run();

    return $process;
}

function deployGateTempDir(): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'onhost-deploy-gate-'.uniqid();
    File::ensureDirectoryExists($dir);

    return $dir;
}

function deployGateRemoveTree(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        @chmod($item->getPathname(), 0777); // git writes read-only objects; Windows refuses to unlink them
        $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/**
 * A doctor report in which every HARD and GATED row is OK, plus one liveness row that is WARN (the units are drained
 * while the gate runs). A `$status` entry for a name outside those lists adds that row (pre-mortem 2026-09-27: the
 * rows outside the lists are judged too).
 *
 * @param  array<string, string>  $status  row name → status to change or add
 * @return array<string, mixed>
 */
function deployGateReport(array $status = [], string $environment = 'staging', array $drop = []): array
{
    $names = json_decode(deployGateCall(['names'])->getOutput(), true);
    $base = array_merge($names['hard'], $names['gated'], ['automation|scheduler running']);
    $rows = [];
    foreach (array_merge($base, array_values(array_diff(array_keys($status), $base))) as $name) {
        if (in_array($name, $drop, true)) {
            continue;
        }
        [$area, $check] = explode('|', $name, 2);
        $rows[] = ['area' => $area, 'check' => $check, 'status' => $status[$name] ?? ($name === 'automation|scheduler running' ? 'WARN' : 'OK'), 'detail' => ''];
    }

    return ['environment' => $environment, 'fail' => 0, 'warn' => 1, 'checks' => $rows];
}

/** @param array<string, string> $options  `accept` = the signed tag message, `expected` = the staging expected-nonok list */
function deployGateVerdict(array|string $report, array $options = []): Process
{
    $dir = deployGateTempDir();
    $file = $dir.'/report.json';
    file_put_contents($file, is_string($report) ? $report : (string) json_encode($report));
    $args = ['verdict', '--report', $file];
    foreach (array_merge(['doctor-rc' => '0', 'env' => 'staging', 'production' => '0', 'sha' => str_repeat('ab', 20), 'override' => '', 'accept-file' => ''], $options) as $key => $value) {
        if ($key === 'accept' || $key === 'expected') {
            $path = $dir.'/'.($key === 'accept' ? 'tag.txt' : 'expected-nonok');
            file_put_contents($path, $value);
            array_push($args, $key === 'accept' ? '--accept-file' : '--expected-file', $path);

            continue;
        }
        array_push($args, '--'.$key, $value);
    }
    $process = deployGateCall($args);
    deployGateRemoveTree($dir);

    return $process;
}

// ── (a) the judge ──────────────────────────────────────────────────────────────────────────────────────────────────

it('passes a report whose HARD and GATED rows are OK and only prints the other rows', function () {
    $p = deployGateVerdict(deployGateReport());

    expect($p->getExitCode())->toBe(0)->and($p->getOutput())->toContain('REPORT automation|scheduler running (WARN)')->toContain('VERDICT pass');
});

it('stops on a HARD row that is not OK, and no override or tag line opens it', function () {
    $sha = str_repeat('ab', 20);
    $report = deployGateReport(['app|APP_DEBUG off' => 'WARN']); // outside production a blocking row is WARN, never FAIL: judged by name, not by status

    expect(deployGateVerdict($report)->getExitCode())->toBe(11)
        ->and(deployGateVerdict($report, ['override' => substr($sha, 0, 12).':staging rehearsal of a hard row'])->getExitCode())->toBe(11)
        ->and(deployGateVerdict(deployGateReport(['app|APP_DEBUG off' => 'FAIL'], 'production'), ['env' => 'production', 'production' => '1', 'accept' => "Accept-Gate: app|APP_DEBUG off — the owner says so, loudly\n"])->getExitCode())->toBe(11);
});

it('stops on a missing HARD row, on a doctor that crashed, on garbage, and on a report from another environment', function () {
    expect(deployGateVerdict(deployGateReport(drop: ['identity|roles in the database match the catalog']))->getExitCode())->toBe(11)
        ->and(deployGateVerdict(deployGateReport(), ['doctor-rc' => '255'])->getExitCode())->toBe(11)
        ->and(deployGateVerdict('Fatal error: Allowed memory size exhausted')->getExitCode())->toBe(11)
        ->and(deployGateVerdict(deployGateReport(environment: 'production'))->getExitCode())->toBe(11) // .env said staging, the app ran as production
        ->and(deployGateVerdict(deployGateReport(), ['doctor-rc' => '1'])->getExitCode())->toBe(0); // 1 = some FAIL row: judged by name
});

it('stops on a GATED row; outside production a SHA-bound override with a reason opens it, one bound to another SHA does not', function () {
    $sha = str_repeat('ab', 20);
    $report = deployGateReport(['storage|queue driver' => 'WARN']);

    expect(deployGateVerdict($report)->getExitCode())->toBe(10)
        ->and($ok = deployGateVerdict($report, ['override' => substr($sha, 0, 12).':staging override rehearsal']))->getExitCode()->toBe(0)
        ->and($ok->getOutput())->toContain('ACCEPTED storage|queue driver (WARN) by ALLOW_DOCTOR_FAIL')
        ->and(deployGateVerdict($report, ['override' => str_repeat('cd', 6).':staging override rehearsal'])->getExitCode())->toBe(10)
        ->and(deployGateVerdict($report, ['override' => substr($sha, 0, 12).':short'])->getExitCode())->toBe(10)
        ->and(deployGateVerdict(deployGateReport(drop: ['catalog|the metering gap ratchet is not growing']))->getExitCode())->toBe(10);
});

it('in production refuses the environment override and honours an Accept-Gate line of the signed tag for a GATED row', function () {
    $sha = str_repeat('ab', 20);
    $report = deployGateReport(['tls|CA bundle for outbound TLS' => 'FAIL'], 'production');
    $prod = ['env' => 'production', 'production' => '1'];

    $refused = deployGateVerdict($report, $prod + ['override' => substr($sha, 0, 12).':production override attempt']);
    expect($refused->getExitCode())->toBe(10)->and($refused->getOutput())->toContain('REFUSED ALLOW_DOCTOR_FAIL')
        ->and(deployGateVerdict($report, $prod + ['accept' => "v2026.09.27\n\nAccept-Gate: tls|CA bundle for outbound TLS — bundle path moves in the next release\n"])->getExitCode())->toBe(0)
        ->and(deployGateVerdict($report, $prod + ['accept' => "Accept-Gate: tls|CA bundle for outbound TLS — short\n"])->getExitCode())->toBe(10)
        ->and(deployGateVerdict(deployGateReport(['tls|CA bundle for outbound TLS' => 'WARN']), ['accept' => "Accept-Gate: tls|CA bundle for outbound TLS — a tag line outside production\n"])->getExitCode())->toBe(10); // tag lines count only in production
});

it('never reads an Accept-Gate line after a signature armor line: git leaves bytes after the signature unverified', function () {
    $report = deployGateReport(['tls|CA bundle for outbound TLS' => 'FAIL'], 'production');
    $appended = "v2026.09.27\n\nRelease-Record: .ai/releases/x.md\n-----BEGIN SSH SIGNATURE-----\nU1NIU0lH\n-----END SSH SIGNATURE-----\n"
        ."Accept-Gate: tls|CA bundle for outbound TLS — appended after the signature by someone without the key\n";

    $p = deployGateVerdict($report, ['env' => 'production', 'production' => '1', 'accept' => $appended]);

    expect($p->getExitCode())->toBe(10, $p->getOutput())->and($p->getOutput())->not->toContain('ACCEPTED');
});

// Pre-mortem 2026-09-27 (false-green HIGH): the gate judged 11 rows by name and waved every other FAIL through — card
// gateway live mode, transactional mailer, bank account, Sanctum domains — although Doctor.php promises that a FAIL
// fails a production deploy. On staging the same rows are WARN by design, so a staging GO taught operators to ignore them.

it('in production stops on any FAIL row outside the lists unless the signed tag accepts it; HARD never, WARN and the drained rows are reported', function () {
    $prod = ['env' => 'production', 'production' => '1'];
    $report = deployGateReport(['payments|card gateway live mode' => 'FAIL', 'mail|sender address set' => 'WARN', 'automation|queue worker alive' => 'FAIL'], 'production');

    $blocked = deployGateVerdict($report, $prod);
    expect($blocked->getExitCode())->toBe(12, $blocked->getOutput())
        ->and($blocked->getOutput())->toContain('ROW-FAIL payments|card gateway live mode (FAIL)')
        ->toContain('REPORT mail|sender address set (WARN)')
        ->toContain('REPORT automation|queue worker alive (FAIL)')->not->toContain('ROW-FAIL automation|queue worker alive');

    $accepted = deployGateVerdict($report, $prod + ['accept' => "v2026.09.28\n\nAccept-Gate: payments|card gateway live mode — the live merchant is switched on after the first paid test order\n"]);
    expect($accepted->getExitCode())->toBe(0, $accepted->getOutput())
        ->and($accepted->getOutput())->toContain('ACCEPTED payments|card gateway live mode (FAIL) by Accept-Gate in the signed tag');

    // production accepts a row only in the owner's signed tag: a list on the host does not open it, nor does the override
    expect(deployGateVerdict($report, $prod + ['expected' => "payments|card gateway live mode\n"])->getExitCode())->toBe(12)
        ->and(deployGateVerdict(deployGateReport(['app|APP_KEY set' => 'FAIL'], 'production'), $prod + ['accept' => "Accept-Gate: app|APP_KEY set — never, whatever the reason says\n"])->getExitCode())->toBe(11);
});

it('outside production stops on any non-OK row that is not on the expected list, which no override opens', function () {
    $sha = str_repeat('ab', 20);
    $report = deployGateReport(['mail|transactional mailer' => 'WARN', 'identity|four eyes in effect' => 'WARN', 'automation|queue worker alive' => 'WARN']);

    $none = deployGateVerdict($report); // no list at all: every such row stops the release
    expect($none->getExitCode())->toBe(12, $none->getOutput())->and($none->getOutput())->toContain('ROW-FAIL mail|transactional mailer (WARN)');

    $partial = deployGateVerdict($report, ['expected' => "# staging, O11: mail goes to the log\nmail|transactional mailer\n"]);
    expect($partial->getExitCode())->toBe(12)
        ->and($partial->getOutput())->toContain('EXPECTED mail|transactional mailer (WARN)')->toContain('ROW-FAIL identity|four eyes in effect (WARN)')
        ->and(deployGateVerdict($report, ['expected' => "mail|transactional mailer\n", 'override' => substr($sha, 0, 12).':staging override rehearsal'])->getExitCode())->toBe(12);

    $full = deployGateVerdict($report, ['expected' => "mail|transactional mailer\nidentity|four eyes in effect\npayments|bank statement import\n"]);
    expect($full->getExitCode())->toBe(0, $full->getOutput())
        ->and($full->getOutput())->toContain('CLEARED payments|bank statement import')->toContain('REPORT automation|queue worker alive (WARN)');
});

it('drafts the rows a release would need listed (staging) or accepted (production)', function () {
    $dir = deployGateTempDir();
    $staging = deployGateReport(['mail|transactional mailer' => 'WARN', 'automation|queue worker alive' => 'WARN', 'storage|queue driver' => 'WARN']);
    $production = deployGateReport(['payments|card gateway live mode' => 'FAIL', 'mail|sender address set' => 'WARN', 'storage|queue driver' => 'WARN'], 'production');
    file_put_contents($dir.'/s.json', (string) json_encode($staging));
    file_put_contents($dir.'/p.json', (string) json_encode($production));

    $s = deployGateCall(['nonok', '--report', $dir.'/s.json', '--production', '0']);
    $p = deployGateCall(['nonok', '--report', $dir.'/p.json', '--production', '1']);

    expect($s->getExitCode())->toBe(0)->and(trim($s->getOutput()))->toBe('mail|transactional mailer')
        ->and($p->getExitCode())->toBe(0)->and(trim($p->getOutput()))->toBe("storage|queue driver\npayments|card gateway live mode")
        ->and(deployGateCall(['nonok', '--report', $dir.'/missing.json'])->getExitCode())->toBe(2);
    deployGateRemoveTree($dir);
});

// Pre-mortem 2026-09-27 (live-harm MEDIUM): S3 only printed values. The assertion reads app.env the way phpdotenv does
// and never prints a value — the file holds secrets, and a mismatch of a secret key must not show it.
it('asserts the staging environment against a spec without printing a value, and refuses an unfilled or unreadable spec', function () {
    $dir = deployGateTempDir();
    file_put_contents($dir.'/app.env', "APP_ENV=staging\nCOMGATE_TEST=true\nCOMGATE_RECURRING=true   # stored cards\nONHOST_BANK_FIO_TOKEN=\nPOWERDNS_HIDDEN01_URL=                     # the hidden primary\nREDIS_PREFIX=onhost-staging-\nCOMGATE_SECRET=sEcReT-live\nONHOST_ACME_DIRECTORY=https://acme-staging-v02.api.letsencrypt.org/directory\n");
    $assert = function (string $spec) use ($dir): Process {
        file_put_contents($dir.'/spec', $spec);

        return deployGateCall(['env-assert', '--file', $dir.'/app.env', '--spec', $dir.'/spec']);
    };

    $ok = $assert("# staging\nAPP_ENV=staging\nCOMGATE_TEST=true\nONHOST_BANK_FIO_TOKEN=\nPOWERDNS_HIDDEN01_URL=\nREDIS_PREFIX?\nREDIS_PREFIX!=onhost-database-\nONHOST_ACME_DIRECTORY~=acme-staging\n");
    expect($ok->getExitCode())->toBe(0, $ok->getOutput())->and($ok->getOutput())->toContain('OK REDIS_PREFIX')->toContain('OK POWERDNS_HIDDEN01_URL');

    $bad = $assert("COMGATE_RECURRING=false\nCOMGATE_SECRET=\nCACHE_PREFIX?\n");
    expect($bad->getExitCode())->toBe(13)
        ->and($bad->getOutput())->toContain('MISMATCH COMGATE_RECURRING')->toContain('MISMATCH COMGATE_SECRET')->toContain('MISMATCH CACHE_PREFIX')
        ->and($bad->getOutput().$bad->getErrorOutput())->not->toContain('sEcReT-live');

    expect($assert("COMGATE_MERCHANT=<the Comgate test merchant id>\n")->getExitCode())->toBe(13)
        ->and($assert("COMGATE_MERCHANT=<the Comgate test merchant id>\n")->getOutput())->toContain('UNFILLED COMGATE_MERCHANT')
        ->and($assert("this is not a rule\n")->getExitCode())->toBe(2)
        ->and(deployGateCall(['env-assert', '--file', $dir.'/missing.env', '--spec', $dir.'/spec'])->getExitCode())->toBe(2);
    deployGateRemoveTree($dir);
});

// Review of the post-round-3 commits (security MEDIUM): parseEnv reads two different definitions as '' — right for the
// APP_ENV caller (fail closed), but envAssert then passed `KEY=` and `KEY!=` while phpdotenv loads the last value; and a
// `${VAR}` value resolves to something the assertion never saw. Both are refused now, and neither prints a value.
it('refuses a key defined twice with different values and a value that interpolates, without printing either', function () {
    $dir = deployGateTempDir();
    $assert = function (string $env, string $spec) use ($dir): Process {
        file_put_contents($dir.'/app.env', $env);
        file_put_contents($dir.'/spec', $spec);

        return deployGateCall(['env-assert', '--file', $dir.'/app.env', '--spec', $dir.'/spec']);
    };

    $twice = $assert("ONHOST_EGRESS_ALLOW_CIDRS=\nONHOST_EGRESS_ALLOW_CIDRS=0.0.0.0/0\nDB_DATABASE=onhost_staging\nDB_DATABASE=oNhOsT_pRoD\n", "ONHOST_EGRESS_ALLOW_CIDRS=\nDB_DATABASE!=onhost_production\n");
    expect($twice->getExitCode())->toBe(13, $twice->getOutput())
        ->and($twice->getOutput())->toContain('MISMATCH ONHOST_EGRESS_ALLOW_CIDRS: defined twice')->toContain('MISMATCH DB_DATABASE: defined twice')
        ->and($twice->getOutput().$twice->getErrorOutput())->not->toContain('0.0.0.0/0')->not->toContain('oNhOsT_pRoD');

    $same = $assert("APP_ENV=staging\nAPP_ENV=\"staging\"   # repeated, same value\n", "APP_ENV=staging\n");
    expect($same->getExitCode())->toBe(0, $same->getOutput())->and($same->getOutput())->toContain('OK APP_ENV');

    $interpolated = $assert("PROD_DB=oNhOsT_pRoD\nDB_DATABASE=\${PROD_DB}\nREDIS_PREFIX=\"onhost-\${APP_ENV}-\"\n", "DB_DATABASE!=onhost_production\nREDIS_PREFIX?\n");
    expect($interpolated->getExitCode())->toBe(13, $interpolated->getOutput())
        ->and($interpolated->getOutput())->toContain('MISMATCH DB_DATABASE: interpolates')->toContain('MISMATCH REDIS_PREFIX: interpolates')
        ->and($interpolated->getOutput().$interpolated->getErrorOutput())->not->toContain('oNhOsT_pRoD');

    // the APP_ENV reader keeps its fail-closed '' for an ambiguous key
    file_put_contents($dir.'/.env', "APP_ENV=staging\nAPP_ENV=production\n");
    expect(rtrim(deployGateCall(['parse-env', '--file', $dir.'/.env'])->getOutput(), "\n"))->toBe('');
    deployGateRemoveTree($dir);
});

// Review round 0 (security MEDIUM): the S3 spec named single keys, while EnvSecretStore reads a whole prefix per
// `env://X` reference (every X_* key) — a live key under a name nobody listed passed. A family line `X_*=` requires
// every X_ key the spec does not name on a line of its own to be empty or absent.
it('asserts a key family empty except the keys the spec names on their own lines, without printing a value', function () {
    $dir = deployGateTempDir();
    $assert = function (string $env, string $spec) use ($dir): Process {
        file_put_contents($dir.'/app.env', $env);
        file_put_contents($dir.'/spec', $spec);

        return deployGateCall(['env-assert', '--file', $dir.'/app.env', '--spec', $dir.'/spec']);
    };

    $clean = $assert("GOPAY_RECURRING=false\nAI_ANTHROPIC_MODEL=\nAPP_ENV=staging\n", "GOPAY_RECURRING=false\nGOPAY_*=\nAI_ANTHROPIC_*=\nPROXMOX_*=\n");
    expect($clean->getExitCode())->toBe(0, $clean->getOutput())
        ->and($clean->getOutput())->toContain('OK GOPAY_RECURRING')->toContain('OK GOPAY_*')->toContain('OK AI_ANTHROPIC_*')->toContain('OK PROXMOX_*');

    $live = $assert("GOPAY_RECURRING=false\nGOPAY_GOID=8123456789\nAI_ANTHROPIC_API_KEY=sk-ant-lIvE\nPROXMOX_CZ1_TOKEN_SECRET=pRoXmOx-lIvE\n", "GOPAY_RECURRING=false\nGOPAY_*=\nAI_ANTHROPIC_*=\nPROXMOX_*=\n");
    expect($live->getExitCode())->toBe(13, $live->getOutput())
        ->and($live->getOutput())->toContain('OK GOPAY_RECURRING')->toContain('MISMATCH GOPAY_GOID')
        ->toContain('MISMATCH AI_ANTHROPIC_API_KEY')->toContain('MISMATCH PROXMOX_CZ1_TOKEN_SECRET')
        ->and($live->getOutput().$live->getErrorOutput())->not->toContain('8123456789')->not->toContain('sk-ant-lIvE')->not->toContain('pRoXmOx-lIvE');

    expect($assert("X=1\n", "GOPAY_*=something\n")->getExitCode())->toBe(2)   // a family can only be required empty
        ->and($assert("X=1\n", "GOPAY_*?\n")->getExitCode())->toBe(2);
    deployGateRemoveTree($dir);
});

// Review round 0 (security MEDIUM): the S3 spec of the runbook missed the outward keys — the console relay's key and
// URL, the AWS key pair, the AI keys, the `*_SECRET_REF` families, the panels' env:// families. The spec block of
// staging-launch.md is run as written (placeholders filled) against a staging environment carrying each of them.
it('holds the runbook\'s staging environment spec to every outward credential', function () {
    $doc = (string) file_get_contents(base_path('docs/runbooks/staging-launch.md'));
    expect(preg_match("/cat > \\\$STATE\\/expected-env <<'EOF'\\n(.*?)\\nEOF\\n/s", $doc, $m))->toBe(1);
    $dir = deployGateTempDir();
    file_put_contents($dir.'/spec', (string) preg_replace('/<[^>]*>/', 'filled-by-test', $m[1]));
    $base = "APP_ENV=staging\nAPP_DEBUG=false\nAPP_URL=https://staging.onhost.cz\nDB_CONNECTION=pgsql\nQUEUE_CONNECTION=redis\nCACHE_STORE=redis\n"
        ."SESSION_DRIVER=redis\nONHOST_SECRETS_DRIVER=db\nREDIS_PREFIX=onhost-staging-\nCACHE_PREFIX=onhost-staging-cache-\nDB_DATABASE=onhost_staging\n"
        ."MAIL_MAILER=log\nPAYMENT_GATEWAY=comgate\nCOMGATE_TEST=true\nCOMGATE_RECURRING=false\nCOMGATE_MERCHANT=filled-by-test\nCOMGATE_SECRET=test-merchant\n"
        ."GOPAY_RECURRING=false\nSTRIPE_RECURRING=false\nWEDOS_TEST_MODE=true\nONHOST_ACME_DIRECTORY=https://acme-staging-v02.api.letsencrypt.org/directory\n"
        ."ONHOST_VIES_ENABLED=false\nONHOST_FOUR_EYES=false\nONHOST_PLATFORM_BACKUP_DISK=filled-by-test\nONHOST_EGRESS_DENY_CIDRS=0.0.0.0/0,::/0\n";
    $assert = function (string $extra) use ($dir, $base): Process {
        file_put_contents($dir.'/app.env', $base.$extra);

        return deployGateCall(['env-assert', '--file', $dir.'/app.env', '--spec', $dir.'/spec']);
    };
    $ok = $assert('');
    expect($ok->getExitCode())->toBe(0, $ok->getOutput());

    foreach (['ONHOST_CONSOLE_RELAY_URL', 'ONHOST_CONSOLE_RELAY_KEY', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AI_ANTHROPIC_API_KEY',
        'AI_OPENAI_API_KEY', 'AI_ANTHROPIC_SECRET_REF', 'AI_OPENAI_SECRET_REF', 'GOPAY_SECRET_REF', 'STRIPE_SECRET_REF', 'PEPPOL_SECRET_REF',
        'OIDC_CLIENT_SECRET_REF', 'ONHOST_DISCORD_BOT_SECRET_REF', 'DISCORD_BOT_TOKEN', 'ONHOST_ONCALL_SECRET_REF', 'ONHOST_GAME_OPERATOR_VARIABLES_REF',
        'ONHOST_CDN_CLOUDFLARE_SECRET_REF', 'CLOUDFLARE_API_TOKEN', 'ONHOST_NODE_BOOTSTRAP_SSH_KEY', 'OPENBAO_TOKEN', 'SENTRY_DSN', 'OTEL_EXPORTER_OTLP_HEADERS',
        'PROXMOX_CZ1_TOKEN_SECRET', 'ISPCONFIG_SHARED01_PASSWORD', 'AAPANEL_MANAGED01_API_KEY', 'PTERODACTYL_GAMES01_API_KEY', 'POWERDNS_HIDDEN01_API_KEY',
        'PBS_CZ1_TOKEN_SECRET', 'RKE2_CZ1_TOKEN', 'WEDOS_MAIN_PASSWORD', 'SUBREG_MAIN_PASSWORD'] as $key) {
        $r = $assert($key."=lIvE-vAlUe-0123\n");
        expect($r->getExitCode())->toBe(13, $key.': '.$r->getOutput())
            ->and($r->getOutput())->toContain('MISMATCH '.$key)
            ->and($r->getOutput().$r->getErrorOutput())->not->toContain('lIvE-vAlUe-0123');
    }
    deployGateRemoveTree($dir);
});

it('reads APP_ENV the way phpdotenv does and leaves anything ambiguous empty (the deployer then treats it as production)', function () {
    $dir = deployGateTempDir();
    $read = function (string $content) use ($dir): string {
        file_put_contents($dir.'/.env', $content);

        return rtrim(deployGateCall(['parse-env', '--file', $dir.'/.env'])->getOutput(), "\n");
    };

    expect($read("APP_ENV=production   # c\n"))->toBe('production')
        ->and($read("APP_ENV=\"production\"\n"))->toBe('production')
        ->and($read("APP_ENV=staging # staging | production\n"))->toBe('staging')
        ->and($read("APP_DEBUG=false\n"))->toBe('')
        ->and($read("APP_ENV=staging\nAPP_ENV=production\n"))->toBe('')
        ->and($read("export APP_ENV='local'\n"))->toBe('local')
        // phpdotenv ends an unquoted value at the first `#`, also right after `=` (VERIFIED against vlucas/phpdotenv 5):
        // `.env.example` writes `KEY=      # comment` for an empty key, which used to read as the comment itself
        ->and($read("APP_ENV=staging#no space before the comment\n"))->toBe('staging')
        ->and($read("APP_ENV=                     # staging | production\n"))->toBe('');
    expect(deployGateCall(['parse-env', '--file', $dir.'/missing'])->getExitCode())->toBe(2);
    deployGateRemoveTree($dir);
});

it('takes the backup set only from a Set line written in this run', function () {
    $dir = deployGateTempDir();
    $set = function (string $output, string $since) use ($dir): Process {
        file_put_contents($dir.'/backup.out', $output);

        return deployGateCall(['backup-set', '--output', $dir.'/backup.out', '--since', $since]);
    };

    $good = $set("+------+\n\e[32mSet platform-backups/20260927-101500 on disk local (pgsql); pruned 0\e[39m\n", '20260927-101400');
    expect($good->getExitCode())->toBe(0)->and(trim($good->getOutput()))->toBe('platform-backups/20260927-101500')
        ->and($set("platform.backup is switched off\n", '20260927-101400')->getExitCode())->toBe(3)
        ->and($set("Set platform-backups/20260926-020000 on disk local (pgsql); pruned 0\n", '20260927-101400')->getExitCode())->toBe(4);
    deployGateRemoveTree($dir);
});

it('builds a maintenance bypass cookie that Laravel accepts, from the secret in the down file, without printing it', function () {
    $dir = deployGateTempDir();
    file_put_contents($dir.'/down', (string) json_encode(['secret' => 'sEcReT-from-down', 'retry' => 60]));

    $p = deployGateCall(['cookie', '--down-file', $dir.'/down', '--out', $dir.'/jar', '--ttl', '600']);
    $config = trim((string) file_get_contents($dir.'/jar'));
    $value = preg_match('/^header = "Cookie: laravel_maintenance=([^"]+)"$/', $config, $m) === 1 ? rawurldecode($m[1]) : '';

    expect($p->getExitCode())->toBe(0)->and($p->getOutput().$p->getErrorOutput())->not->toContain('sEcReT')
        ->and($config)->not->toContain('sEcReT')->and($value)->not->toBe('')
        ->and(MaintenanceModeBypassCookie::isValid($value, 'sEcReT-from-down'))->toBeTrue()
        ->and(MaintenanceModeBypassCookie::isValid($value, 'another'))->toBeFalse();
    file_put_contents($dir.'/down', (string) json_encode(['secret' => null]));
    expect(deployGateCall(['cookie', '--down-file', $dir.'/down', '--out', $dir.'/jar2'])->getExitCode())->toBe(2);
    deployGateRemoveTree($dir);
});

it('prints a recovery command that works under the environment\'s REF rules', function () {
    $dir = deployGateTempDir();
    $sha = str_repeat('0f', 20);
    file_put_contents($dir.'/last-good.json', (string) json_encode(['sha' => $sha, 'ref' => $sha, 'tag' => '', 'at' => 'x', 'operator' => 'op']));
    expect(deployGateCall(['hint', '--file', $dir.'/last-good.json', '--production', '0'])->getOutput())->toContain("REF={$sha} EXPECTED_SHA={$sha}")
        ->and(deployGateCall(['hint', '--file', $dir.'/last-good.json', '--production', '1'])->getExitCode())->toBe(1);
    file_put_contents($dir.'/last-good.json', (string) json_encode(['sha' => $sha, 'ref' => 'v2026.09.27', 'tag' => 'v2026.09.27', 'at' => 'x', 'operator' => 'op']));
    expect(deployGateCall(['hint', '--file', $dir.'/last-good.json', '--production', '1'])->getOutput())->toContain("REF=v2026.09.27 EXPECTED_SHA={$sha}");
    deployGateRemoveTree($dir);
});

// ── (b) the names it gates on exist ────────────────────────────────────────────────────────────────────────────────

it('gates only on doctor rows that exist: every HARD, GATED and drained name appears in a real onhost:doctor --json run', function () {
    Http::preventStrayRequests();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    $present = array_map(fn (array $row) => $row['area'].'|'.$row['check'], $report['checks']);
    $names = json_decode(deployGateCall(['names'])->getOutput(), true);

    expect($names['drained'] ?? null)->toBeArray()->not->toBeEmpty()
        ->and(array_values(array_diff(array_merge($names['hard'], $names['gated'], $names['drained'] ?? []), $present)))->toBe([]);
});

// ── (c) the text deploy.sh parses ──────────────────────────────────────────────────────────────────────────────────

it('pins the backup output deploy.sh parses: a Set line, OK <set> from verify, and no Set line when the rule is off', function () {
    Http::preventStrayRequests();
    Storage::fake('deploy-gate-backups');
    config()->set('onhost.platform_backup.disk', 'deploy-gate-backups');
    $files = storage_path('framework/testing/deploy-gate-'.uniqid());
    File::ensureDirectoryExists($files.'/x');
    File::put($files.'/x/a.txt', 'a');
    config()->set('onhost.platform_backup.files_root', $files);
    $dir = deployGateTempDir();
    $since = now()->utc()->subMinute()->format('Ymd-His');

    Artisan::call('onhost:platform:backup', ['--no-ansi' => true]);
    file_put_contents($dir.'/backup.out', Artisan::output());
    $parsed = deployGateCall(['backup-set', '--output', $dir.'/backup.out', '--since', $since]);
    $set = trim($parsed->getOutput());
    expect($parsed->getExitCode())->toBe(0)->and($set)->toMatch('#^platform-backups/\d{8}-\d{6}$#');

    Artisan::call('onhost:platform:backup:verify', ['set' => $set, '--no-ansi' => true]);
    expect(Artisan::output())->toMatch('#^OK '.preg_quote($set, '#').'( |$)#m');

    app(AutomationLedger::class)->setEnabled('platform.backup', false, 'test');
    Artisan::call('onhost:platform:backup', ['--no-ansi' => true]);
    file_put_contents($dir.'/backup.out', Artisan::output());
    expect(deployGateCall(['backup-set', '--output', $dir.'/backup.out', '--since', $since])->getExitCode())->toBe(3);
    deployGateRemoveTree($dir);
    File::deleteDirectory($files);
});

// ── (d) the script, against stubs ──────────────────────────────────────────────────────────────────────────────────

function deployGateBash(): ?string
{
    $configured = getenv('ONHOST_TEST_BASH');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }
    if (PHP_OS_FAMILY === 'Windows') { // never System32\bash.exe: that is WSL, with other paths and another PHP
        return is_file('C:\\Program Files\\Git\\bin\\bash.exe') ? 'C:\\Program Files\\Git\\bin\\bash.exe' : null;
    }
    foreach (['/usr/bin/bash', '/bin/bash'] as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function deployGatePosix(string $path): string
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return $path;
    }
    $path = str_replace('\\', '/', $path);

    return preg_match('#^([A-Za-z]):/(.*)$#', $path, $m) === 1 ? '/'.strtolower($m[1]).'/'.$m[2] : $path;
}

/** @return array{dir:string, posix:string, SHA_A:string, SHA_B:string, APP:string, SEED:string, ENV:string, UID:string, USER:string} */
function deployGateSandbox(): array
{
    $bash = deployGateBash();
    if ($bash === null) {
        if (getenv('CI')) {
            throw new RuntimeException('bash is required for the deploy script tests in CI');
        }
        test()->markTestSkipped('no bash (Git Bash on Windows) — the deploy script cases run in CI');
    }
    $dir = deployGateTempDir();
    $process = new Process([$bash, deployGatePosix(base_path('infra/aapanel/ci/deploy-sandbox.sh')), deployGatePosix($dir)], null, [
        'REPO_ROOT' => deployGatePosix(base_path()), 'REAL_PHP' => deployGatePosix(PHP_BINARY),
    ]);
    $process->setTimeout(120);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('sandbox: '.$process->getErrorOutput().$process->getOutput());
    }
    $box = ['dir' => $dir, 'posix' => deployGatePosix($dir)];
    foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $line) {
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $box[$key] = $value;
    }
    file_put_contents($dir.'/bin/doctor.json', (string) json_encode(deployGateReport()));

    return $box;
}

/**
 * Runs the installed deployer (or ONHOST_DEPLOY_SCRIPT) in the sandbox.
 *
 * @param  array<string, string|false>  $env
 * @param  array<string, string>  $stub  knobs for the stubs (written to bin/stub.env; artisan runs under env -i)
 * @param  string|false|null  $expectedUnits  the state dir's expected-units; null = the units STUB_UNITS says are running, false = no file
 * @return array{rc:int, out:string, log:string, stub:string, head:string}
 */
function deployGateDeploy(array $box, array $env, array $stub = [], string|false|null $expectedUnits = null, ?string $input = null): array
{
    $process = deployGateDeployProcess($box, $env, $stub, $expectedUnits, $input);
    $process->run();

    return deployGateDeployResult($box, $process);
}

/** Writes $content to $path unless it already holds exactly that (a deployer running concurrently reads these files). */
function deployGateWriteIfChanged(string $path, string $content): void
{
    if (! is_file($path) || file_get_contents($path) !== $content) {
        file_put_contents($path, $content);
    }
}

/**
 * The deployer process deployGateDeploy() runs, prepared but not started ($input = what it gets on stdin).
 *
 * @param  array<string, string|false>  $env
 * @param  array<string, string>  $stub
 */
function deployGateDeployProcess(array $box, array $env, array $stub = [], string|false|null $expectedUnits = null, ?string $input = null): Process
{
    $unitsFile = $box['dir'].'/state/expected-units';
    if ($expectedUnits === false) {
        @unlink($unitsFile);
    } else {
        deployGateWriteIfChanged($unitsFile, $expectedUnits ?? implode("\n", preg_split('/\s+/', trim($stub['STUB_UNITS'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))."\n");
    }
    $base = "STUB_LOG='{$box['posix']}/stub.log'\nREAL_PHP='".deployGatePosix(PHP_BINARY)."'\n";
    foreach ($stub as $key => $value) {
        $base .= $key."='".str_replace("'", "'\\''", $value)."'\n";
    }
    deployGateWriteIfChanged($box['dir'].'/bin/stub.env', $base);
    $script = getenv('ONHOST_DEPLOY_SCRIPT') ?: $box['posix'].'/sbin/onhost-deploy';
    $process = new Process([(string) deployGateBash(), '-c', 'cd "$APP_DIR" && PATH="$STUB_BIN:$PATH" exec bash "$DEPLOYER"'], null, array_merge([
        'STUB_BIN' => $box['posix'].'/bin', 'DEPLOYER' => deployGatePosix((string) $script),
        'SITE' => 'staging.test', 'APP_DIR' => $box['APP'], 'PHP' => $box['posix'].'/bin/php', 'COMPOSER' => '/nonexistent/composer',
        'ENV_FILE' => $box['ENV'], 'UNIT_SETTLE' => '0',
        'RUN_USER' => $box['USER'], 'DEPLOY_STATE_DIR' => $box['posix'].'/state', 'DEPLOY_LIB_DIR' => $box['posix'].'/lib',
        'DEPLOY_WORK_DIR' => $box['posix'].'/work/staging.test', 'PHP_FPM_RELOAD' => $box['posix'].'/bin/fpm-reload', 'DEPLOY_HTTP_BASE' => 'http://127.0.0.1:1', 'DEPLOY_OWNER_UID' => $box['UID'],
        'DEPLOY_HOME' => $box['posix'], 'DEPLOY_SAFE_PATH' => $box['posix'].'/bin:/usr/bin:/bin', 'DEPLOY_OPERATOR' => 'test-operator',
        'BRANCH' => false, 'ALLOW_DOCTOR_FAIL' => false, 'SKIP_BACKUP' => false, 'REF' => false, 'EXPECTED_SHA' => false,
    ], $env));
    $process->setTimeout(120);
    if ($input !== null) {
        $process->setInput($input);
    }

    return $process;
}

/** @return array{rc:int, out:string, log:string, stub:string, head:string} */
function deployGateDeployResult(array $box, Process $process): array
{
    return [
        'rc' => (int) $process->getExitCode(), 'out' => $process->getOutput().$process->getErrorOutput(),
        'log' => (string) @file_get_contents($box['dir'].'/state/deploy.log'), 'stub' => (string) @file_get_contents($box['dir'].'/stub.log'),
        'head' => trim((string) (new Process(['git', '-C', $box['dir'].'/app', 'rev-parse', 'HEAD']))->mustRun()->getOutput()),
    ];
}

/** The line number of the first stub-log line containing $needle (PHP_INT_MAX when absent). */
function deployGateAt(string $stubLog, string $needle): int
{
    foreach (explode("\n", $stubLog) as $i => $line) {
        if (str_contains($line, $needle)) {
            return $i;
        }
    }

    return PHP_INT_MAX;
}

function deployGateTo(array $box, string $sha): array
{
    return ['REF' => $sha, 'EXPECTED_SHA' => $sha];
}

afterEach(function () {
    if (isset($this->deployBox)) {
        deployGateRemoveTree($this->deployBox['dir']);
    }
});

it('releases in order: drain, down, backup and verify, switch, migrate, gate, start, up — and records the release', function () {
    $box = $this->deployBox = deployGateSandbox();
    // a framework cache the old release left in bootstrap/cache (stale after an app.env edit)
    file_put_contents($box['dir'].'/app/bootstrap/cache/config.php', "<?php // the old release's cache\n");
    // VERSION (gitignored, in a directory www owns) planted as a second name of a file root must not write: a hard
    // link here (every OS), a symlink on Linux — root used to write through it with `>` (review round 3)
    file_put_contents($box['dir'].'/victim.txt', "not the release\n");
    PHP_OS_FAMILY === 'Windows' ? link($box['dir'].'/victim.txt', $box['dir'].'/app/VERSION') : symlink($box['dir'].'/victim.txt', $box['dir'].'/app/VERSION');

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']), ['STUB_UNITS' => 'onhost-queue@default.service onhost-scheduler.service']);
    $s = $r['stub'];
    $artisan = array_values(array_filter(explode("\n", $s), fn (string $line) => str_starts_with($line, 'php ') && str_contains($line, '/artisan ')));

    // every artisan call reads the framework caches of this run's own directory (a config fresh from app.env, not what
    // the old release cached), and the build publishes the caches it made for PHP-FPM
    expect($artisan)->not->toBeEmpty();
    foreach ($artisan as $line) {
        expect($line)->toMatch('#\[cache=\S*/work/staging\.test/run-[^ \]]+/config\.php\]#');
    }
    expect((string) file_get_contents($box['dir'].'/app/bootstrap/cache/config.php'))->toContain('built by the release build');

    expect($r['rc'])->toBe(0, $r['out'])->and($r['head'])->toBe($box['SHA_B'])
        ->and(deployGateAt($s, 'systemctl stop'))->toBeLessThan(deployGateAt($s, 'artisan down'))
        ->and(deployGateAt($s, 'artisan down'))->toBeLessThan(deployGateAt($s, 'onhost:platform:backup --no-ansi'))
        ->and(deployGateAt($s, 'onhost:platform:backup:verify platform-backups/'))->toBeLessThan(deployGateAt($s, 'artisan migrate'))
        ->and(deployGateAt($s, 'artisan migrate'))->toBeLessThan(deployGateAt($s, 'onhost:doctor --json'))
        ->and(deployGateAt($s, 'onhost:doctor --json'))->toBeLessThan(deployGateAt($s, 'systemctl start onhost-queue@default.service'))
        ->and(deployGateAt($s, 'systemctl start onhost-scheduler.service'))->toBeLessThan(deployGateAt($s, 'artisan up'))
        ->and($s)->not->toContain('queue:restart')
        ->and(trim((string) file_get_contents($box['dir'].'/app/VERSION')))->toBe($box['SHA_B'].' '.$box['SHA_B'])
        ->and(is_link($box['dir'].'/app/VERSION'))->toBeFalse()
        ->and((string) file_get_contents($box['dir'].'/victim.txt'))->toBe("not the release\n")
        ->and(json_decode((string) file_get_contents($box['dir'].'/state/last-good.json'), true)['sha'])->toBe($box['SHA_B'])
        ->and(is_file($box['dir'].'/app/storage/framework/down'))->toBeFalse()
        ->and($r['log'])->toContain('operator=test-operator')->toContain('rc=0')->toContain('set=platform-backups/');
    $secrets = array_filter(explode("\n", (string) @file_get_contents($box['dir'].'/bin/secrets.seen')));
    expect($secrets)->not->toBeEmpty();
    foreach ($secrets as $secret) { // the bypass secret never reaches an argv or the deploy log
        expect($r['stub'])->not->toContain($secret)->and($r['log'])->not->toContain($secret)->and($r['out'])->not->toContain($secret);
    }
});

it('stops on a HARD doctor row before anything starts again, keeps the site down without the bypass, and names the last good release', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/state/last-good.json', (string) json_encode(['sha' => $box['SHA_A'], 'ref' => $box['SHA_A'], 'tag' => '', 'at' => 'x', 'operator' => 'op']));
    file_put_contents($box['dir'].'/bin/doctor.json', (string) json_encode(deployGateReport(['app|APP_DEBUG off' => 'WARN'])));

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']) + ['ALLOW_DOCTOR_FAIL' => substr($box['SHA_B'], 0, 12).':even with an override'], ['STUB_UNITS' => 'onhost-queue@default.service']);

    expect($r['rc'])->toBe(5, $r['out'])
        ->and($r['stub'])->not->toContain('systemctl start')->not->toContain('artisan up')
        ->and($r['out'])->toContain("REF={$box['SHA_A']} EXPECTED_SHA={$box['SHA_A']}")
        ->and(json_decode((string) file_get_contents($box['dir'].'/app/storage/framework/down'), true)['secret'])->toBeNull() // re-issued without a secret
        ->and(trim((string) file_get_contents($box['dir'].'/state/drained-units')))->toBe('onhost-queue@default.service')
        ->and($r['log'])->toContain('stage=gate rc=5');
});

it('stops on a GATED row unless a SHA-bound, logged override is given; one bound to another SHA is refused before anything changes', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/bin/doctor.json', (string) json_encode(deployGateReport(['storage|queue driver' => 'WARN'])));

    $wrong = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']) + ['ALLOW_DOCTOR_FAIL' => substr($box['SHA_A'], 0, 12).':bound to the other sha']);
    expect($wrong['rc'])->toBe(2)->and($wrong['head'])->toBe($box['SHA_A'])->and($wrong['stub'])->not->toContain('artisan down');

    $plain = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']));
    expect($plain['rc'])->toBe(5, $plain['out'])->and($plain['out'])->toContain('GATED-FAIL storage|queue driver');

    $allowed = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']) + ['ALLOW_DOCTOR_FAIL' => substr($box['SHA_B'], 0, 12).':staging override rehearsal']);
    expect($allowed['rc'])->toBe(0, $allowed['out'])
        ->and($allowed['log'])->toContain('override="staging override rehearsal"')->toContain('accepted="storage|queue driver (WARN) by ALLOW_DOCTOR_FAIL;"');
});

it('switches nothing when the backup did not run, starts the drained units and lifts the maintenance it set', function () {
    $box = $this->deployBox = deployGateSandbox();

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']), ['STUB_BACKUP_OUT' => 'platform.backup is switched off', 'STUB_UNITS' => 'onhost-scheduler.service']);

    expect($r['rc'])->toBe(3, $r['out'])->and($r['head'])->toBe($box['SHA_A'])
        ->and($r['stub'])->not->toContain('artisan migrate')->toContain('artisan up')->toContain('systemctl start onhost-scheduler.service')
        ->and(is_file($box['dir'].'/app/storage/framework/down'))->toBeFalse()
        ->and(is_file($box['dir'].'/state/drained-units'))->toBeFalse();
});

it('keeps the site down after a failed build and prints the recovery hint', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/state/last-good.json', (string) json_encode(['sha' => $box['SHA_A'], 'ref' => $box['SHA_A'], 'tag' => '', 'at' => 'x', 'operator' => 'op']));

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']), ['STUB_COMPOSER_EXIT' => '1']);

    expect($r['rc'])->toBe(4, $r['out'])->and(is_file($box['dir'].'/app/storage/framework/down'))->toBeTrue()
        ->and($r['stub'])->not->toContain('artisan up')->and($r['out'])->toContain("REF={$box['SHA_A']} EXPECTED_SHA={$box['SHA_A']}");
});

it('refuses a dirty tree, a skipped backup over migrations, a closed loopback and a target that carries another deployer — before going down', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);

    file_put_contents($box['dir'].'/app/artisan', "<?php // changed by hand on the server\n");
    $dirty = deployGateDeploy($box, $to);
    (new Process(['git', '-C', $box['dir'].'/app', 'checkout', '--', 'artisan']))->mustRun();
    $skip = deployGateDeploy($box, $to + ['SKIP_BACKUP' => substr($box['SHA_B'], 0, 12).':no time for a backup']);
    $loopback = deployGateDeploy($box, $to, ['STUB_CURL_CODE' => '401']);
    $missing = deployGateDeploy($box, ['REF' => $box['SHA_B']]);
    $branch = deployGateDeploy($box, $to + ['BRANCH' => 'development']);

    foreach ([$dirty, $skip, $loopback, $missing, $branch] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($dirty['out'])->toContain('not clean')->and($skip['out'])->toContain('database/migrations')->and($loopback['out'])->toContain('loopback');

    // a target that descends from the installed deployer but carries a different deploy.sh must be installed first
    $seed = $box['dir'].'/seed';
    file_put_contents($seed.'/infra/aapanel/deploy.sh', "\n# changed\n", FILE_APPEND);
    (new Process(['git', '-C', $seed, '-c', 'user.name=t', '-c', 'user.email=t@example.invalid', 'commit', '-q', '-am', 'C: a new deployer']))->mustRun();
    $shaC = trim((new Process(['git', '-C', $seed, 'rev-parse', 'HEAD']))->mustRun()->getOutput());
    (new Process(['git', '-C', $seed, 'push', '-q', $box['dir'].'/origin.git', 'HEAD:refs/heads/next']))->mustRun();
    $newer = deployGateDeploy($box, deployGateTo($box, $shaC));
    expect($newer['rc'])->toBe(2, $newer['out'])->and($newer['out'])->toContain('install-deployer.sh')->and($newer['stub'])->not->toContain('artisan down');
});

it('leaves a site down that an operator took down before the deploy', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/app/storage/framework/down', (string) json_encode(['secret' => null, 'retry' => 60]));

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']));

    expect($r['rc'])->toBe(0, $r['out'])->and($r['stub'])->not->toContain('artisan up')
        ->and($r['out'])->toContain('left down')
        ->and(json_decode((string) file_get_contents($box['dir'].'/app/storage/framework/down'), true)['secret'])->toBeNull();
});

it('in production accepts only an annotated tag signed by a key in allowed_signers', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/etc/app.env', "APP_ENV=production\nAPP_URL=https://staging.test\n"); // the site's .env is the same file
    $b = $box['SHA_B'];

    $unsigned = deployGateDeploy($box, ['REF' => 'vtest', 'EXPECTED_SHA' => $b]);         // no allowed_signers on the host
    $light = deployGateDeploy($box, ['REF' => 'vlight', 'EXPECTED_SHA' => $b]);           // lightweight
    $bySha = deployGateDeploy($box, deployGateTo($box, $b));                                 // not a tag
    $override = deployGateDeploy($box, ['REF' => 'vtest', 'EXPECTED_SHA' => $b, 'ALLOW_DOCTOR_FAIL' => substr($b, 0, 12).':production override attempt']);

    expect($unsigned['out'])->toContain('allowed_signers')->and($light['out'])->toContain('lightweight');
    foreach ([$unsigned, $light, $bySha, $override] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->and($r['head'])->toBe($box['SHA_A']);
    }
});

/** Runs infra/aapanel/install-deployer.sh (or ONHOST_INSTALL_DEPLOYER_SCRIPT) against the sandbox. */
function deployGateInstallDeployer(array $box, array $env): Process
{
    $script = getenv('ONHOST_INSTALL_DEPLOYER_SCRIPT') ?: base_path('infra/aapanel/install-deployer.sh');
    $process = new Process([(string) deployGateBash(), deployGatePosix((string) $script)], null, array_merge([
        'APP_DIR' => $box['APP'], 'ENV_FILE' => $box['ENV'], 'DEPLOY_STATE_DIR' => $box['posix'].'/state',
        'DEPLOYER_BIN' => $box['posix'].'/sbin/onhost-deploy', 'DEPLOYER_LIB_DIR' => $box['posix'].'/lib', 'DEPLOY_OWNER_UID' => $box['UID'],
        'SHA' => false, 'TAG' => false, 'FIRST' => false,
    ], $env));
    $process->setTimeout(60);
    $process->run();

    return $process;
}

it('in production verifies the tag itself: SSH signature, its own name, and no other signature kind — for the deployer and for a release', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/etc/app.env', "APP_ENV=production\nAPP_URL=https://staging.test\n");
    copy($box['dir'].'/allowed_signers.src', $box['dir'].'/state/allowed_signers');
    file_put_contents($box['dir'].'/bin/doctor.json', (string) json_encode(deployGateReport(environment: 'production')));
    $b = $box['SHA_B'];
    $sourceSha = fn () => trim((string) file_get_contents($box['dir'].'/lib/source-sha'));

    // the judge is installed under the same rules as a release
    $again = deployGateInstallDeployer($box, ['SHA' => $b, 'FIRST' => '1']);              // FIRST=1 on a host that has a deployer
    $untagged = deployGateInstallDeployer($box, ['SHA' => $b]);                             // production without the owner's tag
    $renamedInstall = deployGateInstallDeployer($box, ['SHA' => $b, 'TAG' => 'vrenamed']);
    expect($again->getExitCode())->toBe(2, $again->getErrorOutput())->and($again->getErrorOutput())->toContain('already installed')
        ->and($untagged->getExitCode())->toBe(2)->and($untagged->getErrorOutput())->toContain('TAG=')
        ->and($renamedInstall->getExitCode())->toBe(2)->and($renamedInstall->getErrorOutput())->toContain("calls itself 'vsigned'")
        ->and($sourceSha())->toBe($box['SHA_A']);

    // a release: the same tag object filed under another name, a PGP block (root's GnuPG keyring would judge it), then the real one
    $renamed = deployGateDeploy($box, ['REF' => 'vrenamed', 'EXPECTED_SHA' => $b]);
    $pgp = deployGateDeploy($box, ['REF' => 'vpgp', 'EXPECTED_SHA' => $b]);
    foreach ([$renamed, $pgp] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($renamed['out'])->toContain("calls itself 'vsigned'")->and($pgp['out'])->toContain('not SSH-signed');

    // B's deploy.sh is A's, so the installed deployer takes it; the signed tag passes the gate end to end
    $installed = deployGateInstallDeployer($box, ['SHA' => $b, 'TAG' => 'vsigned']);
    expect($installed->getExitCode())->toBe(0, $installed->getErrorOutput())->and($sourceSha())->toBe($b);
    $signed = deployGateDeploy($box, ['REF' => 'vsigned', 'EXPECTED_SHA' => $b]);
    expect($signed['rc'])->toBe(0, $signed['out'])->and($signed['head'])->toBe($b)
        ->and(json_decode((string) file_get_contents($box['dir'].'/state/last-good.json'), true)['tag'])->toBe('vsigned');
});

it('refuses before going down when the site .env is not the root-owned file, a cache directory is a link, or the deployer files are not root\'s', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);
    $app = $box['dir'].'/app';

    // www repoints .env at its own file saying staging while the root-owned file says production
    file_put_contents($box['dir'].'/etc/app.env', "APP_ENV=production\nAPP_URL=https://staging.test\n");
    unlink($app.'/.env');
    file_put_contents($app.'/.env', "APP_ENV=staging\nAPP_URL=https://staging.test\n");
    $repointed = deployGateDeploy($box, $to);
    file_put_contents($box['dir'].'/etc/app.env', "APP_ENV=staging   # staging | production\nAPP_URL=https://staging.test\n");
    unlink($app.'/.env');
    link($box['dir'].'/etc/app.env', $app.'/.env');

    // www swaps bootstrap/cache for a link to a tree root would then re-own and re-mode
    File::ensureDirectoryExists($box['dir'].'/elsewhere');
    file_put_contents($box['dir'].'/elsewhere/keep.txt', 'not the site');
    File::deleteDirectory($app.'/bootstrap/cache');
    PHP_OS_FAMILY === 'Windows'
        ? (new Process(['cmd', '/c', 'mklink', '/J', str_replace('/', '\\', $app.'/bootstrap/cache'), str_replace('/', '\\', $box['dir'].'/elsewhere')]))->mustRun()
        : symlink($box['dir'].'/elsewhere', $app.'/bootstrap/cache');
    $linked = deployGateDeploy($box, $to);
    PHP_OS_FAMILY === 'Windows' ? rmdir($app.'/bootstrap/cache') : unlink($app.'/bootstrap/cache');
    File::ensureDirectoryExists($app.'/bootstrap/cache');

    // the judge belongs to someone else
    $foreign = deployGateDeploy($box, $to + ['DEPLOY_OWNER_UID' => (string) ((int) $box['UID'] + 1)]);

    foreach ([$repointed, $linked, $foreign] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->not->toContain('chown')->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($repointed['out'])->toContain('/.env is not')
        ->and($linked['out'])->toContain('must be real directories')
        ->and($foreign['out'])->toContain('deployer files')
        ->and((string) file_get_contents($box['dir'].'/elsewhere/keep.txt'))->toBe('not the site');

    if (PHP_OS_FAMILY !== 'Windows') { // Git Bash has no group/other permission bits to test
        chmod($box['dir'].'/lib/deploy-gate.php', 0666);
        $writable = deployGateDeploy($box, $to);
        chmod($box['dir'].'/lib/deploy-gate.php', 0644);
        expect($writable['rc'])->toBe(2, $writable['out'])->and($writable['out'])->toContain('deployer files');
    }
});

it('in production reads Accept-Gate only from the signed message: a line appended after the signature refuses the tag', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/etc/app.env', "APP_ENV=production\nAPP_URL=https://staging.test\n");
    copy($box['dir'].'/allowed_signers.src', $box['dir'].'/state/allowed_signers');
    file_put_contents($box['dir'].'/bin/doctor.json', (string) json_encode(deployGateReport(['storage|queue driver' => 'WARN'], 'production')));
    $b = $box['SHA_B'];

    // someone who can push tags but lacks the owner's key re-files the owner's signed vsigned with one line after END
    $origin = $box['dir'].'/origin.git';
    $raw = (new Process(['git', '-C', $origin, 'cat-file', 'tag', 'refs/tags/vsigned']))->mustRun()->getOutput();
    $forged = new Process(['git', '-C', $origin, 'hash-object', '-t', 'tag', '-w', '--stdin']);
    $forged->setInput($raw."Accept-Gate: storage|queue driver — appended after the signature by someone without the key\n");
    $forged->mustRun();
    (new Process(['git', '-C', $origin, 'update-ref', 'refs/tags/vsigned', trim($forged->getOutput())]))->mustRun();

    $appended = deployGateDeploy($box, ['REF' => 'vsigned', 'EXPECTED_SHA' => $b]);
    expect($appended['rc'])->toBe(2, $appended['out'])->and($appended['out'])->toContain('unsigned content after its signature')
        ->and($appended['stub'])->not->toContain('artisan down')->and($appended['head'])->toBe($box['SHA_A']);
    $installer = deployGateInstallDeployer($box, ['SHA' => $b, 'TAG' => 'vsigned']);   // the judge's installer holds the same line
    expect($installer->getExitCode())->toBe(2, $installer->getErrorOutput())->and($installer->getErrorOutput())->toContain('unsigned content after its signature');

    // the owner's own Accept-Gate line inside the signed message still accepts the GATED row, and is logged
    $accepted = deployGateDeploy($box, ['REF' => 'vaccept', 'EXPECTED_SHA' => $b]);
    expect($accepted['rc'])->toBe(0, $accepted['out'])->and($accepted['head'])->toBe($b)
        ->and($accepted['log'])->toContain('accepted="storage|queue driver (WARN) by Accept-Gate in the signed tag;"');
});

/** Runs infra/aapanel/install.sh (or ONHOST_INSTALL_SCRIPT) against the sandbox, stubs first on PATH. */
function deployGateInstall(array $box, array $env): array
{
    @unlink($box['dir'].'/stub.log');
    File::ensureDirectoryExists($box['dir'].'/systemd');
    $script = getenv('ONHOST_INSTALL_SCRIPT') ?: base_path('infra/aapanel/install.sh');
    $process = new Process([(string) deployGateBash(), '-c', 'PATH="$STUB_BIN:$PATH" exec bash "$INSTALLER"'], null, array_merge([
        'STUB_BIN' => $box['posix'].'/bin', 'INSTALLER' => deployGatePosix((string) $script),
        'SITE' => 'staging.test', 'APP_DIR' => $box['APP'], 'PHP' => $box['posix'].'/bin/php', 'ENV_DIR' => $box['posix'].'/etc',
        'RUN_USER' => $box['USER'], 'DEPLOY_STATE_DIR' => $box['posix'].'/state', 'SYSTEMD_DIR' => $box['posix'].'/systemd',
        'DEPLOY_WORK_DIR' => $box['posix'].'/work/staging.test',
        'DEPLOY_OWNER_UID' => $box['UID'], 'QUEUES' => 'default mails',
        'INSTALL_REPAIR' => '1', 'START_UNITS' => false, 'REF' => false, 'EXPECTED_SHA' => false, 'BRANCH' => false,
    ], $env));
    $process->setTimeout(60);
    $process->run();

    return ['rc' => (int) $process->getExitCode(), 'out' => $process->getOutput().$process->getErrorOutput(), 'stub' => (string) @file_get_contents($box['dir'].'/stub.log')];
}

it('repairs an installed site without starting its units, without following a linked cache directory, and renders units from .git', function () {
    $box = $this->deployBox = deployGateSandbox();
    $app = $box['dir'].'/app';

    $notInstalled = deployGateInstall($box, []);
    expect($notInstalled['rc'])->toBe(2, $notInstalled['out'])->and($notInstalled['out'])->toContain('not installed');
    touch($box['dir'].'/state/installed');

    // www swaps bootstrap/cache for a link to another tree: the repair must not re-own or re-mode it
    File::ensureDirectoryExists($box['dir'].'/elsewhere');
    file_put_contents($box['dir'].'/elsewhere/keep.txt', 'not the site');
    File::deleteDirectory($app.'/bootstrap/cache');
    PHP_OS_FAMILY === 'Windows'
        ? (new Process(['cmd', '/c', 'mklink', '/J', str_replace('/', '\\', $app.'/bootstrap/cache'), str_replace('/', '\\', $box['dir'].'/elsewhere')]))->mustRun()
        : symlink($box['dir'].'/elsewhere', $app.'/bootstrap/cache');
    $linked = deployGateInstall($box, []);
    PHP_OS_FAMILY === 'Windows' ? rmdir($app.'/bootstrap/cache') : unlink($app.'/bootstrap/cache');
    File::ensureDirectoryExists($app.'/bootstrap/cache');
    expect($linked['rc'])->toBe(2, $linked['out'])->and($linked['out'])->toContain('real directories')
        ->and($linked['stub'])->not->toContain('chown')->not->toContain('systemctl')
        ->and((string) file_get_contents($box['dir'].'/elsewhere/keep.txt'))->toBe('not the site');

    // a contained staging (units stopped, disabled and masked on purpose) stays that way: the repair neither starts nor
    // ENABLES a unit — pre-mortem 2026-09-27: `enable` put every provider lane back into the boot sequence, so the next
    // reboot restarted the workers that talk to live panels. A unit file www wrote into the tree is not used.
    File::ensureDirectoryExists($app.'/infra/systemd');
    file_put_contents($app.'/infra/systemd/onhost-scheduler.service', "[Service]\nUser=root\nExecStart=/bin/sh -c 'planted by www'\n");
    file_put_contents($box['dir'].'/state/expected-units', "onhost-queue@default.service\n");
    $repair = deployGateInstall($box, []);
    $unit = (string) @file_get_contents($box['dir'].'/systemd/onhost-scheduler.service');
    expect($repair['rc'])->toBe(0, $repair['out'])
        ->and($repair['stub'])->toContain('systemctl daemon-reload')
        ->not->toContain('systemctl enable')->not->toContain('systemctl start')->not->toContain('systemctl restart')
        ->and($repair['stub'])->toContain('chown -h')
        ->and($unit)->toContain('User='.$box['USER'])->toContain('schedule:work')->not->toContain('planted by www')
        ->and((string) file_get_contents($box['dir'].'/state/expected-units'))->toBe("onhost-queue@default.service\n"); // the operator's list is kept

    $started = deployGateInstall($box, ['START_UNITS' => '1']);   // starting is an explicit choice
    expect($started['rc'])->toBe(0, $started['out'])->and($started['stub'])->toContain('systemctl enable --now onhost-scheduler.service');
});

it('does not call a release good when a drained unit does not come back: all of them stop again and the site stays down', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/state/last-good.json', (string) json_encode(['sha' => $box['SHA_A'], 'ref' => $box['SHA_A'], 'tag' => '', 'at' => 'x', 'operator' => 'op']));

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']), [
        'STUB_UNITS' => 'onhost-queue@default.service onhost-scheduler.service', 'STUB_START_FAIL' => 'onhost-queue@default.service',
    ]);

    expect($r['rc'])->toBe(7, $r['out'])->and($r['out'])->toContain('did not come back: onhost-queue@default.service')
        ->and(substr($r['stub'], (int) strpos($r['stub'], 'systemctl start onhost-scheduler.service')))->toContain('systemctl stop --no-block onhost-queue@default.service onhost-scheduler.service')
        ->and($r['stub'])->not->toContain('artisan up')
        ->and(json_decode((string) file_get_contents($box['dir'].'/app/storage/framework/down'), true)['secret'])->toBeNull()
        ->and(trim((string) file_get_contents($box['dir'].'/state/drained-units')))->toBe("onhost-queue@default.service\nonhost-scheduler.service")
        ->and($r['out'])->toContain("REF={$box['SHA_A']} EXPECTED_SHA={$box['SHA_A']}")
        ->and($r['log'])->toContain('stage=live rc=7');
});

// Pre-mortem 2026-09-27 (false-green HIGH, live-harm HIGH/MEDIUM): the deployer restarted only what happened to run, so a
// lane that was already dead or disabled stayed dead and the run still ended rc 0; a provider lane someone started on a
// contained staging came back with every release; and the staging environment was only printed, never asserted.
it('refuses before going down when an expected unit is not running or not enabled, a unit runs that is not expected, or a staging list does not hold', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);
    $state = $box['dir'].'/state';

    $dead = deployGateDeploy($box, $to, ['STUB_UNITS' => 'onhost-scheduler.service'], "onhost-scheduler.service\nonhost-queue@default.service\n");
    $disabled = deployGateDeploy($box, $to, ['STUB_UNITS' => 'onhost-queue@default.service', 'STUB_DISABLED' => 'onhost-queue@default.service']);
    $stray = deployGateDeploy($box, $to, ['STUB_UNITS' => 'onhost-queue@default.service onhost-queue@provider-ispconfig.service'], "onhost-queue@default.service\n");

    $env = (string) file_get_contents($state.'/expected-env');
    file_put_contents($state.'/expected-env', $env."COMGATE_RECURRING=false\n");   // the site's app.env does not say so
    $mismatch = deployGateDeploy($box, $to);
    file_put_contents($state.'/expected-env', $env);

    rename($state.'/expected-nonok', $state.'/expected-nonok.kept');
    $noList = deployGateDeploy($box, $to);
    rename($state.'/expected-nonok.kept', $state.'/expected-nonok');

    $noUnitsFile = deployGateDeploy($box, $to, [], false);

    foreach ([$dead, $disabled, $stray, $mismatch, $noList, $noUnitsFile] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->not->toContain('systemctl stop')->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($dead['out'])->toContain('onhost-queue@default.service')->toContain('not running')
        ->and($disabled['out'])->toContain("'disabled'")
        ->and($stray['out'])->toContain('onhost-queue@provider-ispconfig.service')->toContain('not in')
        ->and($mismatch['out'])->toContain('MISMATCH COMGATE_RECURRING')
        ->and($noList['out'])->toContain('expected-nonok')
        ->and($noUnitsFile['out'])->toContain('expected-units');

    // an empty list is a decision (a contained staging runs no unit), not a missing one
    $none = deployGateDeploy($box, $to, [], '');
    expect($none['rc'])->toBe(0, $none['out'])->and($none['head'])->toBe($box['SHA_B']);
});

it('stops a staging release on a doctor row that is not on the expected list, and passes it once the list names it', function () {
    $box = $this->deployBox = deployGateSandbox();
    file_put_contents($box['dir'].'/bin/doctor.json', (string) json_encode(deployGateReport(['mail|transactional mailer' => 'WARN'])));
    $to = deployGateTo($box, $box['SHA_B']);

    $stopped = deployGateDeploy($box, $to);
    expect($stopped['rc'])->toBe(5, $stopped['out'])->and($stopped['out'])->toContain('ROW-FAIL mail|transactional mailer (WARN)')
        ->and($stopped['stub'])->not->toContain('artisan up')
        ->and(is_file($box['dir'].'/app/storage/framework/down'))->toBeTrue();

    file_put_contents($box['dir'].'/state/expected-nonok', "# O11: staging sends mail to the log\nmail|transactional mailer\n");
    $passed = deployGateDeploy($box, $to);
    expect($passed['rc'])->toBe(0, $passed['out'])->and($passed['out'])->toContain('EXPECTED mail|transactional mailer (WARN)')
        ->and(is_file($box['dir'].'/app/storage/framework/down'))->toBeFalse()
        ->and($passed['log'])->toMatch('/expected_nonok=[0-9a-f]{12} /');
});

/**
 * Every line of a stub log in which the sandbox php ran the site's code (artisan or composer), each with the line
 * before it — the setpriv stub logs its own argv just before it hands over.
 *
 * @return list<array{string, string}>
 */
function deployGateSitePhp(string $stubLog): array
{
    $lines = explode("\n", $stubLog);
    $calls = [];
    foreach ($lines as $i => $line) {
        if (str_starts_with($line, 'php ') && (str_contains($line, '/artisan ') || str_contains($line, 'composer'))) {
            $calls[] = [$line, $lines[$i - 1] ?? ''];
        }
    }

    return $calls;
}

function deployGateExpectRunAsUser(string $stubLog, string $user): void
{
    $calls = deployGateSitePhp($stubLog);
    expect($calls)->not->toBeEmpty();
    foreach ($calls as [$php, $before]) {
        $argv = explode(' [cache=', substr($php, 4))[0];
        // review round 1 (security HIGH): in a session of its own (setsid), so no controlling terminal to push keys into
        expect($before)->toStartWith("setpriv --reuid={$user} --regid={$user} --init-groups -- setsid --wait env -i PATH=")
            ->and($before)->toContain(' '.$argv);
    }
    expect($stubLog)->not->toContain('COMPOSER_ALLOW_SUPERUSER');
}

// Review round 0 (security HIGH): app.env is root:www 0640 and the deployer ran the target's PHP (composer and its
// scripts, artisan, the doctor, the seeders) as root in a tree www can write — code, vendor/, bootstrap/cache, compiled
// views: a www compromise became root at the next release. Now root fetches, checks out, renames VERSION into place
// and repairs ownership; every call into the site's PHP runs as the run user (setpriv) with a clean
// environment, and a host where that cannot happen is refused before anything changes.
it('runs every artisan and composer call as the run user with a clean environment, never as root', function () {
    $box = $this->deployBox = deployGateSandbox();

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']), ['STUB_UNITS' => 'onhost-queue@default.service']);

    expect($r['rc'])->toBe(0, $r['out'])->and($r['head'])->toBe($box['SHA_B']);
    deployGateExpectRunAsUser($r['stub'], $box['USER']);
    expect($r['stub'])->toContain('composer install')->toContain('HOME='.$box['posix'].'/work/staging.test/home')
        ->and(is_dir($box['dir'].'/app/vendor'))->toBeTrue();
});

// Review round 1 (security HIGH): setpriv switched the user but left the site's PHP in root's session with root's
// terminal on fd 0-2 — code www controls could push keystrokes into root's shell (TIOCSTI, the su/runuser class,
// CVE-2016-2779) or, left running, read what root types next. Now it gets its own session (setsid, asserted on every
// call by deployGateExpectRunAsUser) and nothing of the operator's input; the deployer and install.sh hand a terminal
// to nothing they start (the same guard function in both, run first).
it('gives the site\'s PHP its own session and nothing of the operator\'s input, in the deployer and in install.sh', function () {
    $box = $this->deployBox = deployGateSandbox();

    $r = deployGateDeploy($box, deployGateTo($box, $box['SHA_B']), ['STUB_READ_STDIN' => '1'], null, "echo OPERATOR-TYPED-THIS\n");

    expect($r['rc'])->toBe(0, $r['out']);
    deployGateExpectRunAsUser($r['stub'], $box['USER']);
    expect((string) @file_get_contents($box['dir'].'/bin/stdin.seen'))->not->toContain('OPERATOR-TYPED-THIS')
        ->and(substr_count((string) @file_get_contents($box['dir'].'/bin/stdin.seen'), "read:\n"))->toBeGreaterThan(3);   // it did read, and got nothing

    foreach (['deploy.sh', 'install.sh'] as $script) {
        $source = (string) file_get_contents(base_path('infra/aapanel/'.$script));
        $guard = deployGateShellFunction($script, 'keep_terminal_from_children');
        expect($guard)->toContain('exec </dev/null')->toContain('exec cat')
            ->and(deployGateShellFunction($script, 'as_run'))->toMatch('#--init-groups -- \\\\\s+setsid --wait env -i [^\n]* </dev/null 9>&-#')
            // the guard runs before anything else is started (the first command after `set -euo pipefail` and its definition)
            ->and(strpos($source, "\nkeep_terminal_from_children\n"))->toBeGreaterThan(0)
            ->and(strpos($source, "\nkeep_terminal_from_children\n"))->toBeLessThan(strpos($source, "\nsay() {"));
    }

    // the runbook's hand-run www lines: never setpriv without setsid, and the everyday prefix takes no terminal input
    $doc = (string) file_get_contents(base_path('docs/runbooks/staging-launch.md'));
    expect($doc)->not->toMatch('/setpriv --reuid=www --regid=www --init-groups --(?! setsid --wait)/')
        ->and($doc)->toMatch('/^www\(\) \{[^\n]*setsid --wait "\$@" <\/dev\/null/m');
});

it('refuses before going down when the site\'s PHP cannot run as the run user, the run user is root, or vendor is a link', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);
    $app = $box['dir'].'/app';

    $noSetpriv = deployGateDeploy($box, $to, ['STUB_SETPRIV_EXIT' => '1']);
    $root = deployGateDeploy($box, $to + ['RUN_USER' => 'root']);

    File::ensureDirectoryExists($box['dir'].'/elsewhere');
    PHP_OS_FAMILY === 'Windows'
        ? (new Process(['cmd', '/c', 'mklink', '/J', str_replace('/', '\\', $app.'/vendor'), str_replace('/', '\\', $box['dir'].'/elsewhere')]))->mustRun()
        : symlink($box['dir'].'/elsewhere', $app.'/vendor');
    $linkedVendor = deployGateDeploy($box, $to);
    PHP_OS_FAMILY === 'Windows' ? rmdir($app.'/vendor') : unlink($app.'/vendor');

    foreach ([$noSetpriv, $root, $linkedVendor] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->not->toContain('systemctl stop')
            ->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($noSetpriv['out'])->toContain('cannot run the site\'s PHP as')
        ->and($root['out'])->toContain('RUN_USER')
        ->and($linkedVendor['out'])->toContain('vendor');
});

// Review round 0 (security MEDIUM): Path A's isolation from the live panels rested on a written revocation nobody can
// check, and EgressGuard guards only the destinations customers name. The staging list egress-blocked names every
// address the kept database's instances point at; the deployer connects to each as the run user and refuses the release
// while any of them answers.
it('refuses a staging release while an address of the egress-blocked list can be reached, probing as the run user', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);
    $list = $box['dir'].'/state/egress-blocked';
    // the host's reject table covers loopback here (review round 1: the probe alone is not enough, see the next case)
    file_put_contents($box['dir'].'/bin/nft.table', "table inet onhost_containment {\n\tchain out {\n\t\tip daddr 127.0.0.1 reject\n\t}\n}\n");

    rename($list, $list.'.kept');
    $missing = deployGateDeploy($box, $to);
    rename($list.'.kept', $list);

    file_put_contents($list, "# O4: live panels\nnot an address\n");
    $garbage = deployGateDeploy($box, $to);

    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    expect($server)->not->toBeFalse();
    $open = (string) stream_socket_get_name($server, false);
    file_put_contents($list, "# O4: live panels\n127.0.0.1:1\n{$open}\n");
    $reached = deployGateDeploy($box, $to);
    fclose($server);

    foreach ([$missing, $garbage, $reached] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($missing['out'])->toContain('egress-blocked')
        ->and($garbage['out'])->toContain('not an address')
        ->and($reached['out'])->toContain($open)->toContain('can be reached')->not->toContain('127.0.0.1:1 ')
        ->and($reached['stub'])->toMatch('#setpriv --reuid='.preg_quote($box['USER'], '#').' [^\n]*/dev/tcp#');

    $blocked = deployGateDeploy($box, $to);   // the listener is gone: nothing on the list answers, the host rejects both
    expect($blocked['rc'])->toBe(0, $blocked['out'])->and($blocked['head'])->toBe($box['SHA_B']);
});

// Review round 1 (security MEDIUM): a refused connection proved nothing on its own — a name that did not resolve, a DNS
// outage or a panel that was down passed exactly like the host's reject rule, and an empty list passed on every staging.
// Now a name must resolve and every address it resolves to must be rejected by the host's own table (read with nft);
// an empty list needs the root-owned Path B marker.
it('holds the egress-blocked list to the host\'s reject rules: a name must resolve, each address must be rejected, and only Path B may list none', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);
    $list = $box['dir'].'/state/egress-blocked';
    $table = $box['dir'].'/bin/nft.table';
    $marker = $box['dir'].'/state/path-b';

    unlink($marker);
    $emptyPathA = deployGateDeploy($box, $to);                  // the sandbox's list names nothing

    file_put_contents($list, "panel.invalid:8080\n");
    $unresolved = deployGateDeploy($box, $to);                  // NXDOMAIN used to count as blocked

    file_put_contents($box['dir'].'/bin/hosts', "127.0.0.1 panel.example.test\n");
    file_put_contents($list, "panel.example.test:1\n");
    @unlink($table);
    $noTable = deployGateDeploy($box, $to);                     // nothing answers, but no rule refused it

    file_put_contents($table, "table inet onhost_containment {\n\tchain out {\n\t\tip daddr 192.0.2.0/24 reject\n\t}\n}\n");
    $unruled = deployGateDeploy($box, $to);                     // a table, but not for this address

    foreach ([$emptyPathA, $unresolved, $noTable, $unruled] as $r) {
        expect($r['rc'])->toBe(2, $r['out'])->and($r['stub'])->not->toContain('artisan down')->and($r['head'])->toBe($box['SHA_A']);
    }
    expect($emptyPathA['out'])->toContain('path-b')
        ->and($unresolved['out'])->toContain("'panel.invalid' does not resolve")
        ->and($noTable['out'])->toContain('onhost_containment')
        ->and($unruled['out'])->toContain('not rejected')->toContain('127.0.0.1');

    // the name's address is rejected by the host and nothing answers: released; the probe went to the resolved address
    file_put_contents($table, "table inet onhost_containment {\n\tchain out {\n\t\tip daddr 192.0.2.0/24 reject\n\t\tip daddr 127.0.0.1 reject\n\t}\n}\n");
    $held = deployGateDeploy($box, $to);
    expect($held['rc'])->toBe(0, $held['out'])->and($held['head'])->toBe($box['SHA_B'])
        ->and($held['stub'])->toMatch('#/dev/tcp[^\n]* 127\.0\.0\.1 1\n#');

    // Path B: an empty list with the root-owned marker
    file_put_contents($list, "# Path B: no live credential on this host\n");
    touch($marker);
    $pathB = deployGateDeploy($box, deployGateTo($box, $box['SHA_A']));
    expect($pathB['rc'])->toBe(0, $pathB['out'])->and($pathB['head'])->toBe($box['SHA_A']);
});

// Review round 1 (qa MEDIUM): the per-site lock had no test. A second release of the same site (an operator racing a
// rehearsal, two operators) must be refused before it changes anything, and the running one must finish undisturbed.
it('refuses a second release of the same site while one runs, and leaves the running one alone', function () {
    $box = $this->deployBox = deployGateSandbox();
    $to = deployGateTo($box, $box['SHA_B']);
    $stub = ['STUB_UNITS' => 'onhost-queue@default.service'];
    $hold = $box['dir'].'/bin/hold-drain';
    touch($hold);   // the first run's worker does not stop while this file exists: it waits in its drain, holding the lock

    $first = deployGateDeployProcess($box, $to + ['DRAIN_TIMEOUT' => '60'], $stub);
    $first->setTimeout(300);   // it waits while the second run is judged
    $first->start();
    $deadline = microtime(true) + 90;
    while ($first->isRunning() && ! str_contains((string) @file_get_contents($box['dir'].'/stub.log'), 'systemctl stop') && microtime(true) < $deadline) {
        usleep(200_000);
    }
    expect($first->isRunning())->toBeTrue($first->getOutput().$first->getErrorOutput());

    $second = deployGateDeploy($box, $to + ['DEPLOY_OPERATOR' => 'second-operator', 'DRAIN_TIMEOUT' => '20'], $stub);   // were it let in, it would wait in the same drain
    $whileHeld = [
        'last_good' => is_file($box['dir'].'/state/last-good.json'),
        'version' => is_file($box['dir'].'/app/VERSION'),
        'head' => $second['head'],
    ];
    unlink($hold);
    $first->wait();
    $done = deployGateDeployResult($box, $first);

    expect($second['rc'])->toBe(2, $second['out'])->and($second['out'])->toContain('another deploy of staging.test is running')
        ->and($whileHeld)->toBe(['last_good' => false, 'version' => false, 'head' => $box['SHA_A']])
        ->and($second['log'])->toMatch('/operator=second-operator [^\n]* stage=preflight rc=2 /')
        ->and($done['rc'])->toBe(0, $done['out'])->and($done['head'])->toBe($box['SHA_B'])
        ->and(preg_match_all('#^php \S*/artisan down --retry=60 --with-secret#m', $done['stub']))->toBe(1)   // one run went down
        ->and(preg_match_all('#^php \S*composer install#m', $done['stub']))->toBe(1)                        // and built
        ->and(substr_count($done['log'], ' rc=0 '))->toBe(1)
        ->and(json_decode((string) file_get_contents($box['dir'].'/state/last-good.json'), true)['operator'])->toBe('test-operator');
});

it('installs with the site\'s PHP run only as the run user, and writes the application key itself', function () {
    $box = $this->deployBox = deployGateSandbox();
    // run 2 of a first install: the environment file is filled, nothing is installed yet
    file_put_contents($box['dir'].'/etc/app.env', "APP_ENV=staging\nAPP_URL=https://staging.test\nAPP_KEY=\nDB_PASSWORD=filled\n");
    file_put_contents($box['dir'].'/bin/composer', "#!/usr/bin/env bash\nexit 0\n");
    chmod($box['dir'].'/bin/composer', 0755);

    $r = deployGateInstall($box, ['INSTALL_REPAIR' => '0', 'REF' => $box['SHA_A'], 'EXPECTED_SHA' => $box['SHA_A'], 'START_UNITS' => '0',
        'COMPOSER' => $box['posix'].'/bin/composer']);

    expect($r['rc'])->toBe(0, $r['out']);
    deployGateExpectRunAsUser($r['stub'], $box['USER']);
    expect($r['stub'])->toContain('composer install')->toContain('artisan migrate')->not->toContain('key:generate')
        ->and((string) file_get_contents($box['dir'].'/etc/app.env'))->toMatch('#^APP_KEY=base64:[A-Za-z0-9+/]{43}=$#m')
        ->and(is_file($box['dir'].'/state/installed'))->toBeTrue();
});

it('keeps bash syntax valid and never ignores the doctor again', function () {
    $bash = deployGateBash();
    if ($bash === null) {
        getenv('CI') ? throw new RuntimeException('bash is required in CI') : test()->markTestSkipped('no bash');
    }
    foreach (['deploy.sh', 'install.sh', 'install-deployer.sh', 'relay-install.sh', 'ci/deploy-sandbox.sh', 'ci/deploy-e2e.sh'] as $script) {
        $p = new Process([$bash, '-n', deployGatePosix(base_path('infra/aapanel/'.$script))]);
        $p->run();
        expect($p->getExitCode())->toBe(0, $script.': '.$p->getErrorOutput());
    }
    $code = implode("\n", array_filter(explode("\n", (string) file_get_contents(base_path('infra/aapanel/deploy.sh'))), fn (string $line) => ! str_starts_with(ltrim($line), '#')));
    expect($code)->not->toMatch('/onhost:doctor[^\n]*\|\|\s*true/')->toContain('gate verdict')
        ->and((string) file_get_contents(base_path('infra/aapanel/install.sh')))->not->toContain('--global');
});

/** The source of shell function $name in infra/aapanel/$file (from `name() {` to the first `}` at column 0). */
function deployGateShellFunction(string $file, string $name): string
{
    $source = (string) file_get_contents(base_path('infra/aapanel/'.$file));

    return preg_match('/^'.preg_quote($name, '/').'\(\) \{.*?^\}$/ms', $source, $m) === 1 ? $m[0] : '';
}

it('keeps the security-critical shell functions identical where two scripts need them, and never writes VERSION through a name', function () {
    // the deployer and install.sh repair the same tree the same way, and run the site's PHP as the same user the same way
    foreach (['tree_is_real', 'repair_ownership', 'as_run', 'work_dir_ready', 'vendor_ready', 'keep_terminal_from_children'] as $name) {
        expect(deployGateShellFunction('deploy.sh', $name))->not->toBe('')->toBe(deployGateShellFunction('install.sh', $name));
    }
    expect(deployGateShellFunction('deploy.sh', 'verify_signed_tag'))->not->toBe('')->toBe(deployGateShellFunction('install-deployer.sh', 'verify_signed_tag'));
    foreach (['deploy.sh', 'install.sh'] as $script) {
        $source = (string) file_get_contents(base_path('infra/aapanel/'.$script));
        expect($source)->not->toMatch('#>\s*"\$APP_DIR/VERSION"#')->not->toMatch('#chown -R[^\n]*(storage|bootstrap)#')->not->toMatch('#chmod -R[^\n]*(storage|bootstrap)#');
    }
});
