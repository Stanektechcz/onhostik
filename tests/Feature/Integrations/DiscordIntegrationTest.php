<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Integrations\Models\DiscordLink;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * Services driven from outside the panel: the Discord account link and /onhost slash commands (signed interactions,
 * confirmation buttons), action hooks (a signed URL that runs one action), Discord channel notifications as embeds,
 * and the assistant's service-management proposals that both the chat and Discord turn into confirm buttons.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

function discordSign(string $secretKey, string $body): array
{
    $timestamp = (string) time();
    $signature = sodium_crypto_sign_detached($timestamp.$body, $secretKey);

    return ['HTTP_X_SIGNATURE_ED25519' => bin2hex($signature), 'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp, 'CONTENT_TYPE' => 'application/json'];
}

function discordCommand(string $sub, array $options = [], string $uid = '4242', string $guild = '9001'): array
{
    return ['type' => 2, 'guild_id' => $guild, 'member' => ['user' => ['id' => $uid, 'username' => 'jana']], 'data' => ['name' => 'onhost', 'options' => [['name' => $sub, 'type' => 1, 'options' => array_map(fn ($k, $v) => ['name' => $k, 'value' => $v], array_keys($options), $options)]]]];
}

it('links a Discord account with a one-time code and drives the service with /onhost commands and confirmation buttons', function () {
    $keypair = sodium_crypto_sign_keypair();
    config()->set('onhost.discord.public_key', bin2hex(sodium_crypto_sign_publickey($keypair)));
    config()->set('onhost.discord.application_id', '123456789');
    $secret = sodium_crypto_sign_secretkey($keypair);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^ls -la/' => [0, "index.php\n"]]);
    Http::fake(function ($request) {
        if (! str_contains($request->url(), 'managed01.mgmt.test')) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);

        return match (true) {
            str_contains($q, 'table=backup') => Http::response(['data' => [['id' => 5, 'addtime' => now()->toDateTimeString(), 'size' => 1024, 'filename' => '/www/backup/site/shop.cz.tar.gz']]]),
            str_contains($q, 'GetSiteRunPath') => Http::response(['runPath' => '/']),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    $this->actingAs($user, 'sanctum');

    // 1. the panel: status, a link code
    $status = $this->getJson('/v1/integrations/discord')->assertOk()->json('data');
    expect($status['configured'])->toBeTrue()->and($status['links'])->toBe([])->and($status['invite_url'])->toContain('client_id=123456789');
    $code = $this->postJson('/v1/integrations/discord/link-code')->assertCreated()->json('code');
    expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');

    // 2. Discord: unsigned and badly signed interactions are refused, a ping is answered, the link code binds the account
    $this->call('POST', '/v1/integrations/discord/interactions', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['type' => 1]))->assertStatus(401);
    $ping = json_encode(['type' => 1]);
    $this->call('POST', '/v1/integrations/discord/interactions', [], [], [], discordSign($secret, $ping), $ping)->assertOk()->assertJsonPath('type', 1);
    $interact = function (array $payload) use ($secret) {
        $body = json_encode($payload);

        return $this->call('POST', '/v1/integrations/discord/interactions', [], [], [], discordSign($secret, $body), $body)->assertOk()->json();
    };
    $before = $interact(discordCommand('services'));
    expect($before['data']['content'])->toContain('/onhost link');
    $linked = $interact(discordCommand('link', ['code' => $code]));
    expect($linked['data']['content'])->toContain('Propojeno')->and($linked['data']['flags'])->toBe(64);
    expect(DiscordLink::query()->where('discord_user_id', '4242')->where('state', 'linked')->exists())->toBeTrue();
    expect($this->getJson('/v1/integrations/discord')->json('data.links.0.discord_username'))->toBe('jana');

    // 3. commands as the linked user: listing, status, a backup, a deploy that needs confirmation
    $services = $interact(discordCommand('services'));
    expect($services['data']['content'])->toContain('shop.cz')->toContain('webhosting')->not->toMatch('/aapanel|ispconfig/i');
    $st = $interact(discordCommand('status', ['service' => 'shop.cz']));
    expect($st['data']['content'])->toContain('shop.cz');
    $backup = $interact(discordCommand('backup', ['service' => 'shop']));
    expect($backup['data']['content'])->toContain('Spuštěno');
    $operation = Operation::query()->where('service_id', $service->id)->where('desired->action', 'backup')->orderByDesc('queued_at')->first();
    expect($operation)->not->toBeNull();
    driveOperation($operation);
    expect($operation->fresh()->state)->toBe(Operation::SUCCEEDED, json_encode($operation->fresh()->error));
    $restart = $interact(discordCommand('restart', ['service' => 'shop.cz']));
    expect($restart['data']['content'])->toContain('nerestartují'); // web hosting has no restart
    $missing = $interact(discordCommand('status', ['service' => 'nothing.cz']));
    expect($missing['data']['content'])->toContain('nenašel');

    // 4. the AI agent through Discord: a plain-language request becomes a button, the button runs the action once
    $ask = $interact(discordCommand('ask', ['question' => 'Zálohuj mi prosím shop.cz']));
    expect($ask['data']['content'])->toContain('Zálohovat shop.cz')->and($ask['data']['components'][0]['components'][0]['custom_id'])->toStartWith('act:');
    $customId = $ask['data']['components'][0]['components'][0]['custom_id'];
    $click = $interact(['type' => 3, 'member' => ['user' => ['id' => '4242', 'username' => 'jana']], 'data' => ['custom_id' => $customId]]);
    expect($click['type'])->toBe(7)->and($click['data']['content'])->toContain('Spuštěno')->and($click['data']['components'])->toBe([]);
    $again = $interact(['type' => 3, 'member' => ['user' => ['id' => '4242', 'username' => 'jana']], 'data' => ['custom_id' => $customId]]);
    expect($again['data']['content'])->toContain('neplatí');
    $stranger = $interact(['type' => 3, 'member' => ['user' => ['id' => '7777', 'username' => 'eve']], 'data' => ['custom_id' => 'act:nothing']]);
    expect($stranger['data']['content'])->not->toContain('Spuštěno');
    expect(Operation::query()->where('service_id', $service->id)->where('desired->action', 'backup')->count())->toBe(2);

    // 5. unlink from the panel; commands stop
    $link = DiscordLink::query()->where('discord_user_id', '4242')->firstOrFail();
    $this->deleteJson('/v1/integrations/discord/links/'.$link->id)->assertOk();
    expect($interact(discordCommand('services'))['data']['content'])->toContain('/onhost link');
    expect(DiscordService::commandDefinition()['name'])->toBe('onhost');
});

it('runs one predefined action through a signed action-hook URL and rejects bad or disabled tokens', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Http::fake(function ($request) {
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);

        return match (true) {
            str_contains($q, 'table=backup') => Http::response(['data' => []]),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/hooks/actions', ['service_id' => $service->id, 'name' => 'Záloha z Discordu', 'action' => 'terminate'])->assertUnprocessable();
    $created = $this->postJson('/v1/hooks/actions', ['service_id' => $service->id, 'name' => 'Záloha z Discordu', 'action' => 'backup', 'params' => ['kind' => 'manual']])->assertCreated()->json();
    expect($created['url'])->toContain('/v1/hooks/run/ahk_')->and($created['token'])->toStartWith('ahk_');
    expect($this->getJson('/v1/hooks/actions?service='.$service->id)->assertOk()->json('data.hooks.0.name'))->toBe('Záloha z Discordu');

    $this->post('/v1/hooks/run/ahk_'.str_repeat('x', 40))->assertNotFound();
    $run = $this->post('/v1/hooks/run/'.$created['token'])->assertAccepted()->json();
    expect($run['accepted'])->toBeTrue()->and($run['operation_id'])->toStartWith('op');
    expect(ActionHook::query()->where('id', $created['hook']['id'])->value('uses'))->toBe(1);
    $second = $this->post('/v1/hooks/run/'.$created['token'])->json();
    expect($second['accepted'])->toBeTrue()->and($second['operation_id'])->toBe($run['operation_id']); // a burst within ten seconds is one operation, not two

    ActionHook::query()->where('id', $created['hook']['id'])->update(['enabled' => false]);
    expect($this->post('/v1/hooks/run/'.$created['token'])->assertOk()->json('reason'))->toBe('hook_disabled');
    $this->deleteJson('/v1/hooks/actions/'.$created['hook']['id'])->assertOk();
    $this->post('/v1/hooks/run/'.$created['token'])->assertNotFound();
});

it('delivers platform events to a Discord channel webhook as embeds', function () {
    [$user, $org] = $this->customerWithOrganization();
    Http::fake(['discord.com/api/webhooks/*' => Http::response('', 204)]);
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/webhooks', ['url' => 'https://discord.com/api/webhooks/123/abcDEF_token', 'events' => ['*']])->assertCreated();
    app(OutboxPublisher::class)->publish(GenericEvent::of('monitoring.down', 'service', 'srv_x', ['url' => 'https://shop.cz/', 'error' => 'HTTP 503', 'notify' => true], $org->id));
    app(OutboxPublisher::class)->relayPending();
    app(WebhookDispatcher::class)->retryDue();
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'discord.com/api/webhooks/123')) {
            return false;
        }
        $body = $request->data();

        return ($body['username'] ?? '') === 'ONhost' && str_contains((string) ($body['embeds'][0]['title'] ?? ''), 'Web neodpovídá') && str_contains((string) ($body['embeds'][0]['description'] ?? ''), 'HTTP 503');
    });
});

it('turns plain-language requests in the panel chat into service actions the customer confirms', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    $this->actingAs($user, 'sanctum');
    $answer = $this->postJson('/v1/assistant/chat', ['text' => 'Zálohuj shop.cz a nasaď poslední verzi', 'locale' => 'cs'])->assertOk()->json('data');
    $proposals = array_values(array_filter($answer['actions'], fn ($a) => $a['kind'] === 'service_action'));
    expect($proposals)->toHaveCount(2)
        ->and($proposals[0])->toMatchArray(['action' => 'backup', 'service_id' => $service->id, 'confirm' => true])
        ->and($proposals[1]['action'])->toBe('deploy.run')
        ->and($answer['text'])->toContain('Připravil jsem k potvrzení')->toContain('Zálohovat shop.cz')
        ->and($answer['confident'])->toBeTrue()->and($answer['handoff'])->toBeNull();
    $http3 = $this->postJson('/v1/assistant/chat', ['text' => 'Zapni HTTP/3 na webu shop.cz', 'locale' => 'cs'])->assertOk()->json('data');
    $h3 = array_values(array_filter($http3['actions'], fn ($a) => $a['kind'] === 'service_action'));
    expect($h3)->toHaveCount(1)->and($h3[0]['action'])->toBe('http3.set')->and($h3[0]['params'])->toBe(['enabled' => true])->and($h3[0]['label'])->toBe('Zapnout HTTP/3 na shop.cz');
    $none = $this->postJson('/v1/assistant/chat', ['text' => 'restartuj shop.cz', 'locale' => 'cs'])->assertOk()->json('data');
    expect(array_filter($none['actions'], fn ($a) => $a['kind'] === 'service_action'))->toBe([]); // no restart on web hosting
    $en = $this->postJson('/v1/assistant/chat', ['text' => 'please enable redis cache on shop.cz', 'locale' => 'en'])->assertOk()->json('data');
    $cache = array_values(array_filter($en['actions'], fn ($a) => $a['kind'] === 'service_action'))[0] ?? null;
    expect($cache)->not->toBeNull()->and($cache['action'])->toBe('wp.cache')->and($cache['params'])->toBe(['enabled' => true])->and($cache['label'])->toBe('Turn Redis cache on for shop.cz');
});
