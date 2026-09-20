<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as PaymentState;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceSummary;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Domain\Support\Models\Handoff;
use Onhost\Domain\Support\Models\KnowledgeArticle;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\Support\Triage;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Providers\Contracts\AiProvider;
use Symfony\Component\Yaml\Yaml;

/**
 * ONhost AI Assistant (blueprint §69, §70): visibly AI, context-isolated to the signed-in
 * organization, READ tools only, SAFE_WRITE actions returned as proposals the user confirms
 * through the normal API, and a human handoff on request, low confidence, security/legal
 * topics or repeated failure. Runs on the configured LLM provider, or on the deterministic
 * answer bank when none is configured — the response contract is identical.
 */
final class AssistantService
{
    public const HANDOFF_WORDS = ['clovek', 'cloveka', 'operator', 'operatora', 'podpora', 'podporu', 'human', 'agent', 'someone', 'nekdo zivy'];

    public const LEGAL_WORDS = ['reverse charge', 'danov', 'dan z', 'dph', 'legal', 'pravni', 'smlouv', 'gdpr', 'reklamac', 'odstoup'];

    /**
     * What the agent may read about ONE service, by family: listings that describe the service and carry neither secrets
     * nor file contents. Whatever comes back still passes the redactor, and the plan's own feature gates apply.
     */
    private const READABLE = [
        'web' => ['databases', 'cron', 'subdomains', 'certificate', 'redirect', 'php_settings', 'quotas', 'monitoring', 'staging', 'deploy', 'wordpress', 'cdn', 'backups'],
        'managed' => ['databases', 'cron', 'subdomains', 'certificate', 'redirect', 'php_settings', 'quotas', 'monitoring', 'staging', 'deploy', 'wordpress', 'cdn', 'backups'],
        'mail' => ['mailboxes', 'aliases', 'dkim', 'mail_forwards', 'mail_lists', 'mail_usage', 'backups'],
        'game' => ['status', 'schedules', 'allocations', 'backups'],
        'cloud' => ['snapshots', 'firewall', 'backups'],
        'data' => ['snapshots', 'backups'],
    ];

    /** Extra system guidance when the LLM acts as the service-management agent (proposals become confirm buttons). */
    private const AGENT_PROMPT = "\n\nYou can also help manage the customer's services. Use list_services to see them and get_service_status for details. When the customer asks for a change (restart, backup, deploy, WordPress update, Redis cache, staging refresh/push, CDN purge, certificate, PHP version), call propose_service_action once per action — the platform shows it as a button the customer must confirm; never claim an action ran. Only propose actions the tool reports as available. Explain briefly what the action does and any risk (e.g. staging push replaces production).";

    public function __construct(
        private readonly AiProviderRegistry $providers,
        private readonly TicketService $tickets,
        private readonly Redactor $redactor,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly ServiceFeatures $features,
        private readonly ServiceSummary $summary,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * @return array{run_id:string, topic:string, label:string, confident:bool, text:string, facts:list<array{k:string,v:string}>, actions:list<array<string,mixed>>, handoff:?array{ticket_id:string,number:string,reason:string}, ai:bool, disclosure:string}
     */
    public function chat(string $text, ?Organization $organization, ?User $user, ?string $sessionId, CommandContext $context, string $locale = 'cs', ?AssistantScope $scope = null): array
    {
        $text = trim($text);
        // what this person may see and be offered — the same answers the API would give them (a member without billing rights is
        // told nothing about invoices, a guest sees the services shared with them and no others)
        $scope ??= $organization !== null && $user !== null ? AssistantScope::for($organization, $user, $this->authorizer) : null;
        $sessionId ??= 'anon:'.($context->sessionId ?? $context->ip ?? 'x');
        $previous = AiRun::query()->where('session_id', $sessionId)->orderByDesc('created_at')->first();
        $transcript = (array) ($previous?->transcript ?? []);
        $transcript[] = ['role' => 'user', 'content' => mb_substr($text, 0, 4000), 'at' => now()->toIso8601String()];
        $triage = Triage::classify($text);
        $normalized = Triage::normalize($text);
        $facts = $organization !== null ? $this->facts($organization, $locale, $scope) : [];
        $articles = $this->articles($text, $locale);
        // the sources next to the account summary: topic details from the organization's records, the API reference for
        // integration questions, the price list for offers — each read only when the question is about it
        $detail = $organization !== null ? $this->detail($organization, $triage['topic'], $locale, $scope) : [];
        $reference = $triage['topic'] === 'api' || str_contains(' '.$normalized.' ', ' api ') ? $this->apiReference($text) : [];
        $catalog = in_array($triage['topic'], ['cenik', 'objednavka'], true) ? $this->catalogLines($locale) : [];
        // the service-management agent: plain-language requests become proposals the customer confirms with a button (never executed here)
        $intents = $organization !== null && $user !== null ? ServiceIntent::detect($text, $organization, $this->features, $locale, $scope) : [];
        $llmProposals = [];

        $handoffReason = null;
        foreach (self::HANDOFF_WORDS as $w) {
            if (str_contains($normalized, $w)) {
                $handoffReason = 'user_request';
                break;
            }
        }
        if ($handoffReason === null && in_array($triage['topic'], Triage::SECURITY_TOPICS, true)) {
            $handoffReason = 'security';
        }
        if ($handoffReason === null) {
            foreach (self::LEGAL_WORDS as $w) {
                if (str_contains($normalized, $w)) {
                    $handoffReason = 'legal';
                    break;
                }
            }
        }
        if ($handoffReason === null && $this->repeated($transcript, $triage['topic'])) {
            $handoffReason = 'repeated_failure';
        }
        if ($handoffReason === null && $organization === null && ! $triage['confident']) {
            $handoffReason = null; // anonymous visitors get documentation answers, never a handoff without identity
        }
        $mayHandOff = $scope === null || (! $scope->staff && $scope->ticketsWrite); // a handoff opens a ticket in the organization
        $refusedHandoff = $handoffReason !== null && ! $mayHandOff ? $handoffReason : null;
        if (! $mayHandOff) {
            $handoffReason = null;
        }

        $sessionTriage = $this->sessionTopic($transcript, $triage);
        $provider = $this->providers->provider();
        $answer = null;
        $usedLlm = false;
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $model = null;
        $toolsCalled = [];
        if ($handoffReason === null && $provider !== null) {
            try {
                [$answer, $usage, $model, $toolsCalled, $llmProposals] = $this->llm($provider, $transcript, $organization, $facts, $articles, $detail, $reference, $catalog, $locale, $intents, $scope);
                $usedLlm = $answer !== null;
            } catch (ProviderException $e) {
                $answer = null; // provider trouble never blocks the customer: fall back to rules
                $toolsCalled[] = ['tool' => 'llm', 'error' => $e->errorCode->value];
            }
        }
        if ($handoffReason === null && $answer === null) {
            $answer = $intents !== []
                ? ($locale === 'en' ? 'Understood. I prepared this for you to confirm: ' : 'Rozumím. Připravil jsem k potvrzení: ').implode(', ', array_map(fn ($i) => $i['label'], $intents)).($locale === 'en' ? '. Nothing runs until you press the button; progress then shows in the service detail under Operations.' : '. Nic neproběhne, dokud nepotvrdíte tlačítkem; průběh pak uvidíte v detailu služby v záložce Provoz.')
                : $this->rules($triage, $facts, $articles, $detail, $reference, $catalog, $locale);
        }
        $confident = $handoffReason === null && ($triage['confident'] || ($answer !== null && $facts !== []) || $intents !== []);
        if ($handoffReason === null && $mayHandOff && ! $confident && $organization !== null && ! $triage['confident'] && $triage['hits'] === 0 && $intents === []) {
            $handoffReason = 'low_confidence';
        }
        if ($refusedHandoff !== null && $scope !== null && ! $scope->staff && in_array($refusedHandoff, ['user_request', 'security', 'legal'], true)) {
            // somebody who cannot open tickets here (a guest of the organization) is told who can, instead of a ticket being opened in their name
            $answer = $locale === 'en' ? 'I cannot open a support ticket for this organization on your behalf. Ask the owner of the service to contact support, or write to us from your own account.' : 'Za tuto organizaci nemohu vaším jménem založit tiket podpory. Požádejte majitele služby, aby podporu kontaktoval, nebo nám napište ze svého vlastního účtu.';
        }

        $run = AiRun::query()->create([
            'organization_id' => $organization?->id, 'user_id' => $user?->id, 'session_id' => $sessionId, 'provider' => $usedLlm && $handoffReason === null ? $provider::providerKey() : 'rules', 'model' => $usedLlm ? $model : null,
            'topic' => $sessionTriage['topic'], 'confident' => $confident, 'outcome' => $handoffReason !== null ? 'handoff' : 'answered', 'tools_called' => $toolsCalled, 'input_tokens' => $usage['input_tokens'], 'output_tokens' => $usage['output_tokens'],
            'transcript' => $transcript,
        ]);

        $handoff = null;
        $actions = [];
        if ($handoffReason !== null) {
            $handoff = $organization !== null ? $this->handoff($run, $transcript, $sessionTriage, $facts, $handoffReason, $organization, $user, $context) : null;
            $answer = $handoff !== null
                ? ($locale === 'en' ? "I have passed this to a human colleague as ticket {$handoff['number']} together with a summary of our conversation. Support replies within 30 minutes, high priority within 15." : "Předal jsem to kolegovi z podpory jako tiket {$handoff['number']} i se shrnutím naší konverzace. Podpora odpovídá do 30 minut, u vysoké priority do 15.")
                : ($locale === 'en' ? 'Sign in and I will hand this over to a human colleague with the full context.' : 'Přihlaste se a předám to kolegovi z podpory i s celým kontextem.');
        } else {
            $actions = self::mergeProposals(array_merge($intents, $llmProposals), $this->actions($triage['topic'], $organization, $facts, $articles, $locale, $scope));
        }
        $transcript[] = ['role' => 'assistant', 'content' => $answer, 'at' => now()->toIso8601String(), 'handoff' => $handoff['number'] ?? null];
        $run->forceFill(['transcript' => $transcript, 'summary' => $this->summary($sessionTriage, $facts, $transcript, $locale), 'ticket_id' => $handoff['ticket_id'] ?? null])->save();
        $this->audit->record($context->withScope($organization?->id), 'assistant.chat', 'succeeded', ['topic' => $triage['topic'], 'outcome' => $run->outcome, 'provider' => $run->provider], 'ai_run', $run->id);

        return [
            'run_id' => $run->id, 'topic' => $triage['topic'], 'label' => $triage['label'], 'confident' => $confident, 'text' => (string) $answer, 'facts' => $facts, 'actions' => $actions, 'handoff' => $handoff, 'ai' => true,
            'disclosure' => $locale === 'en' ? 'ONhost AI Assistant. AI can make mistakes. You can ask for a human at any time.' : 'ONhost AI asistent. AI může dělat chyby. Kdykoliv můžete požádat o člověka.',
        ];
    }

    /**
     * The same assistant for a member of staff working on a customer's account (support, NOC): it reads what the
     * customer-360 view shows them, proposes service actions their staff role may run (the console confirms and sends them
     * through the staff API), and never hands off — the person asking IS the human. Conversations are kept per staff member
     * and customer, apart from the customer's own.
     *
     * @return array<string,mixed> the contract of `chat()`
     */
    public function chatAsStaff(string $text, Organization $organization, User $staff, ?string $sessionId, CommandContext $context, string $locale = 'cs'): array
    {
        $scope = AssistantScope::staff($organization, $staff, $this->authorizer);
        $answer = $this->chat($text, $organization, $staff, "staff:{$staff->id}:{$organization->id}:".($sessionId ?? 'default'), $context, $locale, $scope);

        return $answer + ['organization' => ['id' => $organization->id, 'name' => $organization->name], 'staff' => true];
    }

    /** Facts are read from the organization's own records only — never from the question text (blueprint §69.2). @return list<array{k:string,v:string}> */
    public function facts(Organization $organization, string $locale = 'cs', ?AssistantScope $scope = null): array
    {
        $cs = $locale !== 'en';
        $facts = [];
        $sees = fn (string $what): bool => $scope === null || $scope->{$what}; // no scope: a trusted caller inside the platform (a ticket summary for staff)
        $open = $sees('billing') ? Invoice::query()->where('organization_id', $organization->id)->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE])->where('type', 'invoice')->get() : collect();
        if ($open->isNotEmpty()) {
            $sum = Money::minor((int) $open->sum(fn (Invoice $i) => $i->total_minor - $i->paid_minor), $organization->currency);
            $facts[] = ['k' => $cs ? 'Neuhrazené doklady' : 'Unpaid documents', 'v' => $open->count().' · '.$sum->format($locale).($open->where('state', Invoice::OVERDUE)->count() ? ($cs ? ' · po splatnosti' : ' · overdue') : '')];
        }
        $dunning = $sees('billing') ? DunningCase::query()->where('organization_id', $organization->id)->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->count() : 0;
        if ($dunning > 0) {
            $facts[] = ['k' => $cs ? 'Upomínky' : 'Dunning', 'v' => (string) $dunning];
        }
        $orders = $sees('orders') ? Order::query()->where('organization_id', $organization->id)->orderByDesc('placed_at')->limit(3)->get() : collect();
        foreach ($orders as $order) {
            $facts[] = ['k' => ($cs ? 'Objednávka ' : 'Order ').$order->number, 'v' => OrderStateMachine::machine()->label($order->state)];
        }
        $services = ($scope?->services() ?? Service::query()->where('organization_id', $organization->id))->get();
        if ($services->isNotEmpty()) {
            $byState = $services->groupBy('state')->map->count();
            $facts[] = ['k' => $cs ? 'Služby' : 'Services', 'v' => $byState->map(fn ($n, $s) => ServiceStateMachine::machine()->label($s)." {$n}")->implode(', ')];
            // onboarding (audit §5e-4): what an active site still lacks, so the assistant can point at the next step
            foreach ($services->where('state', ServiceStateMachine::ACTIVE)->whereIn('family', ['web', 'managed'])->take(5) as $site) {
                $open = array_values(array_filter($this->summary->for($site)['checklist'] ?? [], fn (array $c) => ! $c['done']));
                if ($open !== []) {
                    $facts[] = ['k' => ($cs ? 'Dokončit u ' : 'To finish on ').($site->label ?: $site->hostname ?: $site->name), 'v' => implode(', ', array_map(fn (array $c) => $cs ? $c['label'] : $c['label_en'], $open))];
                }
            }
        }
        $expiring = $sees('domains') ? Domain::query()->where('organization_id', $organization->id)->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30))->orderBy('expires_at')->limit(3)->get() : collect();
        foreach ($expiring as $domain) {
            $facts[] = ['k' => $domain->fqdn_ascii, 'v' => ($cs ? 'expiruje ' : 'expires ').$domain->expires_at->toDateString().($domain->auto_renew ? ($cs ? ' · auto-obnova' : ' · auto-renew') : ($cs ? ' · bez auto-obnovy' : ' · no auto-renew'))];
        }
        if (Schema::hasTable('incidents')) {
            $incidents = DB::table('incidents')->whereNotIn('state', ['RESOLVED', 'POSTMORTEM'])->where('visibility', 'public')->limit(2)->get(['number', 'title', 'state']);
            foreach ($incidents as $incident) {
                $facts[] = ['k' => ($cs ? 'Incident ' : 'Incident ').$incident->number, 'v' => $incident->title.' · '.$incident->state];
            }
        }

        return $facts;
    }

    /**
     * Topic details from the organization's own records (blueprint §69.2): the documents to pay with their symbols and
     * the credit, the services with their states, the domains with their expiry and DNS, the open tickets.
     *
     * @return list<array{k:string,v:string}>
     */
    public function detail(Organization $organization, string $topic, string $locale = 'cs', ?AssistantScope $scope = null): array
    {
        $cs = $locale !== 'en';
        $out = [];
        $sees = fn (string $what): bool => $scope === null || $scope->{$what};
        if (in_array($topic, ['fakturace', 'cenik', 'objednavka'], true) && $sees('wallet')) {
            $credit = app(WalletService::class)->spendable($organization, $organization->currency ?? 'CZK');
            $out[] = ['k' => $cs ? 'Kredit' : 'Credit', 'v' => $credit->format($locale)];
        }
        if (in_array($topic, ['fakturace', 'cenik', 'objednavka'], true) && $sees('billing')) {
            $open = Invoice::query()->where('organization_id', $organization->id)->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE])->whereIn('type', ['invoice', 'proforma'])->orderBy('due_at')->limit(5)->get();
            foreach ($open as $invoice) {
                $kind = $invoice->type === 'proforma' ? ($cs ? 'Zálohová faktura ' : 'Proforma ') : ($cs ? 'Faktura ' : 'Invoice ');
                $out[] = ['k' => $kind.$invoice->number, 'v' => Money::minor((int) ($invoice->total_minor - $invoice->paid_minor), (string) $invoice->currency)->format($locale)
                    .($invoice->due_at ? ($cs ? ' · splatnost ' : ' · due ').$invoice->due_at->format('j. n. Y') : '').($invoice->payment_reference ? ' · VS '.$invoice->payment_reference : '')];
            }
            $pending = PaymentIntent::query()->where('organization_id', $organization->id)->where('provider', 'bank')->whereIn('state', [PaymentState::CREATED, PaymentState::PENDING_CUSTOMER])->orderByDesc('created_at')->limit(5)->get();
            foreach ($pending as $intent) {
                $instructions = (array) ($intent->raw['instructions'] ?? []);
                $out[] = ['k' => $cs ? 'Čekající převod' : 'Pending transfer', 'v' => Money::minor((int) $intent->amount_minor, (string) $intent->currency)->format($locale)
                    .(! empty($instructions['variable_symbol']) ? ' · VS '.$instructions['variable_symbol'] : '').(! empty($instructions['iban']) ? ' · IBAN '.$instructions['iban'] : '')];
            }
        }
        if (in_array($topic, ['objednavka', 'dostupnost', 'vykon', 'hry', 'zalohy', 'migrace', 'mail', 'pristup', 'latence'], true)) {
            $services = ($scope?->services() ?? Service::query()->where('organization_id', $organization->id))->whereNotIn('state', [ServiceStateMachine::TERMINATED])->orderByDesc('created_at')->limit(8)->get();
            foreach ($services as $service) {
                $out[] = ['k' => (string) ($service->label ?: ($service->hostname ?: $service->name)), 'v' => ServiceStateMachine::machine()->label($service->state).' · '.$service->product_key.($service->hostname ? ' · '.$service->hostname : '')];
            }
        }
        if (in_array($topic, ['dns', 'mail', 'objednavka'], true) && $sees('domains')) {
            foreach (Domain::query()->where('organization_id', $organization->id)->orderBy('fqdn_ascii')->limit(8)->get() as $domain) {
                $out[] = ['k' => $domain->fqdn_ascii, 'v' => ($domain->expires_at ? ($cs ? 'expiruje ' : 'expires ').$domain->expires_at->toDateString() : ($cs ? 'bez data expirace' : 'no expiry date'))
                    .' · '.($domain->usesOnhostDns() ? ($cs ? 'DNS u ONhost' : 'DNS at ONhost') : ($cs ? 'DNS jinde' : 'external DNS')).($domain->auto_renew ? ($cs ? ' · auto-obnova' : ' · auto-renew') : '')];
            }
        }
        $tickets = $sees('tickets') ? Ticket::query()->where('organization_id', $organization->id)->whereNotIn('state', [TicketStateMachine::RESOLVED, TicketStateMachine::CLOSED])->orderByDesc('updated_at')->limit(3)->get() : collect();
        foreach ($tickets as $ticket) {
            $out[] = ['k' => ($cs ? 'Tiket ' : 'Ticket ').$ticket->number, 'v' => $ticket->subject.' · '.$ticket->state];
        }

        return $out;
    }

    /**
     * The public offer, one line per product with its plans and prices (excl. VAT), plus the popular domain endings.
     *
     * @return list<string>
     */
    public function catalogLines(string $locale = 'cs'): array
    {
        $cs = $locale !== 'en';
        $catalog = app(CatalogService::class);
        $lines = [];
        $format = function (mixed $amount) use ($locale): ?string {
            if ($amount instanceof Money) {
                return $amount->format($locale);
            }
            if (is_array($amount) && isset($amount['minor'])) {
                return Money::minor((int) $amount['minor'], (string) ($amount['currency'] ?? 'CZK'))->format($locale);
            }

            return null;
        };
        foreach ($catalog->publicCatalog($locale, 'CZK') as $product) {
            if (($product['family'] ?? '') === 'addon') {
                continue;
            }
            $plans = [];
            foreach ((array) ($product['plans'] ?? []) as $plan) {
                $month = $format($plan['price']['month'] ?? null);
                $year = $format($plan['price_year']['year'] ?? null);
                $hour = $format($plan['price_hour']['hour'] ?? null);
                $price = $month !== null ? $month.($cs ? '/měs' : '/mo') : ($year !== null ? $year.($cs ? '/rok' : '/yr') : ($hour !== null ? $hour.($cs ? '/hod' : '/h') : null));
                if ($price !== null) {
                    $plans[] = $plan['name'].' '.$price;
                }
            }
            if ($plans !== []) {
                $lines[] = $product['name'].': '.implode(', ', $plans);
            }
        }
        $tlds = [];
        foreach (['cz', 'eu', 'com', 'sk'] as $tld) {
            try {
                $tlds[] = '.'.$tld.' '.$catalog->domainPrice($tld, 'CZK')->register()->format($locale).($cs ? '/rok' : '/yr');
            } catch (\Throwable) {
                // a TLD without a price is simply not quoted
            }
        }
        if ($tlds !== []) {
            $lines[] = ($cs ? 'Domény: ' : 'Domains: ').implode(', ', $tlds);
        }

        return $lines;
    }

    /**
     * Operations of the public OpenAPI contract that match the question — "GET /v1/tokens — summary" lines.
     *
     * @return list<string>
     */
    public function apiReference(string $text): array
    {
        $operations = Cache::remember('onhost:assistant:openapi', 3600, function (): array {
            $file = base_path('contracts/openapi/onhost-v1.yaml');
            if (! is_file($file)) {
                return [];
            }
            try {
                $document = (array) Yaml::parseFile($file);
            } catch (\Throwable) {
                return [];
            }
            $out = [];
            foreach ((array) ($document['paths'] ?? []) as $path => $methods) {
                foreach ((array) $methods as $method => $operation) {
                    if (! is_array($operation) || ! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                        continue;
                    }
                    $out[] = ['m' => strtoupper((string) $method), 'p' => (string) $path, 's' => (string) ($operation['summary'] ?? ''), 't' => implode(' ', (array) ($operation['tags'] ?? []))];
                }
            }

            return $out;
        });
        $stop = ['api', 'jak', 'kde', 'pro', 'pres', 'the', 'and', 'how', 'for', 'with', 'muzu', 'chci', 'mam'];
        $words = array_values(array_filter(array_unique(explode(' ', Triage::normalize($text))), fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stop, true)));
        if ($words === []) {
            return [];
        }
        $scored = [];
        foreach ($operations as $operation) {
            $haystack = Triage::normalize($operation['p'].' '.$operation['s'].' '.$operation['t']);
            $score = 0;
            foreach ($words as $w) {
                $score += substr_count($haystack, $w);
            }
            if ($score > 0) {
                $scored[] = [$score, "{$operation['m']} {$operation['p']} — {$operation['s']}"];
            }
        }
        usort($scored, fn (array $a, array $b) => $b[0] <=> $a[0]);

        return array_map(fn (array $row) => $row[1], array_slice($scored, 0, 5));
    }

    /** @return list<array{slug:string,title:string,score:int}> */
    private function articles(string $text, string $locale): array
    {
        $words = array_values(array_filter(array_unique(explode(' ', Triage::normalize($text))), fn ($w) => mb_strlen($w) >= 4));
        if ($words === []) {
            return [];
        }
        $scored = [];
        foreach (KnowledgeArticle::query()->where('state', 'published')->limit(500)->get() as $article) {
            $haystack = Triage::normalize($article->text($locale));
            $score = 0;
            foreach ($words as $w) {
                $score += substr_count($haystack, $w);
            }
            if ($score > 0) {
                $scored[] = ['slug' => $article->slug, 'title' => (string) ($article->title[$locale] ?? $article->title['cs'] ?? $article->slug), 'score' => $score];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 3);
    }

    /** Deterministic answer bank keyed by topic, with live facts, topic details, the price list and the API reference. */
    private function rules(array $triage, array $facts, array $articles, array $detail, array $reference, array $catalog, string $locale): string
    {
        $cs = $locale !== 'en';
        $picked = $detail !== [] ? array_slice($detail, 0, 8) : array_slice($facts, 0, 4);
        $factLine = $picked !== [] ? ("\n\n".($cs ? 'Co vidím ve vašem účtu: ' : 'What I see in your account: ').implode('; ', array_map(fn ($f) => "{$f['k']}: {$f['v']}", $picked))) : '';
        $offer = $catalog !== [] ? ("\n\n".($cs ? 'Aktuální nabídka (bez DPH): ' : 'Current offer (excl. VAT): ').implode(' · ', array_slice($catalog, 0, 8))) : '';
        $ref = $reference !== [] ? ("\n\n".($cs ? 'Související volání API: ' : 'Related API calls: ').implode('; ', $reference)) : '';
        $kb = $articles !== [] ? ("\n\n".($cs ? 'Související návody: ' : 'Related guides: ').implode(', ', array_map(fn ($a) => $a['title'], $articles))) : '';
        $bank = $cs ? [
            'dostupnost' => 'Nejdřív ověřím stav platformy a vaší služby. Pokud běží incident, uvidíte ho na stavové stránce; pokud ne, zkuste restart služby z panelu (u VPS a herních serverů je k dispozici tlačítko Restart) a sledujte konzoli. Když to nepomůže, předám to podpoře i s výpisem.',
            'latence' => 'Latence a ztráty paketů se nejlépe dokládají traceroutem a přesným časem. Pošlete mi obojí, porovnám to s telemetrií uplinků a případně předám síťařům.',
            'vykon' => 'U výkonu se dívám na CPU steal, IO čekání a RAM. Konfigurátor v panelu umožňuje navýšit vCPU/RAM bez reinstalace (změna proběhne jako operace s restartem u disku). Pokud jde o webhosting, pomůže PHP OPcache a méně pluginů.',
            'fakturace' => 'Doklady (PDF i UBL) najdete v klientské sekci pod Fakturací; kredit se dobíjí kartou, převodem nebo z peněženky. Účtenka k dobití vzniká automaticky po přijetí platby, vyúčtování služeb hned po platbě z peněženky.',
            'zalohy' => 'Zálohy VPS běží na Proxmox Backup Server, webhosting má denní zálohy s retencí podle tarifu. Obnovu spustíte z panelu (Zálohy → Obnovit); u VPS musí být stroj před obnovou na místě vypnutý.',
            'pristup' => 'Heslo změníte v Nastavení, dvoufázové ověření zapnete tamtéž (TOTP + záložní kódy). SSH klíče se u VPS zapisují přes cloud-init při zřízení; přidat další lze v detailu služby.',
            'dns' => 'DNS zóna se upravuje ve dvou krocích: nejdřív dávka změn, potom výslovná publikace (a kdykoliv návrat na starší verzi). Změny nameserverů u registru trvají až 24 hodin.',
            'mail' => 'Pro doručitelnost potřebujete SPF, DKIM a DMARC; pokud máte DNS u nás, záznamy se doplní automaticky při zřízení poštovní domény. Zkontroluji, jestli je odesílání zapnuté a DKIM publikované.',
            'migrace' => 'Migraci webu nebo VPS řešíme jako naplánované okno: nahlásíte zdroj a rozsah, my připravíme cíl a data přeneseme. Během okna běží původní hosting dál.',
            'objednavka' => 'Objednávka projde stavy zaplaceno → provisioning → aktivní; u domén čeká na registr. Stav sledujete v klientské sekci, e-mail chodí při každé změně.',
            'hry' => 'Herní server má konzoli a správu modů v panelu; restart, zálohu a změnu verze provedete tam. Pokud se server nespouští, zkontrolujte konzoli — nejčastěji jde o chybějící RAM nebo poškozený svět.',
            'api' => 'API klíč vytvoříte v panelu v Nastavení → API klíče a webhooky: zvolíte rozsah práv (jen čtení, provoz služeb, vše) a platnost, klíč se zobrazí jen jednou a posílá se v hlavičce Authorization: Bearer. Webhooky zakládáte tamtéž — podepsané události o objednávkách, službách, fakturách a doménách chodí na vaši HTTPS adresu. Dokumentace API (OpenAPI 3.1) je na /dokumentace/api.',
            'cenik' => 'Ceny uvádíme bez DPH a účtujeme celé období dopředu; kredit se čerpá první, jinak vystavíme zálohovou fakturu (bankovní převod nebo karta). Domény registrujeme na 1–10 let za ceníkovou cenu. Objednat lze v panelu přes „Nová služba“.',
        ] : [
            'dostupnost' => 'I first check the platform status and your service. If an incident is running you will see it on the status page; otherwise try restarting the service from the panel and watch the console. If that does not help, I will hand it over with the log.',
            'latence' => 'Latency and packet loss are best documented with a traceroute and the exact time. Send both and I will compare them with uplink telemetry or pass it to the network team.',
            'vykon' => 'For performance I look at CPU steal, IO wait and RAM. The configurator lets you add vCPU/RAM without reinstalling. On web hosting, OPcache and fewer plugins help most.',
            'fakturace' => 'Documents (PDF and UBL) are under Billing in the customer panel; credit is topped up by card, transfer or from the wallet. Receipts are issued automatically once payment arrives.',
            'zalohy' => 'VPS backups run on Proxmox Backup Server, web hosting has daily backups with plan-based retention. Restore from the panel (Backups → Restore); a VPS must be stopped for an in-place restore.',
            'pristup' => 'Change the password in Settings and enable two-factor authentication there (TOTP + recovery codes). SSH keys for VPS are applied via cloud-init; more can be added in the service detail.',
            'dns' => 'The DNS zone is edited in two steps: a batch of changes, then an explicit publish (with rollback to any version). Nameserver changes at the registry take up to 24 hours.',
            'mail' => 'Deliverability needs SPF, DKIM and DMARC; with DNS at ONhost the records are added automatically when the mail domain is created. I will check that sending is enabled and DKIM is published.',
            'migrace' => 'Migrations run as a planned window: you report the source and scope, we prepare the target and move the data while the old hosting keeps running.',
            'objednavka' => 'An order moves paid → provisioning → active; domains wait for the registry. Track it in the customer panel, an e-mail goes out on every change.',
            'hry' => 'A game server has its console and mod management in the panel; restart, backup and version changes are there. If it does not start, check the console — usually memory or a corrupted world.',
            'api' => 'Create an API key in the panel under Settings → API keys and webhooks: pick the scope (read only, operate services, everything) and the validity; the key is shown once and travels in the Authorization: Bearer header. Webhooks are created there too — signed events about orders, services, invoices and domains go to your HTTPS address. The API documentation (OpenAPI 3.1) is at /dokumentace/api.',
            'cenik' => 'Prices are quoted excl. VAT and charged for the whole period up front; credit is used first, otherwise we issue a proforma (bank transfer or card). Domains are registered for 1–10 years at the list price. Order in the panel through "New service".',
        ];
        $text = $bank[$triage['topic']] ?? ($cs ? 'Tohle si netroufnu odpovědět z hlavy. Napište „podpora“ a předám to kolegovi i s celým kontextem, ať se vás nikdo neptá dvakrát.' : 'I would rather not guess here. Type "support" and I will hand it to a colleague with the full context.');

        return $text.$factLine.$offer.$ref.$kb;
    }

    /** SAFE_WRITE proposals: executed only by the user through the regular API after confirmation (blueprint §69.3). */
    private function actions(string $topic, ?Organization $organization, array $facts, array $articles, string $locale, ?AssistantScope $scope = null): array
    {
        $cs = $locale !== 'en';
        $actions = [];
        $sees = fn (string $what): bool => $scope === null || $scope->{$what};
        foreach ($articles as $a) {
            $actions[] = ['kind' => 'link', 'label' => $a['title'], 'href' => '/napoveda/'.$a['slug']];
        }
        if ($organization !== null && $topic === 'fakturace' && $sees('billing') && $scope?->staff !== true) {
            $invoice = Invoice::query()->where('organization_id', $organization->id)->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE])->where('type', 'invoice')->orderBy('due_at')->first();
            if ($invoice !== null) {
                $actions[] = ['kind' => 'pay', 'label' => ($cs ? 'Zaplatit ' : 'Pay ').$invoice->number, 'invoice_id' => $invoice->id, 'confirm' => true, 'class' => 'SAFE_WRITE'];
            }
        }
        if ($organization !== null && in_array($topic, ['dostupnost', 'hry', 'vykon'], true)) {
            $service = ($scope?->services() ?? Service::query()->where('organization_id', $organization->id))->whereIn('family', ['cloud', 'game'])->where('state', 'ACTIVE')->orderByDesc('activated_at')->first();
            if ($service !== null && ($scope === null || $scope->mayRun($service, 'power'))) {
                $actions[] = ['kind' => 'restart', 'label' => ($cs ? 'Restartovat ' : 'Restart ').$service->name, 'service_id' => $service->id, 'power_action' => 'reboot', 'confirm' => true, 'class' => 'SAFE_WRITE'];
            }
        }
        // where to go next in the panel or the documentation (links open the real view; panel links navigate in place)
        if ($topic === 'api') {
            $actions[] = ['kind' => 'link', 'label' => $cs ? 'Dokumentace API' : 'API documentation', 'href' => '/dokumentace/api'];
            if ($organization !== null) {
                $actions[] = ['kind' => 'link', 'label' => $cs ? 'API klíče v panelu' : 'API keys in the panel', 'href' => '/panel#/api'];
            }
        }
        if ($organization !== null && $topic === 'fakturace' && $sees('billing')) {
            $actions[] = ['kind' => 'link', 'label' => $cs ? 'Otevřít Fakturaci' : 'Open Billing', 'href' => '/panel#/fakturace'];
        }
        if ($organization !== null && $topic === 'dns' && $sees('domains')) {
            $actions[] = ['kind' => 'link', 'label' => $cs ? 'Domény a DNS' : 'Domains and DNS', 'href' => '/panel#/sluzba/domain'];
        }
        if ($organization !== null && in_array($topic, ['cenik', 'objednavka'], true)) {
            $actions[] = ['kind' => 'link', 'label' => $cs ? 'Přehled a objednávka' : 'Overview and ordering', 'href' => '/panel#/prehled'];
        }
        if ($scope === null || (! $scope->staff && $scope->ticketsWrite)) {
            $actions[] = ['kind' => 'ticket', 'label' => $cs ? 'Spojit s podporou' : 'Contact support', 'confirm' => true, 'class' => 'SAFE_WRITE'];
        }
        $actions[] = ['kind' => 'link', 'label' => $cs ? 'Stav služeb' : 'Status page', 'href' => '/stav'];

        return $actions;
    }

    private function handoff(AiRun $run, array $transcript, array $triage, array $facts, string $reason, Organization $organization, ?User $user, CommandContext $context): array
    {
        $first = collect($transcript)->firstWhere('role', 'user')['content'] ?? 'Požadavek z asistenta';
        $summary = $this->summary($triage, $facts, $transcript, $organization->locale ?? 'cs');
        $body = "AI shrnutí (vygenerováno asistentem):\n{$summary}\n\n--- přepis ---\n".implode("\n", array_map(fn ($m) => strtoupper((string) $m['role']).': '.$this->redactor->redactString((string) $m['content']), $transcript));
        $ticket = $this->tickets->create([
            'subject' => mb_substr((string) $first, 0, 120), 'body' => $body, 'category' => $triage['topic'] === 'ostatni' ? null : $triage['topic'], 'priority' => in_array($reason, ['security'], true) ? 'vysoka' : null, 'channel' => 'ai',
            'tags' => ['ai-handoff', $reason], 'email' => $user?->email ?? $organization->billing_email,
        ], $context, $organization, $user);
        $ticket->forceFill(['ai_summary' => $summary])->save();
        Handoff::query()->create(['ai_run_id' => $run->id, 'ticket_id' => $ticket->id, 'reason' => $reason, 'diagnostics' => ['facts' => $facts, 'topic' => $triage]]);
        $this->outbox->publish(GenericEvent::of('ticket.handoff', 'ticket', $ticket->id, ['number' => $ticket->number, 'reason' => $reason, 'topic' => $triage['topic']], $organization->id));

        return ['ticket_id' => $ticket->id, 'number' => $ticket->number, 'reason' => $reason];
    }

    private function summary(array $triage, array $facts, array $transcript, string $locale): string
    {
        $userTurns = array_values(array_filter($transcript, fn ($m) => ($m['role'] ?? '') === 'user'));
        $lines = [($locale === 'en' ? 'Topic: ' : 'Téma: ').$triage['label'], ($locale === 'en' ? 'Customer said: ' : 'Zákazník napsal: ').mb_substr((string) (end($userTurns)['content'] ?? ''), 0, 300)];
        foreach (array_slice($facts, 0, 5) as $f) {
            $lines[] = "{$f['k']}: {$f['v']}";
        }

        return implode("\n", $lines);
    }

    /** The topic of the conversation: the current message when it is clear, otherwise the last clear topic in the session (a "talk to a human" turn keeps the billing context). */
    private function sessionTopic(array $transcript, array $triage): array
    {
        if ($triage['hits'] > 0) {
            return $triage;
        }
        $userTurns = array_reverse(array_values(array_filter($transcript, fn ($m) => ($m['role'] ?? '') === 'user')));
        foreach (array_slice($userTurns, 1) as $turn) {
            $previous = Triage::classify((string) $turn['content']);
            if ($previous['hits'] > 0) {
                return $previous;
            }
        }

        return $triage;
    }

    private function repeated(array $transcript, string $topic): bool
    {
        $userTurns = array_values(array_filter($transcript, fn ($m) => ($m['role'] ?? '') === 'user'));
        if (count($userTurns) < 3) {
            return false;
        }
        $same = 0;
        foreach (array_slice($userTurns, -3) as $turn) {
            if (Triage::classify((string) $turn['content'])['topic'] === $topic && $topic !== 'ostatni') {
                $same++;
            }
        }

        return $same >= 3;
    }

    /** @return array{0:?string,1:array{input_tokens:int,output_tokens:int},2:?string,3:list<array<string,mixed>>} */
    /** Proposals first (deduplicated by service and action), then the topic links. @return list<array<string,mixed>> */
    private static function mergeProposals(array $proposals, array $actions): array
    {
        $seen = [];
        $out = [];
        foreach ($proposals as $p) {
            $key = ($p['service_id'] ?? '').'|'.($p['action'] ?? '').'|'.json_encode($p['params'] ?? []);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
        }
        foreach ($actions as $a) {
            if (($a['kind'] ?? '') === 'restart' && isset($seen[($a['service_id'] ?? '').'|power|'.json_encode(['power_action' => $a['power_action'] ?? 'reboot'])])) {
                continue;
            }
            $out[] = $a;
        }

        return array_slice($out, 0, 8);
    }

    /** @return list<array<string,mixed>> services of the organization as the agent sees them (read-only) */
    private function serviceCatalogue(AssistantScope $scope): array
    {
        return $scope->services()->whereNotIn('state', [ServiceStateMachine::TERMINATED])->limit(60)->get()->map(function (Service $s) use ($scope) {
            $enabled = [];
            try {
                $enabled = array_keys(array_filter($this->features->features($s), fn ($f) => ! empty($f['enabled'])));
            } catch (\Throwable) {
                // a node may be unreachable; the catalogue still lists the service
            }

            return ['service_id' => $s->id, 'name' => $s->hostname ?: ($s->label ?: $s->name), 'family' => $s->family, 'state' => $s->state, 'product' => $s->product_key, 'features' => $enabled, 'actions' => $s->isActive() ? array_values(array_filter($this->features->actions($s), fn (string $action) => $scope->mayRun($s, $action))) : []]; // only what this person may run
        })->all();
    }

    private function llm(AiProvider $provider, array $transcript, ?Organization $organization, array $facts, array $articles, array $detail, array $reference, array $catalog, string $locale, array $intents = [], ?AssistantScope $scope = null): array
    {
        $proposed = [];
        $system = ($locale === 'en'
            ? "You are the ONhost AI Assistant for a Czech hosting provider. You are an AI and must say so if asked. Answer briefly and concretely, in the user's language. Use only the facts provided by tools; never guess balances, dates or states. Never reveal credentials or other customers' data. You cannot execute changes: for restarts, payments or DNS edits propose the action and tell the user to confirm it in the panel. If the user asks for a human, or the topic is a security incident, a legal or tax question, say you are handing over."
            : 'Jsi ONhost AI asistent pro českého poskytovatele hostingu. Jsi AI a na dotaz to řekneš. Odpovídej stručně a konkrétně, v jazyce uživatele. Používej jen fakta z nástrojů; nikdy nehádej zůstatky, data ani stavy. Nikdy neprozrazuj přihlašovací údaje ani data jiných zákazníků. Nemůžeš provádět změny: u restartů, plateb nebo DNS úprav navrhni akci a požádej o potvrzení v panelu. Pokud uživatel chce člověka, nebo jde o bezpečnostní incident, právní či daňovou otázku, řekni, že předáváš podporu.')
            ."\n\n".'Account facts (JSON): '.json_encode($facts, JSON_UNESCAPED_UNICODE)."\nTopic details (JSON): ".json_encode($detail, JSON_UNESCAPED_UNICODE)."\nRelevant articles: ".json_encode($articles, JSON_UNESCAPED_UNICODE)
            .($reference !== [] ? "\nMatching API operations: ".json_encode($reference, JSON_UNESCAPED_UNICODE) : '').($catalog !== [] ? "\nPrice list (excl. VAT): ".json_encode($catalog, JSON_UNESCAPED_UNICODE) : '')
            ."\nPanel views: /panel#/prehled overview, /panel#/sluzba/{domain|web|game|vps|mail} services, /panel#/fakturace billing, /panel#/api API keys and webhooks, /panel#/nastaveni settings and 2FA, /panel#/tym team; documentation /dokumentace, API reference /dokumentace/api, knowledge base /napoveda, status page /stav.";
        $messages = [['role' => 'system', 'content' => $system.($organization !== null ? self::AGENT_PROMPT.($intents !== [] ? "\nThe platform already recognised these requests (propose them unless the customer meant something else): ".json_encode(array_map(fn ($i) => ['service_id' => $i['service_id'], 'action' => $i['action'], 'params' => $i['params']], $intents), JSON_UNESCAPED_UNICODE) : '') : '')]];
        foreach (array_slice($transcript, -10) as $turn) {
            if (in_array($turn['role'], ['user', 'assistant'], true)) {
                $messages[] = ['role' => $turn['role'], 'content' => (string) $turn['content']];
            }
        }
        $tools = [
            ['name' => 'get_account_facts', 'description' => 'Current invoices, orders, services and expiring domains of the signed-in organization (read-only).', 'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []]],
            ['name' => 'search_knowledge_base', 'description' => 'Search ONhost knowledge base articles.', 'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']]],
            ['name' => 'get_topic_details', 'description' => 'Details of the signed-in organization for one topic (read-only): documents to pay with variable symbols and credit (fakturace), services with states (objednavka, dostupnost, vykon, hry), domains with expiry and DNS (dns), open tickets.', 'parameters' => ['type' => 'object', 'properties' => ['topic' => ['type' => 'string', 'enum' => ['fakturace', 'objednavka', 'dostupnost', 'vykon', 'hry', 'dns', 'mail', 'zalohy', 'pristup']]], 'required' => ['topic']]],
            ['name' => 'get_price_list', 'description' => 'The public ONhost price list: products, plans and monthly/yearly prices excl. VAT, popular domain endings.', 'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []]],
            ['name' => 'search_api_reference', 'description' => 'Search the public ONhost API (OpenAPI 3.1) for operations matching a query; returns method, path and summary.', 'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']]],
        ];
        if ($scope !== null && $scope->seesAnyService()) {
            $tools[] = ['name' => 'list_services', 'description' => 'The signed-in organization\'s services with their state, enabled features and the actions each one accepts (read-only).', 'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []]];
            $tools[] = ['name' => 'get_service_status', 'description' => 'Recent operations, uptime monitoring and quotas of one service (read-only).', 'parameters' => ['type' => 'object', 'properties' => ['service_id' => ['type' => 'string']], 'required' => ['service_id']]];
            $tools[] = ['name' => 'get_service_resource', 'description' => 'One read-only listing of a service: web — databases, cron, subdomains, certificate, redirect, php_settings, quotas, monitoring, staging, deploy, wordpress, cdn; mail — mailboxes, aliases, dkim, mail_forwards, mail_lists, mail_usage; game — status, schedules, allocations; cloud — snapshots, firewall; every family — backups (the last backups with their state, date and size). Passwords are never included.', 'parameters' => ['type' => 'object', 'properties' => ['service_id' => ['type' => 'string'], 'kind' => ['type' => 'string']], 'required' => ['service_id', 'kind']]];
            $tools[] = ['name' => 'propose_service_action', 'description' => 'Propose one action on a service; the customer confirms it with a button. action is one of the service\'s actions (e.g. backup, power with params.power_action reboot|shutdown|start, deploy.run, wp.update, wp.cache with params.enabled, staging.refresh, staging.push, cdn.purge, ssl.issue, https.force, php.set with params.version).', 'parameters' => ['type' => 'object', 'properties' => ['service_id' => ['type' => 'string'], 'action' => ['type' => 'string'], 'params' => ['type' => 'object'], 'label' => ['type' => 'string', 'description' => 'short button label in the customer\'s language']], 'required' => ['service_id', 'action']]];
        }
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $called = [];
        $model = null;
        for ($round = 0; $round < 3; $round++) {
            $result = $provider->chat($messages, $tools, ['max_tokens' => 700]);
            $usage['input_tokens'] += $result['usage']['input_tokens'];
            $usage['output_tokens'] += $result['usage']['output_tokens'];
            $model = $result['model'];
            if ($result['tool_calls'] === []) {
                return [$result['content'] !== null ? trim((string) $result['content']) : null, $usage, $model, $called, $proposed];
            }
            $messages[] = ['role' => 'assistant', 'content' => $result['content'], 'tool_calls' => $result['tool_calls']];
            foreach ($result['tool_calls'] as $call) {
                $output = match ($call['name']) {
                    'get_account_facts' => $organization !== null ? $this->facts($organization, $locale, $scope) : [],
                    'search_knowledge_base' => $this->articles((string) ($call['arguments']['query'] ?? ''), $locale),
                    'get_topic_details' => $organization !== null ? $this->detail($organization, (string) ($call['arguments']['topic'] ?? 'objednavka'), $locale, $scope) : [],
                    'get_price_list' => $this->catalogLines($locale),
                    'search_api_reference' => $this->apiReference((string) ($call['arguments']['query'] ?? '')),
                    'list_services' => $scope !== null ? $this->serviceCatalogue($scope) : [],
                    'get_service_status' => $scope !== null ? $this->serviceStatus($scope, (string) ($call['arguments']['service_id'] ?? '')) : [],
                    'get_service_resource' => $scope !== null ? $this->serviceResource($scope, (string) ($call['arguments']['service_id'] ?? ''), (string) ($call['arguments']['kind'] ?? '')) : [],
                    'propose_service_action' => $scope !== null ? $this->propose($scope, (array) $call['arguments'], $locale, $proposed) : ['ok' => false, 'error' => 'sign in first'],
                    default => ['error' => 'unknown tool'],
                };
                $called[] = ['tool' => $call['name'], 'arguments' => $call['arguments']];
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'name' => $call['name'], 'content' => json_encode($output, JSON_UNESCAPED_UNICODE)];
            }
        }

        return [null, $usage, $model, $called, $proposed];
    }

    /** @return array<string,mixed> operations, monitoring and quotas of one service (read-only, for the agent) */
    private function serviceStatus(AssistantScope $scope, string $serviceId): array
    {
        $service = $scope->service($serviceId);
        if ($service === null) {
            return ['error' => 'unknown service'];
        }
        $out = ['service_id' => $service->id, 'name' => $service->hostname ?: $service->name, 'state' => $service->state, 'operations' => []];
        foreach (Operation::query()->where('service_id', $service->id)->orderByDesc('queued_at')->limit(5)->get() as $op) {
            $out['operations'][] = ['action' => $op->desired['action'] ?? $op->kind, 'state' => $op->state, 'at' => ($op->finished_at ?? $op->queued_at)?->toIso8601String(), 'error' => $op->error['message'] ?? null];
        }
        foreach (['monitoring', 'quotas', 'wordpress', 'deploy', 'staging'] as $kind) {
            try {
                $out[$kind] = $this->features->resources($service, $kind);
            } catch (\Throwable) {
                // not offered for this service
            }
        }

        return $out;
    }

    /**
     * One listing of one service the person may see. Read-only, from the allow-list of its family, redacted, and cut to a
     * size a model can take — a list of four hundred cron jobs is answered with the first of them and the count.
     *
     * @return array<string,mixed>
     */
    private function serviceResource(AssistantScope $scope, string $serviceId, string $kind): array
    {
        $service = $scope->service($serviceId);
        if ($service === null) {
            return ['error' => 'unknown service'];
        }
        $allowed = self::READABLE[$service->family] ?? [];
        if (! in_array($kind, $allowed, true)) {
            return ['error' => 'not readable here', 'available' => $allowed];
        }
        try {
            $data = $kind === 'backups'
                ? Backup::query()->where('service_id', $service->id)->orderByDesc('started_at')->limit(8)->get()->map(fn ($b) => ['id' => $b->id, 'kind' => $b->kind, 'state' => $b->state, 'started_at' => $b->started_at?->toIso8601String(), 'finished_at' => $b->finished_at?->toIso8601String(), 'size_bytes' => $b->size_bytes, 'protected' => (bool) $b->protected])->all()
                : $this->features->resources($service, $kind);
        } catch (DomainError $e) {
            return ['error' => $e->error]; // not part of the plan, or the node does not answer right now
        } catch (\Throwable) {
            return ['error' => 'the service does not answer right now'];
        }
        $data = $this->redactor->redact($data);
        $total = is_array($data) && array_is_list($data) ? count($data) : null;
        if ($total !== null && $total > 25) {
            $data = array_slice($data, 0, 25);
        }
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 6000) {
            return ['service_id' => $service->id, 'kind' => $kind, 'total' => $total, 'truncated' => true, 'data' => mb_substr($json, 0, 6000)];
        }

        return ['service_id' => $service->id, 'kind' => $kind, 'total' => $total, 'data' => $data];
    }

    /** The LLM's proposal, validated against what the service really offers; becomes a confirm button. @return array<string,mixed> */
    private function propose(AssistantScope $scope, array $arguments, string $locale, array &$proposed): array
    {
        $service = $scope->service((string) ($arguments['service_id'] ?? ''));
        $action = (string) ($arguments['action'] ?? '');
        if ($service === null || ! $service->isActive()) {
            return ['ok' => false, 'error' => 'unknown or inactive service'];
        }
        if (in_array($action, ['terminate', 'purge', 'restore', 'archive.restore', 'rollback_snapshot', 'suspend', 'resume', 'resize'], true) || ! in_array($action, $this->features->actions($service), true)) {
            return ['ok' => false, 'error' => 'action not available for this service', 'available' => $this->features->actions($service)];
        }
        if (! $scope->mayRun($service, $action)) { // a button that ends in "forbidden" is not offered
            return ['ok' => false, 'error' => 'the signed-in person may view this service but not change it'];
        }
        $params = is_array($arguments['params'] ?? null) ? $arguments['params'] : [];
        if ($action === 'staging.push') {
            $params['confirm'] = true;
        }
        $name = (string) ($service->hostname ?: ($service->label ?: $service->name));
        $label = trim((string) ($arguments['label'] ?? '')) ?: ($locale === 'en' ? ucfirst(str_replace('.', ' ', $action)).' '.$name : $action.' · '.$name);
        $proposed[] = ['kind' => 'service_action', 'label' => mb_substr($label, 0, 80), 'service_id' => $service->id, 'action' => $action, 'params' => $params, 'confirm' => true, 'class' => $scope->staff ? 'STAFF_WRITE' : 'SAFE_WRITE', 'service' => $name];

        return ['ok' => true, 'proposed' => $label, 'note' => 'shown to the customer as a button to confirm'];
    }

    /** Human takeover: a staff member can read what the AI did (blueprint §69.4). */
    public function transcriptForTicket(Ticket $ticket): array
    {
        $run = AiRun::query()->where('ticket_id', $ticket->id)->orderByDesc('created_at')->first();

        return $run === null ? [] : ['run_id' => $run->id, 'provider' => $run->provider, 'model' => $run->model, 'summary' => $run->summary, 'tools_called' => $run->tools_called, 'transcript' => $run->transcript];
    }
}
