<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Support\Models\SupportChatConversation;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

/**
 * Tells operators (admins) that a customer has asked for live support, so
 * they can pick the chat up from the inbox. In-app only — no e-mail noise.
 */
class ChatEscalatedNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(private readonly SupportChatConversation $conversation) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return $this->channelsFor($notifiable, 'support', ['database']);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        $customerModel = $this->conversation->customer;
        $starter       = $this->conversation->startedBy;

        $customer = 'Zákazník';
        if ($customerModel !== null && $customerModel->company_name) {
            $customer = $customerModel->company_name;
        } elseif ($starter !== null && $starter->name) {
            $customer = $starter->name;
        }

        return [
            'type'            => 'chat_escalated',
            'icon'            => 'message-circle',
            'color'           => 'warning',
            'title'           => 'Nová žádost o živý chat',
            'body'            => $customer . ' čeká na operátora.',
            'url'             => route('admin.chat.show', $this->conversation),
            'conversation_id' => $this->conversation->id,
        ];
    }
}
