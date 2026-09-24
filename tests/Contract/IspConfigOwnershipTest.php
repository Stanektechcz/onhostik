<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

/*
 * An id from a customer is not proof that the thing is theirs.
 *
 * ISPConfig's remote API is reached through ONE administrator session per instance, and it does not ask whose record
 * a `primary_id` is — the adapter says so itself, in the comments of the methods that do guard ("a mail domain's id
 * among web sites is a stranger's site"). Nine of the adapter's eleven id-taking methods therefore look the id up in
 * the site's OWN listing before touching the panel, and refuse what they do not find.
 *
 * Three did not, and the platform hands them the customer's parameter after checking only its SHAPE
 * (`ServiceService`: `/^[A-Za-z0-9:_.-]{1,120}$/`). On a shared node, where ids are consecutive integers, that is a
 * cross-tenant compromise:
 *
 *  - `setShellKey` — put your own SSH key on a neighbour's shell user and read their site's files, or send an empty
 *    key and lock them out of their own;
 *  - `setDbUserPassword` — reset a neighbour's MySQL password (and with it their site);
 *  - `deleteDbUser` — the lookup exists but only feeds the "still owns databases" conflict, so an id that is NOT
 *    ours passes the `!== null` test and the user is deleted anyway.
 *
 * These tests drive the adapter with a panel that answers truthfully: the site owns one of each, and the ids the
 * test sends belong to the neighbour. Nothing may reach the panel for those.
 */

function ispOwnAdapter(): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web', 'mail'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance);
}

/** The panel as it really answers: our site (web 7) owns shell user 11 and db user 21. Ids 12/22 are the neighbour's. */
function ispOwnFake(array &$calls): void
{
    Http::fake(function ($request) use (&$calls) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = [$function, $request->data()];
        $answer = match ($function) {
            'login' => 'sess-own',
            'sites_web_domain_get' => ['domain_id' => 7, 'domain' => 'shop.cz', 'sys_groupid' => 3, 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7'],
            'sites_shell_user_get' => [['shell_user_id' => 11, 'username' => 'web7-ssh', 'ssh_rsa' => '', 'chroot' => 'jailkit', 'active' => 'y']],
            'sites_database_get' => [['database_id' => 5, 'database_name' => 'c3shop', 'database_user_id' => 21]],
            'sites_database_user_get' => ['database_user_id' => 21, 'database_user' => 'c3shop'],
            default => true,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

/** The site the customer really has. */
function ispOwnSite(): ResourceRef
{
    return new ResourceRef('web_domain', '7', '1', ['client_id' => 3, 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7'], 'srv_own');
}

it('refuses to put a shell key on a shell user that is not this site', function () {
    $calls = [];
    ispOwnFake($calls);

    expect(fn () => ispOwnAdapter()->setShellKey(ispOwnSite(), '12', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIexample'))
        ->toThrow(ProviderException::class);
    expect(array_column($calls, 0))->not->toContain('sites_shell_user_update'); // the panel is never asked
});

it('still sets the key of the shell user the site owns', function () {
    $calls = [];
    ispOwnFake($calls);

    ispOwnAdapter()->setShellKey(ispOwnSite(), '11', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIexample');

    expect(array_column($calls, 0))->toContain('sites_shell_user_update');
});

it('refuses to change the password of a database user that is not this site', function () {
    $calls = [];
    ispOwnFake($calls);

    expect(fn () => ispOwnAdapter()->setDbUserPassword(ispOwnSite(), '22', 'a-password-the-neighbour-never-chose'))
        ->toThrow(ProviderException::class);
    expect(array_column($calls, 0))->not->toContain('sites_database_user_update');
});

it('still changes the password of the database user the site owns', function () {
    $calls = [];
    ispOwnFake($calls);

    ispOwnAdapter()->setDbUserPassword(ispOwnSite(), '21', 'a-password-of-our-own');

    expect(array_column($calls, 0))->toContain('sites_database_user_update');
});

it('refuses to delete a database user that is not this site', function () {
    $calls = [];
    ispOwnFake($calls);

    expect(fn () => ispOwnAdapter()->deleteDbUser(ispOwnSite(), '22'))
        ->toThrow(ProviderException::class);
    expect(array_column($calls, 0))->not->toContain('sites_database_user_delete');
});

it('keeps refusing to delete a database user of ours that still owns databases', function () {
    $calls = [];
    ispOwnFake($calls);

    expect(fn () => ispOwnAdapter()->deleteDbUser(ispOwnSite(), '21'))
        ->toThrow(ProviderException::class, 'c3shop'); // the conflict that already existed, unchanged
    expect(array_column($calls, 0))->not->toContain('sites_database_user_delete');
});
