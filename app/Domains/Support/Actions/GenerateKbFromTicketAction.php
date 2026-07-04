<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Support\Models\SupportTicket;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Support\Str;

final class GenerateKbFromTicketAction
{
    public function __construct(private readonly AiAssistantService $ai) {}

    public function handle(SupportTicket $ticket, User $actor): ?KbArticle
    {
        $firstMessage = (string) ($ticket->messages()->oldest()->value('message') ?? '');

        if (trim($firstMessage) === '') {
            return null;
        }

        $text = "{$ticket->subject}\n\n{$firstMessage}";

        $run = $this->ai->run($actor, 'ticket_summary', ['text' => $text]);

        $body = (string) ($run->messages()->where('role', 'assistant')->latest('id')->value('content') ?? '');

        if (trim($body) === '') {
            return null;
        }

        $slug = Str::slug($ticket->subject) . '-' . $ticket->id;

        return KbArticle::create([
            'title'        => $ticket->subject,
            'slug'         => $slug,
            'category'     => $ticket->ai_classification ?? 'general',
            'excerpt'      => mb_substr(strip_tags($body), 0, 255),
            'body'         => $body,
            'is_published' => false,
            'sort_order'   => 0,
            'locale'       => 'cs',
        ]);
    }
}
