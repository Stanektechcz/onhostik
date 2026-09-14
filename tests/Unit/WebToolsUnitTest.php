<?php

declare(strict_types=1);

use App\Http\Presenters\Presenters;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Web\AcmeClient;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Services\Web\CertificateService;
use Onhost\Domain\Services\Web\CommandRunner;
use Onhost\Domain\Services\Web\DeployService;
use Onhost\Domain\Services\Web\ImportService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Cloudflare\CloudflareCdnProvider;
use Onhost\Providers\Shell\ScriptedShell;
use Onhost\Providers\Shell\SecurityRules;
use Onhost\Providers\Shell\SshShell;

/* The web toolkit's pure pieces: the terminal guard, the managed security block, repository parsing, ACME key material, import archive analysis, result presentation. */

it('lets the terminal run the site tools and refuses privilege, daemons and substitution', function () {
    expect(CommandRunner::guard('wp plugin list | grep akismet'))->toBe('wp plugin list | grep akismet')
        ->and(CommandRunner::guard('cd app && composer install --no-dev; php artisan migrate --force'))->toContain('composer install')
        ->and(CommandRunner::guard('NODE_ENV=production npm run build'))->toContain('npm run build')
        ->and(CommandRunner::guard('./vendor/bin/phpunit'))->toBe('./vendor/bin/phpunit');
    foreach (['sudo ls', 'su -', 'crontab -l', 'nohup php worker.php', 'systemctl restart nginx', 'python3 script.py', 'perl -e 1'] as $bad) {
        expect(fn () => CommandRunner::guard($bad))->toThrow(DomainError::class, null, $bad);
    }
    foreach (['php worker.php &', 'echo $(whoami)', 'echo `id`', "ls\nrm -rf x", ''] as $bad) {
        try {
            CommandRunner::guard($bad);
            $this->fail("expected {$bad} to be rejected");
        } catch (DomainError $e) {
            expect($e->error)->toBe('command_invalid');
        }
    }
    try {
        CommandRunner::guard('sudo ls');
    } catch (DomainError $e) {
        expect($e->error)->toBe('command_forbidden')->and($e->status)->toBe(422);
    }
});

it('renders the managed security block for nginx and apache and parses it back without touching customer directives', function () {
    $rules = SecurityRules::normalize(['deny' => ['203.0.113.7', '198.51.100.0/24', 'not-an-ip'], 'allow' => [], 'bots' => '1', 'hotlink' => true, 'hotlink_allow' => ['cdn.shop.cz', 'bad host!'], 'hsts' => true, 'headers' => true, 'rate' => ['perip' => 20, 'perserver' => 200, 'limit_rate' => 512]]);
    expect($rules['deny'])->toBe(['203.0.113.7', '198.51.100.0/24'])->and($rules['bots'])->toBeTrue()->and($rules['hotlink_allow'])->toBe(['cdn.shop.cz'])->and($rules['rate']['perip'])->toBe(20);

    $nginx = SecurityRules::nginx($rules, 'shop.cz');
    expect($nginx)->toContain(SecurityRules::BEGIN)->toContain(SecurityRules::END)->toContain('deny 203.0.113.7;')->toContain('deny 198.51.100.0/24;')->toContain('Strict-Transport-Security')->toContain('X-Content-Type-Options')->toContain('AhrefsBot')->toContain('cdn.shop.cz');
    $parsed = SecurityRules::parse($nginx); // rate limits are applied through the panel's own limiter, not the block
    expect($parsed['deny'])->toBe($rules['deny'])->and($parsed['hsts'])->toBeTrue()->and($parsed['hotlink_allow'])->toBe(['cdn.shop.cz']);

    $apache = SecurityRules::apache($rules, 'shop.cz');
    expect($apache)->toContain('Require not ip 203.0.113.7')->toContain('Strict-Transport-Security')->toContain('RewriteCond %{HTTP_REFERER}');

    $customer = "Header set X-Custom \"1\"\nRedirect 301 /old /new";
    $spliced = SecurityRules::splice($customer, $apache);
    expect($spliced)->toContain('Header set X-Custom "1"')->toContain(SecurityRules::BEGIN);
    $again = SecurityRules::splice($spliced, SecurityRules::apache(SecurityRules::normalize(['hsts' => false]), 'shop.cz'));
    expect(substr_count($again, SecurityRules::BEGIN))->toBe(1)->and($again)->not->toContain('Strict-Transport-Security')->toContain('Redirect 301 /old /new');
    expect(SecurityRules::strip($again))->toBe($customer);
    expect(SecurityRules::iniValue('memory_limit', '256m'))->toBe('256M')->and(SecurityRules::iniValue('display_errors', 'yes'))->toBe('On');
});

it('parses repositories from GitHub shorthand, GitLab paths and SSH clone URLs', function () {
    expect(DeployService::parseRepository('onhost/site'))->toBe(['github', 'git@github.com:onhost/site.git', 'onhost/site'])
        ->and(DeployService::parseRepository('https://github.com/onhost/site.git'))->toBe(['github', 'https://github.com/onhost/site.git', 'onhost/site']) // https stays https: public repositories need no deploy key
        ->and(DeployService::parseRepository('gitlab.com/group/sub/app'))->toBe(['gitlab', 'git@gitlab.com:group/sub/app.git', 'group/sub/app'])
        ->and(DeployService::parseRepository('git@git.example.com:team/app.git')[0])->toBe('generic');
    expect(fn () => DeployService::parseRepository('not a repo!'))->toThrow(DomainError::class);
});

it('composes shell commands with a working directory and environment and caps captured output', function () {
    $composed = SshShell::compose('composer install', ['cwd' => '/var/www/site', 'env' => ['APP_ENV' => 'production', 'bad key' => 'x']]);
    expect($composed)->toContain('/var/www/site')->toStartWith('cd ')->toContain('export APP_ENV=')->not->toContain('bad key')->toContain('composer install'); // quoting follows the platform's escapeshellarg
    expect(strlen(SshShell::cap(str_repeat('x', 2 * 1024 * 1024))))->toBeLessThanOrEqual(1024 * 1024 + 200);
    $shell = new ScriptedShell(['/^git status/' => [0, 'clean'], '/^composer/' => [1, '', 'boom']], true);
    expect($shell->run('git status')->ok())->toBeTrue()->and($shell->run('composer install')->exitCode)->toBe(1)->and($shell->run('ls')->exitCode)->toBe(127)->and($shell->ran('composer'))->toBeTrue();
});

it('builds wildcard certificate requests and reads issued certificates', function () {
    $acme = app(AcmeClient::class);
    $material = $acme->csr(['example.cz', '*.example.cz']);
    expect($material['key'])->toContain('PRIVATE KEY')->and($material['csr_pem'])->toContain('CERTIFICATE REQUEST')->and(strlen($material['csr_der']))->toBeGreaterThan(200);
    expect(AcmeClient::base64url("\xff\xfe"))->toBe('__4');

    $config = tempnam(sys_get_temp_dir(), 'x509');
    file_put_contents($config, "[req]\ndefault_bits = 2048\ndefault_md = sha256\ndistinguished_name = dn\n[dn]\n[v3_req]\nsubjectAltName = DNS:example.cz,DNS:*.example.cz\n");
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048, 'config' => $config]);
    $csr = openssl_csr_new(['commonName' => 'example.cz', 'organizationName' => 'Test CA'], $key, ['config' => $config, 'digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 90, ['config' => $config, 'x509_extensions' => 'v3_req', 'digest_alg' => 'sha256']);
    openssl_x509_export($cert, $pem);
    @unlink($config);
    $info = AcmeClient::inspect($pem."\n".$pem);
    expect($info['domains'])->toBe(['example.cz', '*.example.cz'])->and($info['not_after'])->toBeGreaterThan(time() + 80 * 86400);
    [$leaf, $chain] = CertificateService::split($pem."\n".$pem);
    expect($leaf)->toContain('BEGIN CERTIFICATE')->and($chain)->toContain('BEGIN CERTIFICATE')->and(substr_count($chain, 'BEGIN CERTIFICATE'))->toBe(1);
});

it('unpacks cPanel-style archives, finds the document root and the SQL dumps', function () {
    $service = app(ImportService::class);
    $dir = sys_get_temp_dir().'/onhost-import-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $zip = new ZipArchive;
    $zip->open($dir.'/backup.zip', ZipArchive::CREATE);
    $zip->addFromString('backup-9.12/homedir/public_html/index.php', '<?php echo 1;');
    $zip->addFromString('backup-9.12/homedir/public_html/wp-config.php', "<?php define('DB_NAME', 'old_shop');");
    $zip->addFromString('backup-9.12/homedir/public_html/wp-content/uploads/a.txt', 'x');
    $zip->addFromString('backup-9.12/mysql/old_shop.sql', 'CREATE TABLE t (id int);');
    $zip->addFromString('backup-9.12/mysql.sql', 'GRANT ALL');
    $zip->addFromString('../evil.php', 'no');
    $zip->close();
    expect($service->unpack($dir.'/backup.zip', $dir))->toBe(5);
    $analysis = $service->analyze($dir);
    expect($analysis['docroot_rel'])->toBe('backup-9.12/homedir/public_html')->and($analysis['layout'])->toBe('cpanel')->and($analysis['wordpress'])->toBeTrue()->and($analysis['files'])->toBe(3)
        ->and(array_column($analysis['databases'], 'name'))->toBe(['old_shop']);
    expect(file_exists($dir.'/evil.php'))->toBeFalse();

    $dir2 = sys_get_temp_dir().'/onhost-import-'.bin2hex(random_bytes(4));
    mkdir($dir2.'/src/site', 0777, true);
    file_put_contents($dir2.'/src/site/index.html', '<h1>hi</h1>');
    file_put_contents($dir2.'/src/site/dump.sql.gz', gzencode('SELECT 1'));
    $tar = new PharData($dir2.'/site.tar');
    $tar->buildFromDirectory($dir2.'/src');
    $tar->compress(Phar::GZ);
    unset($tar);
    $work = $dir2.'/work';
    mkdir($work);
    $service->unpack($dir2.'/site.tar.gz', $work);
    $generic = $service->analyze($work);
    expect($generic['docroot_rel'])->toBe('site')->and($generic['layout'])->toBe('generic')->and($generic['wordpress'])->toBeFalse()->and($generic['databases'])->toBe([]);
});

it('presents only the customer-safe part of an operation result', function () {
    $operation = new Operation(['id' => 'op_x', 'kind' => 'service.action', 'state' => Operation::SUCCEEDED, 'desired' => ['action' => 'command.run'], 'result' => ['last_result' => ['exit_code' => 0, 'output' => 'index.php', 'vendor_payload' => ['token' => 'secret']], 'log' => 'done', 'internal_ids' => [1, 2]]]);
    $result = Presenters::customerResult($operation);
    expect($result)->toBe(['exit_code' => 0, 'output' => 'index.php', 'log' => 'done']);
    expect(Presenters::operation($operation)['action'])->toBe('command.run');
    expect(Presenters::customerResult(new Operation(['result' => ['step' => 1]])))->toBeNull();
});

it('knows the backup frequencies and the edge settings vocabulary', function () {
    expect(array_keys(BackupScheduler::FREQUENCIES))->toBe(['15m', 'hourly', '6h', 'daily', 'weekly'])->and(BackupScheduler::FREQUENCIES['6h'])->toBe(360);
    expect(CloudflareCdnProvider::SETTINGS)->toContain('ssl', 'http3', 'security_level', 'always_use_https');
});
