<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Communication\Services\WebPushService;
use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores / removes a browser's web-push subscription for the current user
 * (audit 92). The browser calls store() after the user grants permission.
 */
final class PushSubscriptionController extends Controller
{
    /** Public VAPID key the browser needs to create a subscription. */
    public function key(WebPushService $webPush): JsonResponse
    {
        return response()->json([
            'enabled'   => $webPush->enabled(),
            'publicKey' => $webPush->publicKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint'  => ['required', 'string', 'max:1000'],
            'publicKey' => ['required', 'string', 'max:255'],
            'authToken' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        PushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'endpoint_hash' => hash('sha256', $validated['endpoint'])],
            [
                'endpoint'   => $validated['endpoint'],
                'public_key' => $validated['publicKey'],
                'auth_token' => $validated['authToken'],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        PushSubscription::where('user_id', $request->user()?->id)
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
