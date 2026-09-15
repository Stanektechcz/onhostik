<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Game artwork on the public offer (audit §5w): the header art of a game group is fetched once from the configured
 * source (`onhost.game.art_url`, Steam header art by default), kept on the local disk and served from our own origin —
 * visitors never contact a third party and the CSP stays `img-src 'self'`. Unknown groups or a failed fetch → 404,
 * the page then draws its own tile.
 */
final class GameArtController
{
    public function show(string $group): Response
    {
        $group = strtolower($group);
        $appid = 0;
        foreach ((array) config('onhost.game.eggs', []) as $preset) {
            if (($preset['group'] ?? null) === $group && (int) ($preset['steam_appid'] ?? 0) > 0) {
                $appid = (int) $preset['steam_appid'];
                break;
            }
        }
        abort_if($appid === 0 || preg_match('/^[a-z0-9-]{1,40}$/', $group) !== 1, 404);
        $path = "game-art/{$group}-{$appid}.jpg";
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            try {
                $response = Http::timeout(8)->accept('image/*')->get(str_replace('{appid}', (string) $appid, (string) config('onhost.game.art_url')));
            } catch (Throwable) {
                abort(404);
            }
            $body = (string) $response->body();
            abort_if(! $response->successful() || strlen($body) < 512 || strlen($body) > 2_000_000 || ! str_starts_with($body, "\xFF\xD8"), 404); // JPEG only
            $disk->put($path, $body);
        }

        return response((string) $disk->get($path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=604800', 'X-Content-Type-Options' => 'nosniff']);
    }
}
