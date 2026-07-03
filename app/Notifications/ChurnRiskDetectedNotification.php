<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChurnRiskDetectedNotification extends Notification
{
    /**
     * @param array<int, array<string, string>> $risks [{customer_id, email, signal, detail}]
     */
    public function __construct(private readonly array $risks, private readonly int $totalCount) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'alert-circle',
            'color' => 'warning',
            'title' => "Churn riziko — {$this->totalCount} zákazník" . ($this->totalCount > 4 ? 'ů' : ($this->totalCount > 1 ? 'i' : '')),
            'body'  => 'Zákazníci nevykazují aktivitu. Prohlédněte v audit logu.',
            'url'   => route('admin.audit'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[OnHost] Churn riziko — {$this->totalCount} zákazníků")
            ->greeting('Dobrý den,')
            ->line("Detekce odchodu zákazníků nalezla **{$this->totalCount}** zákazník" .
                ($this->totalCount > 4 ? 'ů' : ($this->totalCount > 1 ? 'y' : 'a')) . ' s rizikovými signály.');

        foreach (array_slice($this->risks, 0, 10) as $risk) {
            $mail->line("• **{$risk['email']}** — {$risk['signal']}");
        }

        if ($this->totalCount > 10) {
            $mail->line('… a další ' . ($this->totalCount - 10) . ' zákazníků.');
        }

        return $mail
            ->action('Zobrazit audit log', route('admin.audit'))
            ->salutation('Tým OnHost');
    }
}
