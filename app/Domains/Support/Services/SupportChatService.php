<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Support\Enums\ChatConversationStatus;
use App\Domains\Support\Models\SupportChatConversation;
use App\Domains\Support\Models\SupportChatMessage;
use App\Models\User;
use App\Notifications\ChatEscalatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Owns the support-chat lifecycle: every message (customer, AI bot, live
 * agent, system event) is persisted, and the conversation moves bot →
 * waiting_agent → agent_active → closed. Nothing lives only in the browser.
 */
final class SupportChatService
{
    /** Latest still-open conversation for the user, or a fresh one. */
    public function openConversationFor(User $user): SupportChatConversation
    {
        $existing = SupportChatConversation::query()
            ->where('started_by', $user->id)
            ->where('status', '!=', ChatConversationStatus::Closed->value)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return SupportChatConversation::create([
            'customer_id'     => $user->customer?->id,
            'started_by'      => $user->id,
            'status'          => ChatConversationStatus::Bot,
            'last_message_at' => now(),
        ]);
    }

    public function userMessage(SupportChatConversation $c, string $body, User $author): SupportChatMessage
    {
        return $this->append($c, 'user', $body, $author->id);
    }

    /** @param array<string, mixed> $meta */
    public function botMessage(SupportChatConversation $c, string $body, array $meta = []): SupportChatMessage
    {
        return $this->append($c, 'bot', $body, null, $meta);
    }

    public function systemMessage(SupportChatConversation $c, string $body): SupportChatMessage
    {
        return $this->append($c, 'system', $body, null);
    }

    /**
     * Attach an uploaded file to the conversation as a user message.
     *
     * @param  array{name: string, path: string, size: int|false, mime: string|null}  $file
     */
    public function attachment(SupportChatConversation $c, User $author, array $file): SupportChatMessage
    {
        $message = $this->append($c, 'user', '📎 ' . $file['name'], $author->id, ['attachment' => $file]);

        // Add the auth-gated download URL now that the message has an id.
        /** @var array<string, mixed> $meta */
        $meta = $message->meta ?? [];
        $meta['attachment']['url'] = route('panel.ai.attachment', $message->id);
        $message->update(['meta' => $meta]);

        return $message;
    }

    public function agentMessage(SupportChatConversation $c, User $agent, string $body): SupportChatMessage
    {
        // First agent reply claims the conversation and makes it live.
        $c->update([
            'status'      => ChatConversationStatus::AgentActive,
            'assigned_to' => $c->assigned_to ?? $agent->id,
        ]);

        return $this->append($c, 'agent', $body, $agent->id);
    }

    /** Customer asks for a human. */
    public function escalate(SupportChatConversation $c): void
    {
        if ($c->status === ChatConversationStatus::AgentActive
            || $c->status === ChatConversationStatus::WaitingAgent) {
            return; // already with / waiting for an agent
        }

        $c->update(['status' => ChatConversationStatus::WaitingAgent, 'last_message_at' => now()]);
        $this->systemMessage($c, 'Konverzace byla předána živé podpoře. Operátor se vám ozve co nejdříve.');

        // Ping operators so they can pick it up from the inbox (in-app only).
        try {
            $admins = User::role('admin')->get();
            Notification::send($admins, new ChatEscalatedNotification($c));
        } catch (Throwable $e) {
            report($e); // never let a notification failure block the escalation
        }

        activity('support')
            ->performedOn($c)
            ->causedBy($c->startedBy)
            ->log('chat.escalated');
    }

    public function close(SupportChatConversation $c, User $by): void
    {
        if ($c->status === ChatConversationStatus::Closed) {
            return;
        }

        $c->update(['status' => ChatConversationStatus::Closed, 'closed_at' => now()]);
        $this->systemMessage($c, 'Konverzace byla uzavřena.');

        activity('support')->performedOn($c)->causedBy($by)->log('chat.closed');
    }

    /**
     * New messages after $afterId — used by the widget's poll.
     *
     * @return Collection<int, SupportChatMessage>
     */
    public function messagesSince(SupportChatConversation $c, int $afterId): Collection
    {
        return $c->messages()->where('id', '>', $afterId)->get();
    }

    /** @param array<string, mixed> $meta */
    private function append(SupportChatConversation $c, string $role, string $body, ?int $authorId, array $meta = []): SupportChatMessage
    {
        $message = $c->messages()->create([
            'author_id' => $authorId,
            'role'      => $role,
            'body'      => $body,
            'meta'      => $meta === [] ? null : $meta,
        ]);

        $c->update(['last_message_at' => now()]);

        return $message;
    }
}
