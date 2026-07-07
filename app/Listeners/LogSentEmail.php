<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $to      = array_keys($message->getTo());
        $address = $to[0] ?? 'unknown';
        $subject = $message->getSubject() ?? '';

        EmailLog::create([
            'to_address' => $address,
            'subject'    => mb_substr($subject, 0, 500),
        ]);
    }
}
