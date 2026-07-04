<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AnalyzeTicketWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly User $user,
    ) {}

    public function handle(AiAssistantService $ai): void
    {
        $ai->analyzeTicket($this->ticket, $this->user);
    }
}
