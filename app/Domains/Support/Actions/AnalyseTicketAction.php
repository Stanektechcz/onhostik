<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;

final class AnalyseTicketAction
{
    public function __construct(private readonly AiAssistantService $ai) {}

    public function handle(SupportTicket $ticket, User $actor): void
    {
        $firstMessage = (string) ($ticket->messages()->oldest()->value('message') ?? '');
        $text         = $ticket->subject . "\n\n" . $firstMessage;

        if (trim($text) === '') {
            return;
        }

        try {
            $run = $this->ai->run($actor, 'ticket_summary', ['text' => $text]);

            $summary = (string) ($run->messages()->where('role', 'assistant')->latest('id')->value('message') ?? '');

            // Extract classification hint from summary (simple keyword match fallback)
            $classification = $this->inferClassification($ticket->subject . ' ' . $firstMessage);
            $sentiment      = $this->inferSentiment($firstMessage);

            $ticket->update([
                'ai_classification' => $classification,
                'ai_sentiment'      => $sentiment,
                'ai_draft'          => $summary !== '' ? $summary : null,
                'ai_analysed_at'    => now(),
            ]);
        } catch (\Throwable) {
            // AI analysis is best-effort; never fail the ticket
        }
    }

    private function inferClassification(string $text): string
    {
        $text = mb_strtolower($text);

        if (str_contains($text, 'faktur') || str_contains($text, 'platb') || str_contains($text, 'invoice') || str_contains($text, 'platit')) {
            return 'billing';
        }
        if (str_contains($text, 'domain') || str_contains($text, 'doména') || str_contains($text, 'dns') || str_contains($text, 'server')) {
            return 'technical';
        }
        if (str_contains($text, 'heslo') || str_contains($text, 'účet') || str_contains($text, 'login') || str_contains($text, 'přihlás')) {
            return 'account';
        }
        if (str_contains($text, 'koupit') || str_contains($text, 'objednat') || str_contains($text, 'cena') || str_contains($text, 'nabídka')) {
            return 'sales';
        }

        return 'general';
    }

    private function inferSentiment(string $text): string
    {
        $text = mb_strtolower($text);

        $negativeWords = ['problém', 'chyba', 'nefunguje', 'error', 'fail', 'broken', 'špatný', 'terrible'];
        $positiveWords = ['děkuji', 'super', 'skvělý', 'excellent', 'perfect', 'thanks', 'thank you'];

        foreach ($negativeWords as $word) {
            if (str_contains($text, $word)) {
                return 'negative';
            }
        }
        foreach ($positiveWords as $word) {
            if (str_contains($text, $word)) {
                return 'positive';
            }
        }

        return 'neutral';
    }
}
