<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Notifications\ServiceExpiryAlertNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BulkExpiryNotificationController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'in:7,14,30'],
        ]);

        $days = (int) $validated['days'];

        $services = Service::with('customer.user')
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->get();

        $sent = 0;

        foreach ($services as $service) {
            $user = $service->customer?->user;
            if ($user === null) {
                continue;
            }
            $daysLeft = (int) now()->diffInDays($service->next_due_date, false);
            $user->notify(new ServiceExpiryAlertNotification($service, max(0, $daysLeft)));
            $sent++;
        }

        return back()->with('status', "Odesláno {$sent} upozornění na expiraci.");
    }
}
