<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerts admins when the queue is backing up or accumulating failures — the
 * "workers died and nobody noticed" failure mode (audit 500 #44), complementing
 * the per-job failure alert.
 */
final class QueueHealthAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $pending,
        private readonly int $failed,
        private readonly int $maxPending,
        private readonly int $maxFailed,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ OnHost: fronta úloh vyžaduje pozornost')
            ->line("Čekající úlohy: {$this->pending} (limit {$this->maxPending}).")
            ->line("Selhané úlohy: {$this->failed} (limit {$this->maxFailed}).")
            ->line('Zkontrolujte workery/Horizon a fronty — úlohy se možná nezpracovávají.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'alert-triangle',
            'color'   => 'danger',
            'title'   => 'Fronta úloh vyžaduje pozornost',
            'body'    => "Čekající: {$this->pending} (limit {$this->maxPending}), selhané: {$this->failed} (limit {$this->maxFailed}).",
            'pending' => $this->pending,
            'failed'  => $this->failed,
        ];
    }
}
