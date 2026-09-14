<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Notifications\CalendarFeed;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Platform\Commands\CommandScope;

/** The organization's dates: renewals, domain expiries, invoice due dates, maintenance, credit horizon — as JSON and as a signed ICS feed link. */
final class CalendarController extends ApiController
{
    public function index(Request $request, CalendarFeed $calendar): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365'], 'project_id' => ['nullable', 'string', 'max:40']]);
        $events = $calendar->events($organization, $data['project_id'] ?? null, (int) ($data['days'] ?? CalendarFeed::HORIZON_DAYS));

        return response()->json(['data' => $events, 'total' => count($events), 'days' => (int) ($data['days'] ?? CalendarFeed::HORIZON_DAYS)]);
    }

    public function feed(Request $request, CalendarFeed $calendar): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $data = $request->validate(['project_id' => ['nullable', 'string', 'max:40']]);

        return response()->json(['data' => $calendar->feedUrl($organization, $data['project_id'] ?? null)]);
    }

    public function rotate(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));

        return $this->dispatch(new OrganizationCommand($organization->id, $this->idempotencyKey($request, 'org.calendar.rotate'), ['op' => 'rotate_calendar_feed']), $this->api->context($request, $organization));
    }
}
