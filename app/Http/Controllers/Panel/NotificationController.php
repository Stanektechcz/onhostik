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

        $query = $user->notifications()->latest();

        if ($request->filled('type')) {
            $type = $request->string('type')->toString();
            $query->where('data->type', $type);
        }

        // Read/unread filter (audit I128). Anything other than the two known
        // values is ignored rather than returning nothing — a mistyped query
        // string should not look like an empty inbox.
        $activeStatus = $request->string('status', '')->toString();

        if ($activeStatus === 'unread') {
            $query->whereNull('read_at');
        } elseif ($activeStatus === 'read') {
            $query->whereNotNull('read_at');
        } else {
            $activeStatus = '';
        }

        $notifications = $query->paginate(25)->withQueryString();
        $unreadCount   = $user->unreadNotifications()->count();
        $readCount     = $user->notifications()->whereNotNull('read_at')->count();
        $activeType    = $request->string('type', '')->toString();

        return view('panel.notifications.index', compact(
            'notifications',
            'unreadCount',
            'readCount',
            'activeType',
            'activeStatus',
        ));
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
