<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\IspConfig\IspConfigWebProvider;
use Onhost\Providers\Shell\ManagedDirectives;
use Onhost\Providers\Shell\SecurityRules;

function ispToolsAdapter(): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web', 'mail'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance);
}

function ispToolsResponse(mixed $response, string $code = 'ok', string $message = ''): array
{
    return ['code' => $code, 'message' => $message, 'response' => $response];
}

/*
 * The web & mail toolkit on ISPConfig: everything that goes through the remote API (php.ini per site, quotas, cron,
 * database remote access, the staff login link, forwards, catch-all, autoresponder, spam policies and lists, filters,
 * mailing lists, fetchmail, mailbox backups, usage). The node shell (agent user over SSH) is covered by the feature tests.
 */

function ispToolsFake(array &$calls, array $answers): void
{
    Http::fake(function ($request) use (&$calls, $answers) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = [$function, $body];
        if ($function === 'login') {
            return Http::response(ispToolsResponse('sess-tools'));
        }
        if (! array_key_exists($function, $answers)) {
            return Http::response(ispToolsResponse(false, 'remote_fault', "unexpected {$function}"));
        }
        $answer = $answers[$function];

        return Http::response(ispToolsResponse($answer instanceof Closure ? $answer($body) : $answer));
    });
}

it('reads and writes per-site php.ini settings, quotas, cron, database access and the staff login link on ISPConfig', function () {
    $calls = [];
    $updates = [];
    ispToolsFake($calls, [
        'sites_web_domain_get' => ['domain_id' => 7, 'domain' => 'shop.cz', 'fastcgi_php_version' => 'PHP 8.3:/usr/bin/php-fpm8.3:/etc/php/8.3/fpm', 'custom_php_ini' => "memory_limit = 256M\nupload_max_filesize = 64M\nsendmail_path = /usr/sbin/sendmail\n", 'sys_groupid' => 3, 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7', 'php_open_basedir' => '/var/www/clients/client3/web7:/tmp'],
        'sites_web_domain_update' => function (array $body) use (&$updates) {
            $updates[] = $body['params'];

            return true;
        },
        'quota_get_by_user' => [['domain_id' => 7, 'used' => 204800, 'hard' => 5120000, 'files' => 1234], ['domain_id' => 8, 'used' => 1]],
        'trafficquota_get_by_user' => [['domain_id' => 7, 'this_month' => 3000000, 'traffic_quota' => 100]],
        'client_get' => ['client_id' => 3, 'username' => 'ohabc123'],
        'client_login_get' => '/login/?login_as=xyz',
        'sites_cron_get' => [['id' => 5, 'run_min' => '0', 'run_hour' => '3', 'run_mday' => '*', 'run_month' => '*', 'run_wday' => '*', 'command' => 'php cron.php', 'active' => 'y']],
        'sites_cron_update' => true,
        'sites_database_get' => ['database_id' => 4, 'database_name' => 'ohabc123_shop', 'parent_domain_id' => 7, 'remote_access' => 'y', 'remote_ips' => '203.0.113.7, 203.0.113.8'],
        'sites_database_update' => true,
        'monitor_jobqueue_count' => 0,
    ]);
    $adapter = ispToolsAdapter();
    $ref = new ResourceRef('web_domain', '7', '1', ['client_id' => 3, 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7'], 'srv_tools');

    $php = $adapter->phpSettings($ref);
    expect($php['version'])->toBe('8.3')->and($php['settings'])->toBe(['memory_limit' => '256M', 'upload_max_filesize' => '64M', 'sendmail_path' => '/usr/sbin/sendmail'])->and($php['editable'])->toContain('memory_limit', 'display_errors')->and($php['extensions'])->toBe([]);
    $adapter->setPhpSettings($ref, ['memory_limit' => '512M', 'display_errors' => 'On', 'disable_functions' => 'exec']);
    $ini = (string) end($updates)['custom_php_ini'];
    expect($ini)->toContain('memory_limit = 512M')->toContain('display_errors = On')->toContain('sendmail_path = /usr/sbin/sendmail')->not->toContain('disable_functions')->not->toContain('256M');

    $quotas = $adapter->quotas($ref);
    expect($quotas['disk_used_bytes'])->toBe(204800 * 1024)->and($quotas['disk_limit_bytes'])->toBe(5120000 * 1024)->and($quotas['inodes_used'])->toBe(1234)->and($quotas['traffic_used_bytes'])->toBe(3000000)->and($quotas['traffic_limit_bytes'])->toBe(100 * 1048576);

    expect($adapter->panelLoginUrl($ref))->toBe('https://shared01.mgmt.test:8080/login/?login_as=xyz');

    $adapter->updateCron($ref, '5', ['schedule' => '15 4 * * 1', 'active' => false]);
    $cron = collect($calls)->last(fn ($c) => $c[0] === 'sites_cron_update')[1]['params'];
    expect($cron)->toMatchArray(['run_min' => '15', 'run_hour' => '4', 'run_wday' => '1', 'command' => 'php cron.php', 'active' => 'n']);

    expect($adapter->databaseAccess($ref, '4'))->toBe(['remote' => true, 'hosts' => ['203.0.113.7', '203.0.113.8']]);
    $adapter->setDatabaseAccess($ref, '4', false);
    expect(collect($calls)->last(fn ($c) => $c[0] === 'sites_database_update')[1]['params']['remote_access'])->toBe('n');
    expect(json_encode(DB::table('provider_calls')->pluck('request')))->not->toContain('remote-secret');
});

it('manages forwards, catch-all, autoresponder, spam policies and lists, filters, mailing lists, fetchmail, mailbox backups and usage on ISPConfig', function () {
    $calls = [];
    ispToolsFake($calls, [
        'mail_domain_get' => ['domain_id' => 9, 'domain' => 'shop.cz'],
        'mail_forward_get' => [['forwarding_id' => 31, 'source' => 'info@shop.cz', 'destination' => 'jana@gmail.com', 'active' => 'y']],
        'mail_forward_add' => 32,
        'mail_catchall_get' => [],
        'mail_catchall_add' => 40,
        'mail_user_get' => fn (array $body) => is_array($body['primary_id'] ?? null)
            ? [['mailuser_id' => 21, 'email' => 'jana@shop.cz', 'name' => 'Jana', 'quota' => 2147483648]]
            : ['mailuser_id' => 21, 'email' => 'jana@shop.cz', 'autoresponder' => 'y', 'autoresponder_subject' => 'Dovolená', 'autoresponder_text' => 'Jsem pryč', 'autoresponder_start_date' => '2026-09-10 00:00:00', 'autoresponder_end_date' => '0000-00-00 00:00:00'],
        'mail_user_update' => true,
        'mail_policy_get' => [['id' => 1, 'policy_name' => 'Normal'], ['id' => 3, 'policy_name' => 'Wants all spam']],
        'mail_spamfilter_user_get' => fn (array $body) => ($body['primary_id']['email'] ?? '') === 'jana@shop.cz' ? [['id' => 60, 'email' => 'jana@shop.cz', 'policy_id' => 3]] : (($body['primary_id']['email'] ?? '') === '%shop.cz' ? [['id' => 61, 'email' => '@shop.cz', 'policy_id' => 1], ['id' => 99, 'email' => '@eshop.cz', 'policy_id' => 1]] : [['id' => 61, 'email' => '@shop.cz', 'policy_id' => 1]]), // `%shop.cz` without the @ is also somebody else's eshop.cz
        'mail_spamfilter_whitelist_get' => fn (array $body) => (int) ($body['primary_id']['rid'] ?? 0) === 99 ? [['wblist_id' => 990, 'email' => 'cizi@eshop.cz', 'active' => 'y']] : [['wblist_id' => 70, 'email' => 'partner@example.com', 'active' => 'y']],
        'mail_spamfilter_blacklist_get' => [],
        'mail_spamfilter_blacklist_add' => 71,
        'mail_user_filter_get' => [['filter_id' => 80, 'rulename' => 'Newsletters', 'source' => 'Subject', 'op' => 'contains', 'searchterm' => 'newsletter', 'action' => 'move', 'target' => 'Newsletters', 'active' => 'y']],
        'mail_user_filter_add' => 81,
        'mail_mailinglist_get' => [['mailinglist_id' => 90, 'listname' => 'novinky', 'email' => 'novinky@shop.cz']],
        'mail_mailinglist_add' => 91,
        'mail_fetchmail_get' => [['mailget_id' => 50, 'type' => 'imapssl', 'source_server' => 'imap.seznam.cz', 'source_username' => 'jana', 'destination' => 'jana@shop.cz', 'source_delete' => 'n', 'active' => 'y']],
        'mail_fetchmail_add' => 51,
        'mail_user_backup_list' => [['backup_id' => 7, 'tstamp' => 1757400000, 'filesize' => 1048576]],
        'mail_user_backup' => true,
        'mailquota_get_by_user' => [['email' => 'jana@shop.cz', 'used' => 104857600, 'quota' => 2147483648], ['email' => 'x@other.cz', 'used' => 1, 'quota' => 1]],
        'monitor_jobqueue_count' => 0,
    ]);
    $adapter = ispToolsAdapter();
    $domain = new ResourceRef('mail_domain', '9', '1', ['client_id' => 3, 'domain' => 'shop.cz'], 'srv_tools');
    $mailbox = new ResourceRef('mailbox', '21', '1', ['client_id' => 3], 'srv_tools');
    $params = function (string $function) use (&$calls): array {
        $call = collect($calls)->last(fn ($c) => $c[0] === $function);
        expect($call)->not->toBeNull("expected a {$function} call");

        return (array) ($call[1]['params'] ?? []);
    };

    expect($adapter->listForwards($domain))->toBe([['remote_id' => '31', 'source' => 'info@shop.cz', 'destination' => 'jana@gmail.com', 'active' => true]]);
    expect($adapter->createForward($domain, ['source' => 'obchod@shop.cz', 'destination' => 'prodej@gmail.com'])->isAsync())->toBeTrue();
    expect($params('mail_forward_add'))->toMatchArray(['source' => 'obchod@shop.cz', 'destination' => 'prodej@gmail.com']);

    expect($adapter->catchAll($domain))->toBeNull();
    expect($adapter->setCatchAll($domain, 'jana@shop.cz')->isAsync())->toBeTrue()->and($params('mail_catchall_add'))->toMatchArray(['domain' => 'shop.cz', 'destination' => 'jana@shop.cz']);
    expect($adapter->setCatchAll($domain, '')->alreadyExisted)->toBeTrue();

    $auto = $adapter->autoresponder($mailbox);
    expect($auto)->toMatchArray(['enabled' => true, 'subject' => 'Dovolená', 'start' => '2026-09-10', 'end' => null]);
    $adapter->setAutoresponder($mailbox, ['enabled' => true, 'subject' => 'Mimo kancelář', 'text' => 'Ozvu se', 'start' => '2026-09-12', 'end' => '2026-09-20']);
    expect($params('mail_user_update'))->toMatchArray(['autoresponder' => 'y', 'autoresponder_subject' => 'Mimo kancelář', 'autoresponder_start_date' => '2026-09-12 00:00:00', 'autoresponder_end_date' => '2026-09-20 23:59:59']);

    expect($adapter->spamPolicies($domain))->toBe([['remote_id' => '1', 'name' => 'Normal'], ['remote_id' => '3', 'name' => 'Wants all spam']]);
    expect($adapter->mailboxSpamPolicy($mailbox))->toBe(['policy_id' => '3', 'policy' => 'Wants all spam']);
    expect($adapter->listSpamLists($domain))->toBe([['remote_id' => '70', 'kind' => 'whitelist', 'address' => 'partner@example.com', 'active' => true]]);
    $adapter->addSpamListEntry($domain, 'blacklist', 'spam@example.net');
    expect($params('mail_spamfilter_blacklist_add'))->toMatchArray(['wb' => 'B', 'rid' => 61, 'email' => 'spam@example.net']);

    expect($adapter->listFilters($mailbox)[0])->toMatchArray(['remote_id' => '80', 'name' => 'Newsletters', 'source' => 'Subject', 'op' => 'contains', 'term' => 'newsletter', 'action' => 'move', 'target' => 'Newsletters']);
    $adapter->createFilter($mailbox, ['name' => 'Faktury', 'source' => 'From', 'op' => 'contains', 'term' => 'fakturace@', 'action' => 'move', 'target' => 'Faktury']);
    expect($params('mail_user_filter_add'))->toMatchArray(['mailuser_id' => 21, 'rulename' => 'Faktury', 'searchterm' => 'fakturace@', 'target' => 'Faktury']);

    expect($adapter->listMailingLists($domain))->toBe([['remote_id' => '90', 'name' => 'novinky', 'email' => 'novinky@shop.cz', 'active' => true]]);
    expect($adapter->createMailingList($domain, ['name' => 'akce', 'email' => 'jana@shop.cz', 'password' => 'Correct-Horse-Battery-9'])->ref?->remoteId)->toBe('91');

    expect($adapter->listFetchmail($domain)[0])->toMatchArray(['remote_id' => '50', 'type' => 'imapssl', 'host' => 'imap.seznam.cz', 'user' => 'jana', 'destination' => 'jana@shop.cz']);
    $adapter->createFetchmail($domain, ['type' => 'pop3ssl', 'host' => 'pop.example.com', 'user' => 'old', 'password' => 'pw', 'destination' => 'jana@shop.cz', 'delete' => true]);
    expect($params('mail_fetchmail_add'))->toMatchArray(['type' => 'pop3ssl', 'source_server' => 'pop.example.com', 'source_username' => 'old', 'source_delete' => 'y', 'destination' => 'jana@shop.cz']);

    $backups = $adapter->listMailboxBackups($domain);
    expect($backups)->toHaveCount(1)->and($backups[0])->toMatchArray(['remote_id' => '7', 'mailbox' => 'jana@shop.cz', 'size_bytes' => 1048576]);
    expect($adapter->backupMailbox($mailbox)->isAsync())->toBeTrue();
    expect(collect($calls)->last(fn ($c) => $c[0] === 'mail_user_backup')[1])->toMatchArray(['primary_id' => 21, 'action_type' => 'backup']);
    $adapter->restoreMailbox($mailbox, '7');
    expect(collect($calls)->last(fn ($c) => $c[0] === 'mail_user_backup')[1])->toMatchArray(['action_type' => 'restore', 'backup_id' => 7]);

    expect($adapter->mailboxUsage($domain))->toBe([['mailbox' => 'jana@shop.cz', 'used_bytes' => 104857600, 'quota_bytes' => 2147483648]]);
    expect($adapter->webmailUrl($domain))->toBeNull();
    expect(json_encode(DB::table('provider_calls')->pluck('request')))->not->toContain('remote-secret');
});

it('keeps reverse proxies and default documents as a marked block in the site directives on ISPConfig, next to the customer directives and the security block', function () {
    $calls = [];
    $directives = "Header set X-Test \"1\"\n".SecurityRules::BEGIN." {\"deny\":[\"10.0.0.1\"]}\nRequire not ip 10.0.0.1\n".SecurityRules::END."\n";
    $updates = [];
    ispToolsFake($calls, [
        'server_get' => ['web' => ['server_type' => 'apache']],
        'sites_web_domain_get' => function () use (&$directives) {
            return ['domain_id' => 7, 'domain' => 'shop.cz', 'server_id' => 1, 'apache_directives' => $directives, 'nginx_directives' => ''];
        },
        'sites_web_domain_update' => function (array $body) use (&$updates, &$directives) {
            $updates[] = $body['params'];
            $directives = (string) $body['params']['apache_directives'];

            return true;
        },
    ]);
    $adapter = ispToolsAdapter();
    $ref = new ResourceRef('web_domain', '7', '1', ['client_id' => 3, 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7'], 'srv_tools');

    expect($adapter->siteFeatures())->toMatchArray(['proxy' => true, 'default_docs' => true])->and($adapter->listProxies($ref))->toBe([])->and($adapter->defaultDocuments($ref))->toBe([]);

    // a proxy: the block is appended, the customer directive and the security block stay, Apache gets ProxyPass lines
    $adapter->createProxy($ref, ['name' => 'API', 'path' => 'api', 'target' => 'http://127.0.0.1:3000/', 'cache' => false]);
    expect($updates)->toHaveCount(1)->and($directives)->toStartWith("Header set X-Test \"1\"\n".SecurityRules::BEGIN)->toContain(SecurityRules::END."\n".ManagedDirectives::BEGIN.' {"proxies":[{"name":"api","path":"/api","target":"http://127.0.0.1:3000","cache":false}],"index":[]}')
        ->toContain("ProxyPreserveHost On\nProxyRequests Off\nProxyPass /api/ http://127.0.0.1:3000/\nProxyPassReverse /api/ http://127.0.0.1:3000/\n".ManagedDirectives::END);
    expect($adapter->listProxies($ref))->toBe([['remote_id' => 'api', 'name' => 'api', 'path' => '/api', 'target' => 'http://127.0.0.1:3000', 'enabled' => true, 'cache' => false]]);
    // the same proxy again changes nothing; a bad upstream is refused before any write
    expect($adapter->createProxy($ref, ['name' => 'api', 'path' => '/api/', 'target' => 'http://127.0.0.1:3000'])->alreadyExisted)->toBeTrue()->and($updates)->toHaveCount(1);
    expect(fn () => $adapter->createProxy($ref, ['name' => 'ftp', 'target' => 'ftp://nope']))->toThrow(ProviderException::class);

    // default documents join the same block; an unchanged list writes nothing
    $adapter->setDefaultDocuments($ref, ['app.php', 'index.php', '../etc/passwd', 'app.php']);
    expect($updates)->toHaveCount(2)->and($directives)->toContain("DirectoryIndex app.php index.php standard_index.html\nProxyPreserveHost On")->and($adapter->defaultDocuments($ref))->toBe(['app.php', 'index.php']);
    expect($adapter->setDefaultDocuments($ref, ['app.php', 'index.php'])->completed)->toBeTrue()->and($updates)->toHaveCount(2);

    // the customer's own directives are edited without the managed blocks and written back around them
    expect($adapter->siteSettings($ref)['directives'])->toBe(['apache' => 'Header set X-Test "1"', 'nginx' => '']);
    $adapter->setDirectives($ref, 'apache', "Header set X-Test \"2\"\nOptions -Indexes");
    expect($directives)->toStartWith("Header set X-Test \"2\"\nOptions -Indexes\n".SecurityRules::BEGIN)->toContain('DirectoryIndex app.php index.php')->toContain('ProxyPass /api/');

    // removing the proxy leaves the index; clearing the index removes the whole block
    expect($adapter->deleteProxy($ref, 'api')->completed)->toBeFalse()->and($directives)->toContain('DirectoryIndex app.php index.php')->not->toContain('ProxyPass')->and($adapter->listProxies($ref))->toBe([]);
    expect($adapter->deleteProxy($ref, 'api')->alreadyExisted)->toBeTrue();
    $adapter->setDefaultDocuments($ref, []);
    expect($directives)->toBe("Header set X-Test \"2\"\nOptions -Indexes\n".SecurityRules::BEGIN." {\"deny\":[\"10.0.0.1\"]}\nRequire not ip 10.0.0.1\n".SecurityRules::END)->not->toContain(ManagedDirectives::BEGIN);

    // the nginx rendering of the same state
    $nginx = ManagedDirectives::render(['proxies' => [['name' => 'api', 'path' => '/api', 'target' => 'http://127.0.0.1:3000', 'cache' => false]], 'index' => ['app.php']], 'nginx');
    expect($nginx)->toContain("index app.php standard_index.html;\nlocation /api/ {\n    proxy_pass http://127.0.0.1:3000/;")->toContain('proxy_set_header Host $host;')->and(ManagedDirectives::parse($nginx)['proxies'][0]['path'])->toBe('/api');
    expect(ManagedDirectives::render(['proxies' => [], 'index' => []], 'apache'))->toBe('');
});
