<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Communication\Models\SystemAnnouncement;
use App\Domains\Communication\Services\AnnouncementService;
use Illuminate\Console\Command;

class PublishScheduledAnnouncementsCommand extends Command
{
    protected $signature   = 'announcements:publish-scheduled';
    protected $description = 'Publish announcements whose scheduled_at time has arrived.';

    public function handle(AnnouncementService $service): int
    {
        $due = SystemAnnouncement::query()
            ->where('is_published', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No announcements due for publishing.');
            return self::SUCCESS;
        }

        $published = 0;

        foreach ($due as $announcement) {
            $announcement->update(['is_published' => true]);
            $service->broadcast($announcement);
            $published++;
            $this->line("Published announcement #{$announcement->id}: {$announcement->title}");
        }

        $this->info("Done — {$published} announcement(s) published.");

        return self::SUCCESS;
    }
}
