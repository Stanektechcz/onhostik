<?php

declare(strict_types=1);

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Models\SystemAnnouncement;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Support\Facades\Notification;

final class AnnouncementService
{
    public function broadcast(SystemAnnouncement $announcement): int
    {
        $users = User::where('is_active', true)
            ->whereHas('customer')
            ->get();

        Notification::send($users, new SystemAnnouncementNotification($announcement));

        $announcement->update([
            'is_published' => true,
            'published_at' => now(),
            'sent_count'   => $users->count(),
        ]);

        return $users->count();
    }
}
