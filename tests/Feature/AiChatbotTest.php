<?php

declare(strict_types=1);

use App\Domains\Ai\Services\AiChatbotService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * The rebuilt AI chatbot: intent routing, category menu, grounded answers and
 * per-turn quick-replies — replacing the old widget that always drafted the
 * same support reply.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('opens with a categorised menu when the message is empty', function (): void {
    $user = customerUser();

    $result = app(AiChatbotService::class)->reply($user, '');

    expect($result['category'])->toBeNull()
        ->and($result['suggestions'])->toHaveCount(count(AiChatbotService::CATEGORIES))
        ->and(collect($result['suggestions'])->pluck('message')->all())
        ->toContain('kategorie:objednavka', 'kategorie:fakturace', 'kategorie:domeny');
});

it('routes a "kategorie:" chip to that category overview with deep links', function (): void {
    $user = customerUser();

    $result = app(AiChatbotService::class)->reply($user, 'kategorie:fakturace');

    expect($result['category'])->toBe('fakturace')
        ->and(collect($result['links'])->pluck('url')->all())
        ->toContain(route('panel.billing.invoices'));
});

it('answers a free-text billing question with a payment link', function (): void {
    $user = customerUser();

    $result = app(AiChatbotService::class)->reply($user, 'Jak zaplatím fakturu?');

    expect($result['category'])->toBe('fakturace')
        ->and($result['reply'])->toContain('Comgate')
        ->and(collect($result['links'])->pluck('url')->all())->toContain(route('panel.billing.invoices'));
});

it('answers a DNS question with a technical explanation', function (): void {
    $user = customerUser();

    $result = app(AiChatbotService::class)->reply($user, 'Co znamená CNAME záznam?');

    expect($result['category'])->toBe('domeny')
        ->and($result['reply'])->toContain('CNAME');
});

it('gives DIFFERENT answers to different questions (no more repeated reply)', function (): void {
    $user = customerUser();
    $svc  = app(AiChatbotService::class);

    $a = $svc->reply($user, 'Jak zapnu dvoufázové ověření?')['reply'];
    $b = $svc->reply($user, 'Jak restartuji svůj VPS server?')['reply'];
    $c = $svc->reply($user, 'Jaký tarif pro WordPress?')['reply'];

    expect($a)->not->toBe($b)
        ->and($b)->not->toBe($c)
        ->and($a)->not->toBe($c);
});

it('is accent-insensitive when matching intents', function (): void {
    $user = customerUser();

    $withDiacritics    = app(AiChatbotService::class)->reply($user, 'Jak změním verzi PHP?');
    $withoutDiacritics = app(AiChatbotService::class)->reply($user, 'jak zmenim verzi php');

    expect($withDiacritics['category'])->toBe('technicka')
        ->and($withoutDiacritics['category'])->toBe('technicka');
});

it('grounds the billing overview in the customer\'s open invoices', function (): void {
    $user = customerUser();
    // An order + proforma leaves one open (Sent) invoice.
    placeOrder($user);

    $result = app(AiChatbotService::class)->reply($user, 'kategorie:fakturace');

    expect($result['reply'])->toContain('neuhrazenou');
});

it('every suggestion carries a non-empty label and message', function (): void {
    $user = customerUser();
    $svc  = app(AiChatbotService::class);

    foreach (['kategorie:objednavka', 'kategorie:technicka', 'Jak nainstaluji WordPress?'] as $msg) {
        foreach ($svc->reply($user, $msg)['suggestions'] as $s) {
            expect($s['label'])->not->toBe('')
                ->and($s['message'])->not->toBe('');
        }
    }
});

// ── HTTP endpoint ───────────────────────────────────────────────────────────────

it('the chat endpoint returns a structured JSON reply', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), ['message' => 'Jak zaplatím fakturu?'])
        ->assertOk()
        ->assertJsonStructure(['reply', 'suggestions', 'links', 'category']);
});

it('the chat endpoint accepts a category-only pick', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), ['category' => 'domeny'])
        ->assertOk()
        ->assertJsonPath('category', 'domeny');
});

it('the chat endpoint requires authentication', function (): void {
    $this->postJson(route('panel.ai.chat'), ['message' => 'ahoj'])
        ->assertUnauthorized(); // guest JSON request → 401
});
