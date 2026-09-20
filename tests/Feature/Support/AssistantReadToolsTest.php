<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Support\Assistant\AiProviderRegistry;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\AiProvider;

/*
 * Two read tools of the assistant: the DNS records of a zone and one document by its number. Each is offered only to
 * somebody who may read that in the panel — and, asked for anyway by a model that was not offered it, answers like an
 * unknown zone or document. Another organization's zone or invoice does not exist for the conversation.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
});

afterEach(fn () => app(AiProviderRegistry::class)->override(null));

/** A model that calls both tools whether they were offered or not, and repeats what it was told — with the names of the tools it was offered. */
function pryingLlm(string $zone, string $number): AiProvider
{
    return new class($zone, $number) implements AiProvider
    {
        private int $round = 0;

        public function __construct(private readonly string $zone, private readonly string $number) {}

        public static function providerKey(): string
        {
            return 'fake';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            if (++$this->round === 1) {
                return ['content' => 'OFFERED: '.implode(',', array_column($tools, 'name')), 'tool_calls' => [['id' => 'd1', 'name' => 'get_dns_records', 'arguments' => ['zone' => $this->zone]], ['id' => 'i1', 'name' => 'get_invoice', 'arguments' => ['number' => $this->number]]],
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'model' => 'fake-1', 'finish_reason' => 'tool_calls'];
            }
            $offered = (string) collect($messages)->where('role', 'assistant')->pluck('content')->filter()->first();
            $told = implode("\n", array_map(fn ($m) => (string) $m['content'], array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'tool')));

            return ['content' => $offered."\nTOOLS: ".$told, 'tool_calls' => [], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1], 'model' => 'fake-1', 'finish_reason' => 'stop'];
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

it('reads the records of a zone and one document for somebody who may read them, and for nobody else', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'name' => 'firma.cz', 'provider' => 'powerdns', 'state' => 'active']);
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'A', 'content' => '203.0.113.10', 'ttl' => 3600, 'managed_by' => 'customer']);
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'MX', 'content' => '10 mail.firma.cz.', 'ttl' => 3600, 'managed_by' => 'platform', 'protected' => true]);
    $foreignZone = DnsZone::query()->create(['organization_id' => $other->id, 'name' => 'cizi.cz', 'provider' => 'powerdns', 'state' => 'active']);
    DnsRecord::query()->create(['zone_id' => $foreignZone->id, 'name' => '@', 'type' => 'A', 'content' => '198.51.100.77', 'ttl' => 3600, 'managed_by' => 'customer']);
    $invoice = Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0042', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::ISSUED,
        'subtotal_minor' => 100000, 'tax_minor' => 21000, 'total_minor' => 121000, 'paid_minor' => 0, 'issued_at' => now(), 'due_at' => now()->addDays(14), 'supply_date' => now()->toDateString(), 'payment_reference' => '20260042']);
    InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'position' => 1, 'sku' => 'web', 'description' => 'Webhosting Standard', 'qty' => 1, 'unit' => 'ks', 'unit_net_minor' => 100000, 'net_minor' => 100000, 'tax_rate' => 21, 'tax_minor' => 21000, 'total_minor' => 121000]);
    Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0099', 'organization_id' => $other->id, 'currency' => 'CZK', 'state' => Invoice::ISSUED, 'subtotal_minor' => 5, 'tax_minor' => 1, 'total_minor' => 6, 'paid_minor' => 0]);
    $ask = fn () => (string) $this->postJson('/v1/assistant/chat', ['text' => 'Kam míří moje doména a kolik mám zaplatit?'])->assertOk()->json('data.text');

    // the owner: both tools are offered and answer
    app(AiProviderRegistry::class)->override(pryingLlm('Firma.cz.', 'fv-2026-0042'));
    $this->actingAs($owner, 'sanctum')->withHeader('X-Organization', $org->id);
    $answer = $ask();
    expect($answer)->toContain('get_dns_records')->toContain('get_invoice')->toContain('203.0.113.10')->toContain('mail.firma.cz')->toContain('FV-2026-0042')->toContain('20260042')->toContain('Webhosting Standard');

    // somebody else's zone and document do not exist for this conversation
    app(AiProviderRegistry::class)->override(pryingLlm('cizi.cz', 'FV-2026-0099'));
    $answer = $ask();
    expect($answer)->toContain('unknown zone')->toContain('unknown document')->not->toContain('198.51.100.77');

    // a support contact reads neither domains nor invoices in the panel: the tools are not offered, and asked for anyway they know nothing
    $contact = $this->customer(['email' => 'kontakt@example.cz']);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $contact->id, 'role_key' => 'support_contact', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $contact->id, 'state' => 'active', 'role_key' => 'support_contact', 'joined_at' => now()]);
    app(AiProviderRegistry::class)->override(pryingLlm('firma.cz', 'FV-2026-0042'));
    $this->actingAs($contact, 'sanctum')->withHeader('X-Organization', $org->id);
    $answer = $ask();
    expect($answer)->not->toContain('get_dns_records')->not->toContain('get_invoice')->not->toContain('203.0.113.10')->not->toContain('20260042')->toContain('unknown zone')->toContain('unknown document');

    // …and the conversation is this person's alone. The same address and no session id used to continue the OWNER's transcript:
    // the questions and answers above — the records, the invoice, its variable symbol — went into the model's context for somebody else
    $mine = AiRun::query()->where('user_id', $contact->id)->latest('created_at')->firstOrFail();
    expect($mine->session_id)->toStartWith($contact->id.':')->and(collect($mine->transcript)->where('role', 'user')->count())->toBe(1)
        ->and(json_encode($mine->transcript))->not->toContain('203.0.113.10')->not->toContain('20260042');
});

it('gives a visitor without a browser session no memory at all, instead of the memory of everybody behind the same address', function () {
    config(['onhost.ai.enabled' => false]);
    // no route serves visitors today; whoever calls the service for one (a widget on the public site) gets the same rule
    $visitor = fn () => new CommandContext('system', null, null, null, '203.0.113.5', 'pest', null);
    $first = app(AssistantService::class)->chat('Moje doména tajna-firma.cz nejde, jsem jan@tajna-firma.cz', null, null, null, $visitor());
    $second = app(AssistantService::class)->chat('Kolik stojí webhosting?', null, null, null, $visitor());
    $runs = AiRun::query()->whereNull('user_id')->orderBy('created_at')->get();
    expect($runs)->toHaveCount(2)->and($runs[0]->session_id)->not->toBe($runs[1]->session_id)->and($runs[0]->session_id)->not->toContain('203.0.113.5')
        ->and(json_encode($runs[1]->transcript))->not->toContain('tajna-firma.cz');
    expect($first['run_id'])->not->toBe($second['run_id']);
});
