<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Notifications\CalendarFeed;
use Onhost\Domain\Organizations\Models\Organization;

/** The signed ICS feed: no session, the signature (checked by the `signed` middleware) plus the feed version are the credential. */
final class CalendarFeedController
{
    public function feed(Request $request, CalendarFeed $calendar, string $organization): Response
    {
        $model = Organization::query()->find($organization);
        if ($model === null || (int) $request->query('v', '0') !== $calendar->version($model)) {
            abort(404);
        }
        $project = $request->query('project');
        $body = $calendar->ics($model, $calendar->events($model, is_string($project) && $project !== '' ? $project : null));

        return new Response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="onhost-'.$model->slug.'.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
