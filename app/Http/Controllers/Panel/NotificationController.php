<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Bell AJAX → JSON; browser navigation → full HTML page. */
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();

        if ($request->wantsJson()) {
            $notifications = $user
                ->notifications()
                ->latest()
                ->take(20)
                ->get()
                ->map(fn ($n) => [
                    'id'         => $n->id,
                    'data'       => $n->data,
                    'read'       => $n->read_at !== null,
                    'created_at' => $n->created_at->diffForHumans(),
                ]);

            return response()->json([
                'notifications' => $notifications,
                'unread_count'  => $user->unreadNotifications()->count(),
            ]);
        }

        $notifications  = $user->notifications()->latest()->paginate(25);
        $unreadCount    = $user->unreadNotifications()->count();

        return view('panel.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereKey($id)
            ->first()
            ?->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
