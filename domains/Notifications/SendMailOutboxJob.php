<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Onhost\Domain\Notifications\Models\MailOutbox;

/**
 * Sends one queued transactional mail as soon as it was queued (the `mails` queue); `onhost:mail:send` remains the
 * safety net for retries and for mails scheduled for later.
 */
final class SendMailOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $mailId)
    {
        $this->onQueue('mails');
    }

    public function handle(NotificationService $notifications): void
    {
        $mail = MailOutbox::query()->find($this->mailId);
        if ($mail === null || $mail->state !== 'queued' || ($mail->scheduled_at !== null && $mail->scheduled_at->isFuture())) {
            return;
        }
        $notifications->deliver($mail);
    }
}
