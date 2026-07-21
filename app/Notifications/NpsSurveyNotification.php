<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Support\Models\SupportTicket;
use App\Models\NpsResponse;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class NpsSurveyNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(
        private readonly NpsResponse $npsResponse,
        private readonly SupportTicket $ticket,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'marketing', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('nps.show', $this->npsResponse->survey_token);

        return (new MailMessage)
            ->subject("Jak hodnotíte naši podporu? — Tiketa #{$this->ticket->id}")
            ->greeting('Dobrý den,')
            ->line("Vaše tiketa **{$this->ticket->subject}** byla uzavřena. Děkujeme, že jste se na nás obrátili.")
            ->line('Věnujte nám prosím 30 sekund a ohodnoťte naši podporu. Vaše zpětná vazba nám pomáhá se zlepšovat.')
            ->action('Ohodnotit podporu', $url)
            ->line('Jak pravděpodobné je, že byste nás doporučili přátelům nebo kolegům? (0 = vůbec ne, 10 = rozhodně)')
            ->salutation('Tým Onhost.cz');
    }
}
