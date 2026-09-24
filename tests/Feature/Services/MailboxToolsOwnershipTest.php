<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\DomainError;

/*
 * A mailbox TOOL is named by the mailbox's id too, and nothing measured that id against the service.
 *
 * `MailboxOwnershipTest` closed `mailbox.update`, `mailbox.delete` and `alias.delete`. The tools that act on one
 * mailbox — its autoresponder, its spam policy, its filters, its backups — still built the mailbox reference straight
 * from `remote_id` (`ServiceFeatures::mailboxRef`), and so did the listings that read them. ISPConfig answers one
 * administrator session and never asks whose `mailuser_id` it was handed, so on the shared mail server — where the
 * historical customers' mailboxes live, which the platform must never touch — a neighbour's mailbox was one integer
 * away: a `delete` filter on it, an autoresponder, a spam policy, an old backup restored over what it holds now, and
 * all of its settings readable through the resource listing. Fetchmail could deliver into any address.
 */

beforeEach(fn () => Http::preventStrayRequests());

/**
 * ISPConfig where our mail domain shop.cz (id 5) holds exactly one mailbox, 31; mailbox 32 is a historical customer's
 * (cizi.cz) on the same server — with a filter, a backup and an autoresponder of its own.
 *
 * @param  list<string>  $calls
 */
function mbxToolsFake(array &$calls): void
{
    $ours = ['mailuser_id' => 31, 'email' => 'info@shop.cz', 'name' => 'Info', 'quota' => 2147483648, 'disablesmtp' => 'n', 'autoresponder' => 'n'];
    $theirs = ['mailuser_id' => 32, 'email' => 'reditel@cizi.cz', 'name' => 'Ředitel', 'quota' => 2147483648, 'disablesmtp' => 'n', 'autoresponder' => 'y', 'autoresponder_subject' => 'Tajné', 'autoresponder_text' => 'Jsem na jednání s bankou.'];
    Http::fake(function (Request $request) use (&$calls, $ours, $theirs) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $function;
        $primary = $request->data()['primary_id'] ?? null;
        $answer = match ($function) {
            'login' => 'sess-mbx-tools',
            'logout' => true,
            'monitor_jobqueue_count' => 0,
            'mail_domain_get' => ['domain_id' => 5, 'domain' => 'shop.cz', 'active' => 'y'],
            // a listing is asked with a filter array (`email LIKE %@shop.cz`), one mailbox with its integer id
            'mail_user_get' => is_array($primary) ? [$ours] : ((int) $primary === 32 ? $theirs : ((int) $primary === 31 ? $ours : [])),
            'mail_user_filter_get' => (int) data_get($primary, 'mailuser_id') === 32 ? [['filter_id' => 90, 'rulename' => 'Banka', 'source' => 'From', 'op' => 'contains', 'searchterm' => 'banka', 'action' => 'move', 'target' => 'Banka', 'active' => 'y']] : [],
            'mail_user_backup_list' => (int) $primary === 32 ? [['backup_id' => 700, 'tstamp' => 1758000000, 'filesize' => 1024]] : [['backup_id' => 600, 'tstamp' => 1758000000, 'filesize' => 1024]],
            'mail_spamfilter_user_get' => [],
            'mail_policy_get' => [['id' => 1, 'policy_name' => 'Normal'], ['id' => 6, 'policy_name' => 'Nevyfiltrovat']],
            'mail_fetchmail_get' => [],
            default => 101,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

/** The panel functions that write something. */
const MBX_TOOLS_WRITES = ['mail_user_update', 'mail_user_filter_add', 'mail_user_filter_delete', 'mail_user_backup', 'mail_spamfilter_user_add', 'mail_spamfilter_user_update', 'mail_fetchmail_add'];

dataset('mbxToolsStrangerActions', [
    'autoresponder on a stranger\'s mailbox' => ['autoresponder.set', ['remote_id' => '32', 'enabled' => true, 'subject' => 'Pryč', 'text' => 'Nejsem tu.']],
    'spam policy of a stranger\'s mailbox' => ['spam.policy', ['remote_id' => '32', 'policy_id' => '6']],
    'a delete filter on a stranger\'s mailbox' => ['filter.create', ['remote_id' => '32', 'name' => 'vše pryč', 'source' => 'Subject', 'op' => 'contains', 'term' => 'a', 'action' => 'delete']],
    'removing a stranger\'s filter' => ['filter.delete', ['remote_id' => '90', 'mailbox_id' => '32']],
    'restoring an old backup over a stranger\'s mailbox' => ['mailbox.restore', ['remote_id' => '32', 'backup_id' => '700']],
    // `mailbox.backup` is guarded alike, but ISPConfig cannot back a mailbox up on demand, so the action is not offered here
]);

it('refuses a mailbox tool on a mailbox that is not the service\'s, before the panel is written', function (string $action, array $params) {
    $calls = [];
    mbxToolsFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, $action, $this->contextFor($user, $org), 'mbx-tools-'.$action, $params));

    expect($operation->state)->not->toBe(Operation::SUCCEEDED)
        ->and((string) data_get($operation->error, 'message', ''))->toContain('nepatří')
        ->and(array_values(array_intersect($calls, MBX_TOOLS_WRITES)))->toBe([]); // the panel is never asked to change anything
})->with('mbxToolsStrangerActions');

it('still sets the autoresponder, the spam policy and a filter of the mailbox the service really has', function () {
    $calls = [];
    mbxToolsFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);
    $services = app(ServiceService::class);

    foreach ([
        ['autoresponder.set', ['remote_id' => '31', 'enabled' => true, 'subject' => 'Pryč', 'text' => 'Nejsem tu.'], 'mail_user_update'],
        ['spam.policy', ['remote_id' => '31', 'policy_id' => '6'], 'mail_spamfilter_user_add'],
        ['filter.create', ['remote_id' => '31', 'name' => 'Faktury', 'source' => 'Subject', 'op' => 'contains', 'term' => 'faktura', 'action' => 'move', 'target' => 'Faktury'], 'mail_user_filter_add'],
        ['mailbox.restore', ['remote_id' => '31', 'backup_id' => '600'], 'mail_user_backup'],
    ] as [$action, $params, $write]) {
        $operation = driveOperation($services->requestAction($service->fresh(), $action, $this->contextFor($user, $org), 'mbx-tools-own-'.$action, $params));
        expect($operation->state)->not->toBe(Operation::FAILED, $action.': '.(string) data_get($operation->error, 'message', ''))
            ->and($calls)->toContain($write);
    }
});

it('does not let fetchmail deliver into an address outside the service\'s domains', function () {
    $calls = [];
    mbxToolsFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    expect(fn () => app(ServiceService::class)->requestAction($service, 'fetchmail.create', $this->contextFor($user, $org), 'mbx-tools-fm-1', ['type' => 'imapssl', 'host' => 'imap.gmail.com', 'user' => 'me@gmail.com', 'password' => 'Zvolene-Heslo-2026!', 'destination' => 'reditel@cizi.cz']))
        ->toThrow(DomainError::class);
    expect($calls)->not->toContain('mail_fetchmail_add');
});

it('does not let fetchmail deliver into an address of the domain that is no mailbox of the service', function () {
    $calls = [];
    mbxToolsFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'fetchmail.create', $this->contextFor($user, $org), 'mbx-tools-fm-2', ['type' => 'imapssl', 'host' => 'imap.gmail.com', 'user' => 'me@gmail.com', 'password' => 'Zvolene-Heslo-2026!', 'destination' => 'nikdo@shop.cz']));

    expect($operation->state)->not->toBe(Operation::SUCCEEDED)
        ->and($calls)->not->toContain('mail_fetchmail_add');
});

it('still fetches mail into the service\'s own mailbox', function () {
    $calls = [];
    mbxToolsFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'fetchmail.create', $this->contextFor($user, $org), 'mbx-tools-fm-3', ['type' => 'imapssl', 'host' => 'imap.gmail.com', 'user' => 'me@gmail.com', 'password' => 'Zvolene-Heslo-2026!', 'destination' => 'Info@shop.cz']));

    expect($operation->state)->not->toBe(Operation::FAILED, (string) data_get($operation->error, 'message', ''))
        ->and($calls)->toContain('mail_fetchmail_add');
});

it('reads a stranger\'s autoresponder, filters and spam policy as not found, and its own as they are', function () {
    $calls = [];
    mbxToolsFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);
    $this->actingAs($user, 'sanctum');

    foreach (['mail_autoresponder', 'mail_filters', 'mail_spam'] as $kind) {
        $response = $this->getJson("/v1/services/{$service->id}/resources/{$kind}?fresh=1&remote_id=32");
        $response->assertStatus(404);
        expect($response->getContent())->not->toContain('banka')->not->toContain('Tajné')->not->toContain('cizi.cz');
    }

    $this->getJson("/v1/services/{$service->id}/resources/mail_autoresponder?fresh=1&remote_id=31")->assertOk()->assertJsonPath('data.enabled', false);
    $this->getJson("/v1/services/{$service->id}/resources/mail_filters?fresh=1&remote_id=31")->assertOk()->assertJsonPath('data', []);
    $this->getJson("/v1/services/{$service->id}/resources/mail_spam?fresh=1")->assertOk()->assertJsonPath('data.mailbox', null); // the policies alone name no mailbox
});
