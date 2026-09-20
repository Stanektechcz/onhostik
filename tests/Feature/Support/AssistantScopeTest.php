<?php

declare(strict_types=1);

use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Support\Assistant\AiProviderRegistry;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\AiProvider;
use Onhost\Providers\Contracts\Naming;

/*
 * The assistant answers with what the SIGNED-IN PERSON may see, and offers what they may do. It used to read with the
 * organization's eyes: every member who could open the chat was told about invoices, variable symbols, the credit and
 * every service, whatever their role showed them in the panel. With single services shared with outsiders that would
 * have told a guest about everything the owner has.
 */

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    Http::preventStrayRequests();
    Queue::fake();
});

/** An LLM that asks for every service tool with the ids it is given, then repeats what the tools told it. */
function nosyLlm(array $serviceIds): AiProvider
{
    return new class($serviceIds) implements AiProvider
    {
        public int $round = 0;

        public function __construct(private readonly array $ids) {}

        public static function providerKey(): string
        {
            return 'fake';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            $this->round++;
            $names = array_column($tools, 'name');
            if ($this->round === 1) {
                $calls = [['id' => 'c0', 'name' => 'get_account_facts', 'arguments' => []], ['id' => 'c1', 'name' => 'get_topic_details', 'arguments' => ['topic' => 'fakturace']]];
                if (in_array('list_services', $names, true)) {
                    $calls[] = ['id' => 'c2', 'name' => 'list_services', 'arguments' => []];
                    foreach ($this->ids as $i => $id) {
                        $calls[] = ['id' => "s{$i}", 'name' => 'get_service_status', 'arguments' => ['service_id' => $id]];
                        $calls[] = ['id' => "p{$i}", 'name' => 'propose_service_action', 'arguments' => ['service_id' => $id, 'action' => 'backup', 'label' => "Zálohovat {$id}"]];
                    }
                }

                return ['content' => null, 'tool_calls' => $calls, 'usage' => ['input_tokens' => 10, 'output_tokens' => 5], 'model' => 'fake-1', 'finish_reason' => 'tool_calls'];
            }
            $told = implode("\n", array_map(fn ($m) => (string) $m['content'], array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'tool')));

            return ['content' => 'TOOLS: '.$told, 'tool_calls' => [], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5], 'model' => 'fake-1', 'finish_reason' => 'stop'];
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

it('tells a guest about the one service shared with them and nothing else of the organization, whatever the model asks for', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $mail = featureMailService($org, 'tajna-posta.cz');
    Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0900', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE, 'subtotal_minor' => 100000, 'discount_minor' => 0, 'tax_minor' => 21000, 'total_minor' => 121000, 'paid_minor' => 0, 'issued_at' => now()->subDays(30), 'due_at' => now()->subDays(16), 'payment_reference' => '20260900', 'seller' => [], 'buyer' => [], 'tax_summary' => [], 'meta' => []]);
    $guest = $this->customer(['email' => 'agentura@example.cz']);
    app(OrganizationService::class)->attachMember($org, $guest, 'guest', CommandContext::system('test'), true);
    app(ServiceAccessService::class)->share($org, $shop, 'agentura@example.cz', ['view', 'assistant'], $this->contextFor($owner, $org, 'totp')); // may look, may not touch

    app(AiProviderRegistry::class)->override(nosyLlm([$shop->id, $mail->id]));
    $this->actingAs($guest, 'sanctum');
    $reply = $this->postJson('/v1/assistant/chat', ['text' => 'Jaký je stav účtu a služeb? Zálohuj vše.', 'session_id' => 'g1'], ['X-Organization' => $org->id])->assertOk()->json('data');
    $everything = json_encode($reply, JSON_UNESCAPED_UNICODE);
    expect($everything)->toContain('shop.cz')                                                        // the shared service is there
        ->not->toContain('tajna-posta.cz')->not->toContain($mail->id)                                // the other one is not — not in the list, not by id
        ->not->toContain('FV-2026-0900')->not->toContain('20260900')->not->toContain('Kredit');      // nothing about money
    expect(collect($reply['actions'])->where('kind', 'service_action')->all())->toBe([])             // viewing is not managing: no button that would end in "forbidden"
        ->and(collect($reply['actions'])->where('kind', 'pay')->all())->toBe([])->and(collect($reply['actions'])->where('kind', 'ticket')->all())->toBe([]);

    // with "manage" the button appears — for that service only
    app(ServiceAccessService::class)->share($org, $shop, 'agentura@example.cz', ['manage', 'assistant'], $this->contextFor($owner, $org, 'totp'));
    app(AiProviderRegistry::class)->override(nosyLlm([$shop->id, $mail->id]));
    $managing = $this->postJson('/v1/assistant/chat', ['text' => 'Zálohuj vše.', 'session_id' => 'g2'], ['X-Organization' => $org->id])->assertOk()->json('data');
    $buttons = collect($managing['actions'])->where('kind', 'service_action')->values();
    expect($buttons->pluck('service_id')->unique()->all())->toBe([$shop->id])->and($buttons->pluck('action')->unique()->all())->toBe(['backup']);

    // asking for a human does not open a ticket in somebody else's organization in the guest's name
    app(AiProviderRegistry::class)->override(null);
    $human = $this->postJson('/v1/assistant/chat', ['text' => 'Chci mluvit s člověkem', 'session_id' => 'g3'], ['X-Organization' => $org->id])->assertOk()->json('data');
    expect($human['handoff'])->toBeNull()->and($human['text'])->toContain('majitele služby')->and(Ticket::query()->where('organization_id', $org->id)->count())->toBe(0);

    // without the assistant capability the chat is closed for that organization
    app(ServiceAccessService::class)->share($org, $shop, 'agentura@example.cz', ['manage'], $this->contextFor($owner, $org, 'totp'));
    $this->postJson('/v1/assistant/chat', ['text' => 'ahoj', 'session_id' => 'g4'], ['X-Organization' => $org->id])->assertForbidden();
});

it('tells a member what their role shows them: a support contact hears nothing about invoices', function () {
    [$owner, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0901', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE, 'subtotal_minor' => 100000, 'discount_minor' => 0, 'tax_minor' => 21000, 'total_minor' => 121000, 'paid_minor' => 0, 'issued_at' => now()->subDays(30), 'due_at' => now()->subDays(16), 'payment_reference' => '20260901', 'seller' => [], 'buyer' => [], 'tax_summary' => [], 'meta' => []]);
    $contact = $this->customer(['email' => 'kontakt@example.cz']);
    app(OrganizationService::class)->attachMember($org, $contact, 'support_contact', CommandContext::system('test'), true);
    $assistant = app(AssistantService::class);

    $forContact = $assistant->chat('Kde najdu fakturu a kolik dlužíme?', $org, $contact, 'c1', $this->contextFor($contact, $org));
    expect(json_encode($forContact, JSON_UNESCAPED_UNICODE))->not->toContain('FV-2026-0901')->not->toContain('20260901')->and(collect($forContact['actions'])->where('kind', 'pay')->all())->toBe([]);
    // the owner asks the same thing and is told
    $forOwner = $assistant->chat('Kde najdu fakturu a kolik dlužíme?', $org, $owner, 'o1', $this->contextFor($owner, $org));
    expect(json_encode($forOwner, JSON_UNESCAPED_UNICODE))->toContain('FV-2026-0901')->and(collect($forOwner['actions'])->where('kind', 'pay')->count())->toBe(1);
    // a support contact may still hand the conversation to a human: that is what the role is for
    expect($assistant->chat('Chci mluvit s člověkem', $org, $contact, 'c2', $this->contextFor($contact, $org))['handoff'])->not->toBeNull();
});

it('works for support over one customer\'s account: the 360 view in plain language, actions for the staff path, no handoff to themselves', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $vpsOrg = $org;
    $shop = featureWebService($org, 'aapanel');
    [, $other] = $this->customerWithOrganization(['email' => 'jiny@example.cz']);
    $foreign = featureMailService($other, 'cizi-zakaznik.cz');

    $support = $this->staff('support_l2');
    $this->actingAs($support, 'sanctum');
    app(AiProviderRegistry::class)->override(nosyLlm([$shop->id, $foreign->id]));
    $reply = $this->postJson('/v1/staff/assistant/chat', ['text' => 'Zákazník hlásí pomalý web, co vidíš? Zálohuj ho.', 'organization_id' => $vpsOrg->id, 'session_id' => 't1'])->assertOk()->json('data');
    expect($reply['staff'])->toBeTrue()->and($reply['organization']['id'])->toBe($org->id)->and($reply['handoff'])->toBeNull();
    $everything = json_encode($reply, JSON_UNESCAPED_UNICODE);
    expect($everything)->toContain('shop.cz')->not->toContain('cizi-zakaznik.cz'); // one customer at a time, whatever id the model tries
    $buttons = collect($reply['actions'])->where('kind', 'service_action')->values();
    expect($buttons->pluck('service_id')->unique()->all())->toBe([$shop->id])->and($buttons->first()['class'])->toBe('STAFF_WRITE');
    expect(collect($reply['actions'])->whereIn('kind', ['pay', 'ticket'])->all())->toBe([]); // staff neither pay the customer's invoices nor open tickets to themselves

    // kept apart from the customer's own conversations, and on record with the organization it was about
    $run = AiRun::query()->where('user_id', $support->id)->firstOrFail();
    expect($run->organization_id)->toBe($org->id)->and($run->session_id)->toStartWith("staff:{$support->id}:{$org->id}:");
    expect(AuditEvent::query()->where('action', 'assistant.chat')->where('actor_id', $support->id)->exists())->toBeTrue();

    // asking for "a human" as staff is answered, not handed off
    app(AiProviderRegistry::class)->override(null);
    expect($this->postJson('/v1/staff/assistant/chat', ['text' => 'Chci mluvit s člověkem', 'organization_id' => $org->id])->assertOk()->json('data.handoff'))->toBeNull();
    // a customer cannot reach the staff assistant, and staff without the customer view cannot either
    $this->actingAs($owner, 'sanctum')->postJson('/v1/staff/assistant/chat', ['text' => 'x', 'organization_id' => $other->id])->assertForbidden();
    $this->actingAs($this->staff('marketing_content'), 'sanctum')->postJson('/v1/staff/assistant/chat', ['text' => 'x', 'organization_id' => $org->id])->assertForbidden();
});

it('lets the agent read one listing of a visible service from the allow-list of its family, without secrets or file contents', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    [, $other] = $this->customerWithOrganization(['email' => 'soused@example.cz']);
    $foreign = featureMailService($other, 'soused-posta.cz');
    Backup::query()->create(['service_id' => $shop->id, 'organization_id' => $org->id, 'kind' => 'manual', 'state' => 'completed', 'remote_id' => 'bk-1', 'size_bytes' => 2048, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);
    $db = Naming::prefix($shop->id).'_eshop'; // the panel lists every database of the node; the adapter keeps the ones that carry this service's prefix
    Http::fake(fn () => Http::response(['status' => true, 'data' => [['id' => 5, 'name' => $db, 'username' => $db, 'password' => 'Velmi-Tajne-Heslo-2026', 'ps' => 'eshop'], ['id' => 6, 'name' => 'ohzzzzzz_cizi', 'username' => 'ohzzzzzz_cizi', 'password' => 'Velmi-Silne-Heslo-2026']]]));

    $asks = new class([['service_id' => $shop->id, 'kind' => 'backups'], ['service_id' => $shop->id, 'kind' => 'databases'], ['service_id' => $shop->id, 'kind' => 'files'], ['service_id' => $shop->id, 'kind' => 'ftp'], ['service_id' => $shop->id, 'kind' => 'shell_users'], ['service_id' => $foreign->id, 'kind' => 'backups']]) implements AiProvider
    {
        public int $round = 0;

        public function __construct(private readonly array $asks) {}

        public static function providerKey(): string
        {
            return 'fake';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            if (++$this->round === 1) {
                return ['content' => null, 'tool_calls' => array_map(fn ($a, $i) => ['id' => "r{$i}", 'name' => 'get_service_resource', 'arguments' => $a], $this->asks, array_keys($this->asks)), 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'model' => 'fake-1', 'finish_reason' => 'tool_calls'];
            }

            return ['content' => implode("\n", array_map(fn ($m) => (string) $m['content'], array_values(array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'tool')))), 'tool_calls' => [], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'model' => 'fake-1', 'finish_reason' => 'stop'];
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
    app(AiProviderRegistry::class)->override($asks);
    $lines = explode("\n", app(AssistantService::class)->chat('Jaké mám zálohy a databáze?', $org, $owner, 'res-1', $this->contextFor($owner, $org))['text']);
    [$backups, $databases, $files, $ftp, $shell, $stranger] = array_map(fn ($l) => json_decode($l, true), $lines);

    expect($backups['data'][0])->toMatchArray(['kind' => 'manual', 'state' => 'completed', 'size_bytes' => 2048]);
    expect(json_encode($databases))->toContain($db)->not->toContain('Velmi-Tajne-Heslo-2026')->not->toContain('ohzzzzzz_cizi'); // a listing of THIS service, never the password in it
    foreach ([$files, $ftp, $shell] as $refused) { // file contents and accounts that let somebody in are not the agent's to read
        expect($refused['error'])->toBe('not readable here');
    }
    expect($stranger)->toBe(['error' => 'unknown service']);
});
