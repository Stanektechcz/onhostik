<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServiceCalendarController extends Controller
{
    public function download(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user     = $request->user();
        $customer = $user->customer;

        $services = $customer
            ? Service::query()
                ->where('customer_id', $customer->id)
                ->whereNotNull('next_due_date')
                ->whereNull('terminated_at')
                ->orderBy('next_due_date')
                ->get(['id', 'label', 'next_due_date'])
            : collect();

        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//OnHost//Service Calendar//CS';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:OnHost — Moje služby';
        $lines[] = 'X-WR-CALDESC:Termíny obnov vašich služeb';

        foreach ($services as $service) {
            $date     = $service->next_due_date->format('Ymd');
            $datePlus = $service->next_due_date->addDay()->format('Ymd');
            $label    = $this->escapeText((string) $service->label);
            $uid      = 'service-' . $service->id . '@onhost';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTART;VALUE=DATE:' . $date;
            $lines[] = 'DTEND;VALUE=DATE:' . $datePlus;
            $lines[] = 'SUMMARY:Obnova — ' . $label;
            $lines[] = 'DESCRIPTION:Platba za obnovu služby ' . $label . ' je splatná.';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $body = implode("\r\n", $lines) . "\r\n";

        return response($body, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sluzby-onhost.ics"',
        ]);
    }

    private function escapeText(string $text): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $text);
    }
}
