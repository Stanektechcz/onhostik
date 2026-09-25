<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\MailboxBackupRetention;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

/*
 * Mailbox backup retention on ISPConfig (TASK-0024, owner decision 3). Mail plans sell `backup_days` (14 / 30) and no
 * code ever told the panel: `mail_user_add` went out without `backup_interval`/`backup_copies`, so the panel kept its
 * form default. The adapter now sends them on a new mailbox when the platform asks, and sets them on an existing one
 * only after proving the mailbox is the platform's own — the organisation's `onh_` client, its group on the mail domain
 * row and on the mailbox row, the address inside the domain. Historical mail on the same server is never written.
 */

function mbrContractAdapter(): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web', 'mail'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance);
}

/**
 * Our mail domain shop.cz (id 5) of client 3 (`onh_…`, group 7) with mailbox 31; mailbox 33 answers the same LIKE query
 * but carries another client's group, mailbox 34 is a stranger whose address only ends in "shop.cz".
 *
 * @param  list<array{0:string,1:array<string,mixed>}>  $calls
 * @param  array<string,mixed>  $overrides  function => answer
 */
function mbrContractFake(array &$calls, array $overrides = []): void
{
    $ours = ['mailuser_id' => 31, 'email' => 'info@shop.cz', 'name' => 'Info', 'password' => '$6$hash', 'quota' => 2147483648, 'autoresponder' => 'y', 'autoresponder_subject' => 'Dovolená', 'sys_groupid' => 7, 'sys_userid' => 1, 'backup_interval' => 'none', 'backup_copies' => 1];
    $foreignGroup = ['mailuser_id' => 33, 'email' => 'old@shop.cz', 'name' => 'Old', 'password' => '$6$x', 'sys_groupid' => 9, 'backup_interval' => 'none', 'backup_copies' => 1];
    $outside = ['mailuser_id' => 34, 'email' => 'boss@myshop.cz', 'name' => 'Boss', 'password' => '$6$y', 'sys_groupid' => 7, 'backup_interval' => 'none', 'backup_copies' => 1];
    $answers = array_merge([
        'client_get' => ['client_id' => 3, 'username' => 'onh_abc123', 'password' => '$6$c'],
        'client_get_groupid' => 7,
        'mail_domain_get' => ['domain_id' => 5, 'domain' => 'shop.cz', 'sys_groupid' => 7, 'active' => 'y'],
        'mail_user_get' => fn (array $body) => is_array($body['primary_id'] ?? null) ? [$ours, $foreignGroup, $outside] : match ((int) ($body['primary_id'] ?? 0)) {
            31 => $ours, 33 => $foreignGroup, 34 => $outside, default => [],
        },
        'mail_user_update' => true,
        'mail_user_add' => 41,
        'monitor_jobqueue_count' => 0,
    ], $overrides);
    Http::fake(function ($request) use (&$calls, $answers) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = [$function, $body];
        if ($function === 'login') {
            return Http::response(['code' => 'ok', 'message' => '', 'response' => 'sess-mbr']);
        }
        if (! array_key_exists($function, $answers)) {
            return Http::response(['code' => 'remote_fault', 'message' => "unexpected {$function}", 'response' => false]);
        }
        $answer = $answers[$function];

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer instanceof Closure ? $answer($body) : $answer]);
    });
}

/** @param  list<array{0:string,1:array<string,mixed>}>  $calls @return list<array<string,mixed>> */
function mbrContractBodies(array $calls, string $function): array
{
    return array_values(array_map(fn (array $c) => $c[1], array_filter($calls, fn (array $c) => $c[0] === $function)));
}

function mbrContractDomain(array $meta = ['client_id' => 3, 'domain' => 'shop.cz']): ResourceRef
{
    return new ResourceRef('mail_domain', '5', '1', $meta, 'svc-1');
}

it('sends a daily backup with the plan\'s copies on a new mailbox only when asked', function () {
    $calls = [];
    mbrContractFake($calls, ['mail_user_get' => []]);
    $adapter = mbrContractAdapter();

    $adapter->createMailbox(mbrContractDomain(), ['address' => 'nova@shop.cz', 'password' => 'Heslo-Nove-2026!', 'quota_mb' => 1024, 'backup_copies' => 14]);
    $adapter->createMailbox(mbrContractDomain(), ['address' => 'dalsi@shop.cz', 'password' => 'Heslo-Nove-2026!', 'quota_mb' => 1024]);

    [$with, $without] = mbrContractBodies($calls, 'mail_user_add');
    expect($with['params'])->toMatchArray(['email' => 'nova@shop.cz', 'backup_interval' => 'daily', 'backup_copies' => 14])
        ->and($without['params'])->not->toHaveKey('backup_interval')->not->toHaveKey('backup_copies');
});

it('lists the retention of the domain\'s mailboxes and marks what is not provably ours', function () {
    $calls = [];
    mbrContractFake($calls);
    $adapter = mbrContractAdapter();

    expect($adapter)->toBeInstanceOf(MailboxBackupRetention::class);
    $rows = collect($adapter->mailboxBackupRetention(mbrContractDomain()))->keyBy('remote_id');

    expect($rows->pluck('remote_id')->values()->all())->toBe(['31', '33', '34'])
        ->and($rows['31'])->toMatchArray(['address' => 'info@shop.cz', 'interval' => 'none', 'copies' => 1, 'owned' => true])
        ->and($rows['33']['owned'])->toBeFalse() // another client's group
        ->and($rows['34']['owned'])->toBeFalse() // an address that only ends in the domain's name
        ->and(mbrContractBodies($calls, 'mail_user_update'))->toBe([]);
});

it('sets the retention on our mailbox through the merged record, never sending the password back', function () {
    $calls = [];
    mbrContractFake($calls);
    $adapter = mbrContractAdapter();

    $result = $adapter->setMailboxBackupRetention(mbrContractDomain(), '31', 30);

    $update = mbrContractBodies($calls, 'mail_user_update');
    expect($update)->toHaveCount(1)
        ->and($update[0]['primary_id'])->toBe(31)
        ->and($update[0]['params'])->toMatchArray(['backup_interval' => 'daily', 'backup_copies' => 30, 'autoresponder' => 'y', 'autoresponder_subject' => 'Dovolená', 'email' => 'info@shop.cz'])
        ->and($update[0]['params'])->not->toHaveKey('password')->not->toHaveKey('sys_groupid')->not->toHaveKey('mailuser_id')
        ->and($result->isAsync())->toBeTrue()
        ->and($result->data)->toMatchArray(['copies' => 30]);
});

it('refuses to set the retention on a mailbox that is not provably ours, with no write', function (string $remoteId) {
    $calls = [];
    mbrContractFake($calls);

    expect(fn () => mbrContractAdapter()->setMailboxBackupRetention(mbrContractDomain(), $remoteId, 14))
        ->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::NOT_FOUND));
    expect(mbrContractBodies($calls, 'mail_user_update'))->toBe([]);
})->with(['another client\'s group' => '33', 'an address outside the domain' => '34', 'a mailbox that is not there' => '99', 'not a number' => '31 OR 1']);

it('refuses a copy count outside what a plan can sell', function (int $copies) {
    $calls = [];
    mbrContractFake($calls);

    expect(fn () => mbrContractAdapter()->setMailboxBackupRetention(mbrContractDomain(), '31', $copies))
        ->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::VALIDATION));
    expect(mbrContractBodies($calls, 'mail_user_update'))->toBe([]);
})->with([0, -1, 366]);

it('touches nothing in a mail domain it cannot prove is the platform\'s', function (array $overrides, ?array $meta, ProviderErrorCode $code) {
    $calls = [];
    mbrContractFake($calls, $overrides);
    $adapter = mbrContractAdapter();
    $domain = $meta === null ? mbrContractDomain() : mbrContractDomain($meta);

    expect(fn () => $adapter->mailboxBackupRetention($domain))->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe($code));
    expect(fn () => $adapter->setMailboxBackupRetention($domain, '31', 14))->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe($code));
    expect(mbrContractBodies($calls, 'mail_user_update'))->toBe([]);
})->with([
    'the domain carries another client\'s group' => [['mail_domain_get' => ['domain_id' => 5, 'domain' => 'shop.cz', 'sys_groupid' => 9]], null, ProviderErrorCode::CONFLICT],
    'the client is not one the platform made' => [['client_get' => ['client_id' => 3, 'username' => 'jan.novak']], null, ProviderErrorCode::CONFLICT],
    'the panel will not name the client\'s group' => [['client_get_groupid' => 0], null, ProviderErrorCode::CONFLICT],
    'the domain row is another domain' => [['mail_domain_get' => ['domain_id' => 5, 'domain' => 'jiny.cz', 'sys_groupid' => 7]], null, ProviderErrorCode::CONFLICT],
    'the binding names no client' => [[], ['domain' => 'shop.cz'], ProviderErrorCode::VALIDATION],
]);

it('proves a mail domain again when the same adapter is handed the same id under another name', function () {
    $calls = [];
    mbrContractFake($calls); // the panel's row 5 is shop.cz
    $adapter = mbrContractAdapter(); // one adapter for the whole queue worker (ProviderRegistry caches it)

    expect($adapter->mailboxBackupRetention(mbrContractDomain()))->toHaveCount(3);
    $other = mbrContractDomain(['client_id' => 3, 'domain' => 'jiny.cz']);

    expect(fn () => $adapter->mailboxBackupRetention($other))->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::CONFLICT));
    expect(fn () => $adapter->setMailboxBackupRetention($other, '31', 14))->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::CONFLICT));
    expect(mbrContractBodies($calls, 'mail_user_update'))->toBe([]);
});

it('keeps the backup settings when an autoresponder is switched on', function () {
    $calls = [];
    mbrContractFake($calls, ['mail_user_get' => ['mailuser_id' => 31, 'email' => 'info@shop.cz', 'name' => 'Info', 'password' => '$6$hash', 'sys_groupid' => 7, 'backup_interval' => 'daily', 'backup_copies' => 14, 'autoresponder' => 'n']]);

    mbrContractAdapter()->setAutoresponder(new ResourceRef('mailbox', '31', '1', ['client_id' => 3], 'svc-1'), ['enabled' => true, 'subject' => 'Pryč', 'text' => 'Nejsem tu.']);

    $update = mbrContractBodies($calls, 'mail_user_update');
    expect($update)->toHaveCount(1)
        ->and($update[0]['params'])->toMatchArray(['autoresponder' => 'y', 'autoresponder_subject' => 'Pryč', 'backup_interval' => 'daily', 'backup_copies' => 14, 'email' => 'info@shop.cz'])
        ->and($update[0]['params'])->not->toHaveKey('password');
});
