<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Communication\Models\ProductUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * In-app changelog — "co je nového" (audit I130).
 */
class ChangelogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $updates = ProductUpdate::query()
            ->visible()
            ->latest('published_at')
            ->paginate(20);

        // Everything above this line was new when the page was opened; the
        // marker is captured BEFORE it is advanced so the "new" flags on this
        // very render still show, rather than the page marking itself read
        // and appearing to have nothing new in it.
        $seenBefore = $user?->changelog_seen_at;

        $user?->forceFill(['changelog_seen_at' => now()])->save();

        return view('panel.changelog.index', compact('updates', 'seenBefore'));
    }
}
