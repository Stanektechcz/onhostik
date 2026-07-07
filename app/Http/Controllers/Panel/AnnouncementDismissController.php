<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Communication\Models\SystemAnnouncement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementDismissController extends Controller
{
    public function dismiss(Request $request, SystemAnnouncement $announcement): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $announcement->isActive()) {
            $already = $announcement->dismissedBy()->where('users.id', $user->id)->exists();

            if (! $already) {
                $announcement->dismissedBy()->attach($user->id, ['dismissed_at' => now()]);
            }
        }

        return back();
    }
}
