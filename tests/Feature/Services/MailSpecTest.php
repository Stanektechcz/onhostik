<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;

/*
 * Declarative spec of a mail domain (audit §5g-3): forwards, the catch-all and aliases as one document — GET reads
 * them from the mail node, PUT converges: only the rows that differ become actions (one per forward or alias to add
 * or remove, one catch-all change), everything else is reported unchanged.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('reads the routing of a mail domain and converges forwards, catch-all and aliases through ordinary actions', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);
    $forwards = [['forwarding_id' => 31, 'source' => 'info@shop.cz', 'destination' => 'jana@gmail.com', 'active' => 'y']];
    $aliases = [['forwarding_id' => 51, 'source' => 'obchod@shop.cz', 'destination' => 'info@shop.cz', 'active' => 'y']];
    $catchall = [];
    $calls = [];
    Http::fake(function ($request) use (&$forwards, &$aliases, &$catchall, &$calls) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $data = $request->data();
        $calls[] = [$function, $data];
        $answer = match ($function) {
            'login' => 'sess-mail',
            'logout' => true,
            'monitor_jobqueue_count' => 0,
            'mail_forward_get' => $forwards,
            'mail_alias_get' => $aliases,
            'mail_catchall_get' => $catchall,
            'mail_forward_add' => (function () use (&$forwards, $data) {
                $forwards[] = ['forwarding_id' => 32, 'source' => $data['params']['source'], 'destination' => $data['params']['destination'], 'active' => 'y'];

                return 32;
            })(),
            'mail_forward_delete' => (function () use (&$forwards, $data) {
                $forwards = array_values(array_filter($forwards, fn ($f) => $f['forwarding_id'] !== (int) $data['primary_id']));

                return true;
            })(),
            'mail_alias_add' => (function () use (&$aliases, $data) {
                $aliases[] = ['forwarding_id' => 52, 'source' => $data['params']['source'], 'destination' => $data['params']['destination'], 'active' => 'y'];

                return 52;
            })(),
            'mail_catchall_add' => (function () use (&$catchall, $data) {
                $catchall = [['forwarding_id' => 40, 'source' => '@shop.cz', 'destination' => $data['params']['destination'], 'active' => 'y']];

                return 40;
            })(),
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
    $this->actingAs($user, 'sanctum');

    $spec = $this->getJson("/v1/services/{$service->id}/spec")->assertOk()->json('data');
    expect($spec['forwards'])->toBe([['source' => 'info@shop.cz', 'destination' => 'jana@gmail.com', 'remote_id' => '31']])->and($spec['catchall'])->toBe('')
        ->and($spec['aliases'])->toBe([['source' => 'obchod@shop.cz', 'destination' => 'info@shop.cz', 'remote_id' => '51']])->and($spec['features'])->toBe(['forwards', 'catchall', 'aliases', 'mailboxes', 'autoresponders', 'spam']);

    // converge: the existing forward is replaced by another, the catch-all appears, one alias is added next to the existing one
    $this->withHeader('Idempotency-Key', 'm-spec-bad')->putJson("/v1/services/{$service->id}/spec", ['spec' => ['php' => '8.3']])->assertStatus(422)->assertJsonPath('error', 'spec_section_unknown');
    $this->flushHeaders();
    $result = $this->withHeader('Idempotency-Key', 'm-spec-1')->putJson("/v1/services/{$service->id}/spec", ['spec' => [
        'forwards' => [['source' => 'Info@shop.cz', 'destination' => 'petr@gmail.com']],
        'catchall' => 'jana@shop.cz',
        'aliases' => [['source' => 'obchod@shop.cz', 'destination' => 'info@shop.cz'], ['source' => 'prodej@shop.cz', 'destination' => 'info@shop.cz']],
    ]])->assertOk()->json();
    $this->flushHeaders();
    expect(collect($result['operations'])->pluck('action')->all())->toBe(['forward.delete', 'forward.create', 'catchall.set', 'alias.create'])->and($result['unchanged'])->toBe([])->and($result['skipped'])->toBe([]);
    foreach ($result['operations'] as $op) {
        expect(driveOperation(Operation::query()->findOrFail($op['operation_id']))->state)->toBe(Operation::SUCCEEDED);
    }
    $added = collect($calls)->first(fn ($c) => $c[0] === 'mail_forward_add')[1]['params'];
    expect($added)->toMatchArray(['source' => 'info@shop.cz', 'destination' => 'petr@gmail.com'])->and(collect($calls)->first(fn ($c) => $c[0] === 'mail_forward_delete')[1]['primary_id'])->toBe(31)
        ->and(collect($calls)->first(fn ($c) => $c[0] === 'mail_catchall_add')[1]['params']['destination'])->toBe('jana@shop.cz')->and(collect($calls)->first(fn ($c) => $c[0] === 'mail_alias_add')[1]['params']['source'])->toBe('prodej@shop.cz');

    // the same document again: nothing to do; GET reflects the node
    $again = $this->withHeader('Idempotency-Key', 'm-spec-2')->putJson("/v1/services/{$service->id}/spec", ['spec' => ['forwards' => [['source' => 'info@shop.cz', 'destination' => 'petr@gmail.com']], 'catchall' => ['destination' => 'jana@shop.cz'], 'aliases' => [['source' => 'obchod@shop.cz', 'destination' => 'info@shop.cz'], ['source' => 'prodej@shop.cz', 'destination' => 'info@shop.cz']]]])->assertOk()->json();
    expect($again['operations'])->toBe([])->and($again['unchanged'])->toBe(['forwards', 'catchall', 'aliases']);
    $spec = $this->getJson("/v1/services/{$service->id}/spec")->assertOk()->json('data');
    expect($spec['forwards'][0])->toMatchArray(['source' => 'info@shop.cz', 'destination' => 'petr@gmail.com', 'remote_id' => '32'])->and($spec['catchall'])->toBe('jana@shop.cz')->and($spec['aliases'])->toHaveCount(2);
});

it('reads and converges mailboxes, autoresponders and spam policies; mailboxes are never deleted by a document and a new one needs a password', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);
    $users = [
        21 => ['mailuser_id' => 21, 'email' => 'jana@shop.cz', 'name' => 'Jana', 'quota' => 2048 * 1048576, 'postfix' => 'y', 'disableimap' => 'n', 'autoresponder' => 'n', 'autoresponder_subject' => '', 'autoresponder_text' => '', 'autoresponder_start_date' => '0000-00-00 00:00:00', 'autoresponder_end_date' => '0000-00-00 00:00:00'],
        22 => ['mailuser_id' => 22, 'email' => 'petr@shop.cz', 'name' => 'Petr', 'quota' => 1024 * 1048576, 'postfix' => 'y', 'disableimap' => 'n', 'autoresponder' => 'y', 'autoresponder_subject' => 'Dovolená', 'autoresponder_text' => 'Jsem pryč.', 'autoresponder_start_date' => '2026-09-01 00:00:00', 'autoresponder_end_date' => '2026-09-14 00:00:00'],
    ];
    $filters = ['petr@shop.cz' => ['id' => 7, 'email' => 'petr@shop.cz', 'policy_id' => 5]];
    $calls = [];
    Http::fake(function ($request) use (&$users, &$filters, &$calls) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $data = $request->data();
        $calls[] = [$function, $data];
        $answer = match ($function) {
            'login' => 'sess-mail2', 'logout' => true, 'monitor_jobqueue_count' => 0,
            'mail_forward_get', 'mail_alias_get', 'mail_catchall_get' => [],
            'mail_user_get' => is_array($data['primary_id'] ?? null)
                ? array_values(array_filter($users, fn ($u) => str_starts_with((string) ($data['primary_id']['email'] ?? '%'), '%') ? str_ends_with($u['email'], '@shop.cz') : $u['email'] === ($data['primary_id']['email'] ?? '')))
                : ($users[(int) ($data['primary_id'] ?? 0)] ?? []),
            'mail_user_add' => (function () use (&$users, $data) {
                $users[23] = ['mailuser_id' => 23, 'email' => $data['params']['email'], 'name' => $data['params']['name'], 'quota' => (int) $data['params']['quota'], 'postfix' => 'y', 'disableimap' => 'n', 'autoresponder' => 'n', 'autoresponder_subject' => '', 'autoresponder_text' => '', 'autoresponder_start_date' => '0000-00-00 00:00:00', 'autoresponder_end_date' => '0000-00-00 00:00:00'];

                return 23;
            })(),
            'mail_user_update' => (function () use (&$users, $data) {
                $id = (int) $data['primary_id'];
                foreach ((array) $data['params'] as $k => $v) {
                    if ($k === 'quota') {
                        $users[$id]['quota'] = (int) $v;
                    } elseif ($k === 'name') {
                        $users[$id]['name'] = $v;
                    } elseif ($k === 'autoresponder') {
                        $users[$id]['autoresponder'] = $v;
                    } elseif (str_starts_with($k, 'autoresponder_')) {
                        $users[$id][$k] = $v;
                    }
                }

                return true;
            })(),
            'mail_policy_get' => [['id' => 5, 'policy_name' => 'Normal'], ['id' => 6, 'policy_name' => 'Strict']],
            'mail_spamfilter_user_get' => isset($filters[$data['primary_id']['email'] ?? '']) ? [$filters[$data['primary_id']['email']]] : [],
            'mail_spamfilter_user_add' => (function () use (&$filters, $data) {
                $filters[$data['params']['email']] = ['id' => 8, 'email' => $data['params']['email'], 'policy_id' => (int) $data['params']['policy_id']];

                return 8;
            })(),
            'mail_spamfilter_user_update' => (function () use (&$filters, $data) {
                foreach ($filters as &$f) {
                    if ((int) $f['id'] === (int) $data['primary_id']) {
                        $f['policy_id'] = (int) $data['params']['policy_id'];
                    }
                }

                return true;
            })(),
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
    $this->actingAs($user, 'sanctum');

    $spec = $this->getJson("/v1/services/{$service->id}/spec")->assertOk()->json('data');
    expect(array_column($spec['mailboxes'], 'address'))->toBe(['jana@shop.cz', 'petr@shop.cz'])->and($spec['mailboxes'][0])->toMatchArray(['name' => 'Jana', 'quota_mb' => 2048, 'remote_id' => '21'])
        ->and($spec['autoresponders']['petr@shop.cz'])->toMatchArray(['enabled' => true, 'subject' => 'Dovolená', 'text' => 'Jsem pryč.', 'start' => '2026-09-01', 'end' => '2026-09-14'])->and($spec['autoresponders']['jana@shop.cz']['enabled'])->toBeFalse()
        ->and($spec['spam']['policies'])->toBe([['id' => '5', 'name' => 'Normal'], ['id' => '6', 'name' => 'Strict']])->and($spec['spam']['mailboxes'])->toBe(['jana@shop.cz' => null, 'petr@shop.cz' => '5'])
        ->and($spec['features'])->toBe(['forwards', 'catchall', 'aliases', 'mailboxes', 'autoresponders', 'spam']);

    // converge: Jana gets a bigger quota, a new mailbox appears (with its password), one is asked for without a password, Petr's autoresponder goes off, Jana gets the strict policy by name
    $result = $this->withHeader('Idempotency-Key', 'm-spec-3')->putJson("/v1/services/{$service->id}/spec", ['spec' => [
        'mailboxes' => [['address' => 'jana@shop.cz', 'quota_mb' => 4096], ['address' => 'obchod@shop.cz', 'name' => 'Obchod', 'quota_mb' => 1024, 'password' => 'Velmi-Tajne-Heslo-2026'], ['address' => 'nova@shop.cz']],
        'autoresponders' => ['petr@shop.cz' => ['enabled' => false], 'jana@shop.cz' => ['enabled' => false], 'nikdo@shop.cz' => ['enabled' => true]],
        'spam' => ['jana@shop.cz' => 'Strict', 'petr@shop.cz' => 5, 'obchod@shop.cz' => 'Unknown policy'],
    ]])->assertOk()->json();
    $this->flushHeaders();
    expect(collect($result['operations'])->pluck('action')->all())->toBe(['mailbox.update', 'mailbox.create', 'autoresponder.set', 'spam.policy'])->and($result['unchanged'])->toBe([])
        ->and($result['skipped'])->toBe([['section' => 'mailboxes', 'reason' => 'password_required:nova@shop.cz'], ['section' => 'mailboxes', 'reason' => 'mailbox_extra:petr@shop.cz'], ['section' => 'autoresponders', 'reason' => 'mailbox_unknown:nikdo@shop.cz'], ['section' => 'spam', 'reason' => 'mailbox_unknown:obchod@shop.cz']]);
    foreach ($result['operations'] as $op) {
        expect(driveOperation(Operation::query()->findOrFail($op['operation_id']))->state)->toBe(Operation::SUCCEEDED);
    }
    expect(collect($calls)->first(fn ($c) => $c[0] === 'mail_user_update' && (int) $c[1]['primary_id'] === 21)[1]['params']['quota'])->toBe(4096 * 1048576)
        ->and(collect($calls)->first(fn ($c) => $c[0] === 'mail_user_add')[1]['params'])->toMatchArray(['email' => 'obchod@shop.cz', 'name' => 'Obchod', 'password' => 'Velmi-Tajne-Heslo-2026'])
        ->and(collect($calls)->first(fn ($c) => $c[0] === 'mail_user_update' && (int) $c[1]['primary_id'] === 22)[1]['params']['autoresponder'])->toBe('n')
        ->and(collect($calls)->first(fn ($c) => $c[0] === 'mail_spamfilter_user_add')[1]['params'])->toMatchArray(['email' => 'jana@shop.cz', 'policy_id' => 6]);
    expect(json_encode($calls))->not->toContain('Velmi-Tajne-Heslo-2026"}]}]'); // the password reaches the panel once, in the create call only

    // the same document again (with the mailbox now existing and no password given): nothing to do
    $again = $this->withHeader('Idempotency-Key', 'm-spec-4')->putJson("/v1/services/{$service->id}/spec", ['spec' => ['mailboxes' => [['address' => 'jana@shop.cz', 'quota_mb' => 4096], ['address' => 'obchod@shop.cz', 'name' => 'Obchod', 'quota_mb' => 1024], ['address' => 'petr@shop.cz']], 'autoresponders' => ['petr@shop.cz' => ['enabled' => false]], 'spam' => ['jana@shop.cz' => '6']]])->assertOk()->json();
    expect($again['operations'])->toBe([])->and($again['unchanged'])->toBe(['mailboxes', 'autoresponders', 'spam'])->and($again['skipped'])->toBe([]);
});
