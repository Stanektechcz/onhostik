<?php

declare(strict_types=1);

use App\Domains\Support\Enums\ChatConversationStatus;
use App\Domains\Support\Models\SupportChatConversation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\SupportChatService;
use App\Notifications\ChatEscalatedNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Database-backed support chat: AI bot turns are persisted, the customer can
 * escalate to a live agent, and the agent replies from the admin inbox.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('persists the customer message and the bot reply to the database', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), ['message' => 'Jak zaplatím fakturu?'])
        ->assertOk();

    $conversation = SupportChatConversation::where('started_by', $user->id)->firstOrFail();

    expect($conversation->messages()->where('role', 'user')->count())->toBe(1)
        ->and($conversation->messages()->where('role', 'bot')->count())->toBe(1)
        ->and($conversation->status)->toBe(ChatConversationStatus::Bot);
});

it('reuses one open conversation across turns', function (): void {
    $user = customerUser();

    $this->actingAs($user)->postJson(route('panel.ai.chat'), ['message' => 'první dotaz na dns']);
    $this->actingAs($user)->postJson(route('panel.ai.chat'), ['message' => 'druhý dotaz na fakturu']);

    expect(SupportChatConversation::where('started_by', $user->id)->count())->toBe(1);
});

it('escalates to a live agent and stops the bot from answering', function (): void {
    $user = customerUser();

    // Start a conversation, then escalate.
    $this->actingAs($user)->postJson(route('panel.ai.chat'), ['message' => 'potřebuji člověka']);
    $this->actingAs($user)->postJson(route('panel.ai.escalate'))->assertOk();

    $conversation = SupportChatConversation::where('started_by', $user->id)->firstOrFail();
    expect($conversation->fresh()->status)->toBe(ChatConversationStatus::WaitingAgent);

    // A further customer message is stored but the bot must NOT reply.
    $botBefore = $conversation->messages()->where('role', 'bot')->count();
    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), ['message' => 'jsi tam?'])
        ->assertOk()
        ->assertJsonPath('escalated', true);

    expect($conversation->messages()->where('role', 'bot')->count())->toBe($botBefore);
});

it('lets an admin agent reply, which the customer poll then returns', function (): void {
    $customerUser = customerUser();
    $chat = app(SupportChatService::class);
    $conversation = $chat->openConversationFor($customerUser);
    $chat->escalate($conversation);

    // Agent replies from the admin inbox.
    $this->actingAs(adminUser())
        ->post(route('admin.chat.reply', $conversation), ['body' => 'Dobrý den, pomůžu vám.'])
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ChatConversationStatus::AgentActive)
        ->and($conversation->messages()->where('role', 'agent')->count())->toBe(1);

    // The customer's poll returns the agent message.
    $this->actingAs($customerUser)
        ->getJson(route('panel.ai.poll', ['after' => 0]))
        ->assertOk()
        ->assertJsonFragment(['role' => 'agent', 'body' => 'Dobrý den, pomůžu vám.']);
});

it('lets an admin close a conversation', function (): void {
    $customerUser = customerUser();
    $chat = app(SupportChatService::class);
    $conversation = $chat->openConversationFor($customerUser);
    $chat->escalate($conversation);

    $this->actingAs(adminUser())
        ->post(route('admin.chat.close', $conversation))
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ChatConversationStatus::Closed);
});

it('renders the admin chat inbox and a conversation', function (): void {
    $customerUser = customerUser();
    $chat = app(SupportChatService::class);
    $conversation = $chat->openConversationFor($customerUser);
    $chat->userMessage($conversation, 'Ahoj', $customerUser);
    $chat->escalate($conversation);

    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.chat.index'))
        ->assertOk()
        ->assertSee('Živá podpora');

    $this->actingAs($admin)->get(route('admin.chat.show', $conversation))
        ->assertOk()
        ->assertSee('Ahoj');
});

it('forbids a customer from the admin chat inbox', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.chat.index'))
        ->assertForbidden();
});

// ── Phase B extensions ──────────────────────────────────────────────────────────

it('notifies operators when a chat is escalated', function (): void {
    Notification::fake();
    $admin = adminUser(); // ensures the admin role exists + a recipient
    $user  = customerUser();

    app(SupportChatService::class)->escalate(app(SupportChatService::class)->openConversationFor($user));

    Notification::assertSentTo($admin, ChatEscalatedNotification::class);
});

it('converts a conversation into a support ticket with the transcript', function (): void {
    $user = customerUser();
    $chat = app(SupportChatService::class);
    $conversation = $chat->openConversationFor($user);
    $chat->userMessage($conversation, 'Web nefunguje', $user);
    $chat->botMessage($conversation, 'Zkontrolujte prosím…');

    $this->actingAs(adminUser())
        ->post(route('admin.chat.to-ticket', $conversation))
        ->assertRedirect();

    $ticket = SupportTicket::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    expect($ticket->subject)->toContain('Web nefunguje')
        ->and($ticket->messages()->count())->toBe(2)
        ->and($conversation->fresh()->status)->toBe(ChatConversationStatus::Closed);
});

it('lets a customer attach a file to the chat and download it', function (): void {
    Storage::fake('local');
    $user = customerUser();

    $response = $this->actingAs($user)
        ->post(route('panel.ai.upload'), ['file' => UploadedFile::fake()->image('screenshot.png')])
        ->assertOk()
        ->assertJsonPath('meta.attachment.name', 'screenshot.png');

    $messageId = $response->json('id');

    // Owner can download.
    $this->actingAs($user)->get(route('panel.ai.attachment', $messageId))->assertOk();

    // A different customer cannot.
    $this->actingAs(customerUser())->get(route('panel.ai.attachment', $messageId))->assertForbidden();
});

it('rejects an over-sized or disallowed attachment', function (): void {
    Storage::fake('local');
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.ai.upload'), ['file' => UploadedFile::fake()->create('malware.exe', 100)])
        ->assertSessionHasErrors('file');
});
