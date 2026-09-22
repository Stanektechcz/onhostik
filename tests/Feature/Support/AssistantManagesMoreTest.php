<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Support\Assistant\AiProviderRegistry;
use Onhost\Domain\Support\Assistant\AssistantProposals;
use Onhost\Providers\Contracts\AiProvider;

/*
 * "Přes AI chat je možné spravovat jednotlivé služby klienta." The assistant puts on a button only what is on its allow-list
 * (AssistantProposals: undoable or repeatable, parameters a reference or a choice, no step-up). The list had twelve actions
 * and none of what the platform learned since — a restore test of a backup, a staging copy, rolling a deployment back,
 * leaving rescue mode, a Node.js app, HTTP/3, a game schedule, a database export, a mailbox backup. The tool's description
 * repeated the list by hand, and a refused action told the model nothing it could pass on.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
});

afterEach(fn () => app(AiProviderRegistry::class)->override(null));

/** A model that proposes what it is told and remembers what the platform gave it: the tools and the tool answers. */
function recordingLlm(array $proposals, ArrayObject $seen): AiProvider
{
    return new class($proposals, $seen) implements AiProvider
    {
        private int $round = 0;

        public function __construct(private readonly array $proposals, private readonly ArrayObject $seen) {}

        public static function providerKey(): string
        {
            return 'fake';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            $this->seen['tools'] = $tools;
            $this->seen['messages'] = $messages;
            if (++$this->round === 1) {
                $calls = [];
                foreach ($this->proposals as $i => $arguments) {
                    $calls[] = ['id' => "p{$i}", 'name' => 'propose_service_action', 'arguments' => $arguments];
                }

                return ['content' => null, 'tool_calls' => $calls, 'usage' => ['input_tokens' => 10, 'output_tokens' => 5], 'model' => 'fake-1', 'finish_reason' => 'tool_calls'];
            }

            return ['content' => 'Připravil jsem tlačítka.', 'tool_calls' => [], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5], 'model' => 'fake-1', 'finish_reason' => 'stop'];
        }

        public function embeddings(array $inputs, array $options = []): array
        {
            return [];
        }

        public function moderate(string $input): array
        {
            return ['flagged' => false, 'categories' => []];
        }
    };
}

/** @return array<string,mixed> the answer of the platform to the proposal with this id */
function recordedAnswer(ArrayObject $seen, string $callId): array
{
    foreach ((array) ($seen['messages'] ?? []) as $message) {
        if (($message['role'] ?? '') === 'tool' && ($message['tool_call_id'] ?? '') === $callId) {
            return (array) json_decode((string) $message['content'], true);
        }
    }

    return [];
}

it('puts the newer management actions on buttons, each with its own parameters only', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $backup = Backup::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'provider_instance_id' => $site->provider_instance_id, 'kind' => 'manual', 'state' => 'completed', 'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'retention_until' => now()->addMonth(), 'meta' => ['set' => FinalArchive::PREFIX.'/'.$org->id.'/'.$site->id.'-20260921-020000']]);
    $this->actingAs($owner, 'sanctum');
    $seen = new ArrayObject;
    app(AiProviderRegistry::class)->override(recordingLlm([
        ['service_id' => $site->id, 'action' => 'restore.test', 'params' => ['backup_id' => $backup->id, 'target' => 'production']], // a parameter smuggled in
        ['service_id' => $site->id, 'action' => 'node.action', 'params' => ['remote_id' => 'shop-api', 'op' => 'delete']],          // deleting is never on a button
        ['service_id' => $site->id, 'action' => 'node.action', 'params' => ['remote_id' => 'shop-api', 'op' => 'restart']],
        ['service_id' => $site->id, 'action' => 'deploy.rollback', 'params' => []],                                                 // which release? nothing is guessed
        ['service_id' => $site->id, 'action' => 'http3.set', 'label' => 'Smazat produkci', 'params' => ['enabled' => 'false']],
        ['service_id' => $site->id, 'action' => 'staging.create'],                                                                 // this plan has no staging: not offered here
        ['service_id' => $site->id, 'action' => 'database.export', 'params' => ['remote_id' => 'shop_db; DROP DATABASE shop_db']],    // not a name
        ['service_id' => $site->id, 'action' => 'database.export', 'params' => ['remote_id' => 'shop_db']],
    ], $seen));

    $reply = $this->postJson('/v1/assistant/chat', ['text' => 'Pomoz mi prosím s webem.', 'session_id' => 'more'], ['X-Organization' => $org->id])->assertOk()->json('data');
    $buttons = collect($reply['actions'])->where('kind', 'service_action')->keyBy('action');

    // it used to be: none of these could be proposed at all — the customer was sent to the panel for each
    expect($buttons->keys()->sort()->values()->all())->toBe(['database.export', 'http3.set', 'node.action', 'restore.test']);
    expect($buttons['restore.test'])->toMatchArray(['params' => ['backup_id' => $backup->id]])->and($buttons['restore.test']['label'])->toContain('Otestovat obnovu zálohy')
        ->and($buttons['node.action'])->toMatchArray(['params' => ['remote_id' => 'shop-api', 'op' => 'restart']])->and($buttons['node.action']['label'])->toContain('Restartovat aplikaci shop-api')
        ->and($buttons['http3.set'])->toMatchArray(['params' => ['enabled' => false]])->and($buttons['http3.set']['label'])->toContain('Vypnout HTTP/3')->not->toContain('Smazat')
        ->and($buttons['database.export']['label'])->toContain('Exportovat databázi shop_db');
    expect(recordedAnswer($seen, 'p1')['ok'])->toBeFalse()->and(recordedAnswer($seen, 'p3')['ok'])->toBeFalse()
        ->and((string) recordedAnswer($seen, 'p5')['error'])->toContain('does not offer')->and(recordedAnswer($seen, 'p6')['ok'])->toBeFalse();

    // a button is only worth showing if pressing it works: each goes through the customer's own API, as the panel sends it
    foreach ($buttons as $button) {
        $this->postJson("/v1/services/{$site->id}/actions", ['action' => $button['action'], 'params' => $button['params']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'press-'.$button['action']])
            ->assertStatus(202);
        Operation::query()->where('service_id', $site->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]); // one operation per service at a time: the queue is faked here
    }
});

it('does not run a restore test on somebody else\'s backup, whatever the button says', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    [, $other] = $this->customerWithOrganization();
    $foreign = featureMailService($other, 'cizi.cz');
    $theirs = Backup::query()->create(['service_id' => $foreign->id, 'organization_id' => $other->id, 'provider_instance_id' => $foreign->provider_instance_id, 'kind' => 'manual', 'state' => 'completed', 'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'retention_until' => now()->addMonth(), 'meta' => ['set' => FinalArchive::PREFIX.'/'.$other->id.'/'.$foreign->id.'-20260921-020000']]);
    $this->actingAs($owner, 'sanctum');
    $seen = new ArrayObject;
    app(AiProviderRegistry::class)->override(recordingLlm([['service_id' => $site->id, 'action' => 'restore.test', 'params' => ['backup_id' => $theirs->id]]], $seen));

    $reply = $this->postJson('/v1/assistant/chat', ['text' => 'Pomoz mi prosím s webem.', 'session_id' => 'foreign'], ['X-Organization' => $org->id])->assertOk()->json('data');

    // the id has the shape of a backup — it is not drawn all the same: no finished backup of THIS service carries it
    expect(collect($reply['actions'])->where('action', 'restore.test')->all())->toBe([])
        ->and((string) recordedAnswer($seen, 'p0')['error'])->toContain('not a finished backup of this service');
    // and sent by hand, the platform does not find it either
    $this->postJson("/v1/services/{$site->id}/actions", ['action' => 'restore.test', 'params' => ['backup_id' => $theirs->id]], ['X-Organization' => $org->id, 'Idempotency-Key' => 'press-foreign'])->assertNotFound();
});

it('tells the model why an action is not put on a button, so that it can tell the customer', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $this->actingAs($owner, 'sanctum');
    $seen = new ArrayObject;
    app(AiProviderRegistry::class)->override(recordingLlm([
        ['service_id' => $site->id, 'action' => 'terminate'],                                                                  // asks for a fresh step-up
        ['service_id' => $site->id, 'action' => 'cron.create', 'params' => ['schedule' => '0 * * * *', 'command' => 'php x']],  // carries a command
        ['service_id' => $site->id, 'action' => 'mailbox.backup', 'params' => ['remote_id' => '5']],                          // a web site has no mailboxes
    ], $seen));

    $this->postJson('/v1/assistant/chat', ['text' => 'Zruš službu, přidej cron a zálohuj schránku.', 'session_id' => 'why'], ['X-Organization' => $org->id])->assertOk();

    // it used to be one sentence for all three: the model could not say whether the customer lacks a right, a feature or a panel click
    expect((string) recordedAnswer($seen, 'p0')['error'])->toContain('fresh confirmation of the customer\'s identity')
        ->and((string) recordedAnswer($seen, 'p1')['error'])->toContain('carries a command')
        ->and((string) recordedAnswer($seen, 'p2')['error'])->toContain('does not offer');
});

it('tells the model every action it may propose and every listing it may read, from the lists themselves', function () {
    [$owner, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $this->actingAs($owner, 'sanctum');
    $seen = new ArrayObject;
    app(AiProviderRegistry::class)->override(recordingLlm([], $seen));

    $this->postJson('/v1/assistant/chat', ['text' => 'Co umíš udělat s mým webem?', 'session_id' => 'list'], ['X-Organization' => $org->id])->assertOk();
    $tools = collect((array) $seen['tools'])->keyBy('name');

    // it used to be a sentence written by hand next to the list — the two drifted the moment an action was added
    foreach (array_keys(AssistantProposals::ACTIONS) as $action) {
        expect($tools['propose_service_action']['description'])->toContain($action);
    }
    expect($tools['propose_service_action']['description'])->toContain('params.op start|stop|restart')->not->toContain('delete)')
        ->and($tools['get_service_resource']['description'])->toContain('node_projects')->toContain('deployments');
});
