<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use App\Notifications\ResellerApprovedNotification;
use App\Notifications\ResellerRejectedNotification;
use App\Notifications\ResellerSuspendedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->string('status')->toString();
        $search       = $request->string('search')->toString();

        $resellers = ResellerProfile::with('user')
            ->when($statusFilter !== '' && $statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search): void {
                $q2->where('business_name', 'like', '%' . $search . '%')
                   ->orWhere('custom_domain', 'like', '%' . $search . '%');
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.resellers.index', [
            'resellers'    => $resellers,
            'statusFilter' => $statusFilter,
            'search'       => $search,
        ]);
    }

    public function show(ResellerProfile $reseller): View
    {
        $reseller->load('user');

        return view('admin.resellers.show', [
            'reseller' => $reseller,
        ]);
    }

    public function approve(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $reseller->update([
            'status'      => 'active',
            'approved_at' => now(),
        ]);

        // Grant portal access so the reseller sees the Reseller sidebar section
        $reseller->user?->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('access-reseller', 'web')
        );

        $reseller->user?->notify(new ResellerApprovedNotification($reseller));

        activity('reseller')
            ->performedOn($reseller)
            ->causedBy($request->user())
            ->withProperties(['previous_status' => $reseller->getOriginal('status') ?? 'pending'])
            ->log('reseller.approved');

        return back()->with('status', 'Reseller byl schválen a aktivován.');
    }

    public function revoke(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $reseller->update(['status' => 'suspended']);

        $resellerPermission = \Spatie\Permission\Models\Permission::findOrCreate('access-reseller', 'web');
        if ($reseller->user?->hasPermissionTo($resellerPermission)) {
            $reseller->user->revokePermissionTo($resellerPermission);
        }

        $reseller->user?->notify(new ResellerSuspendedNotification($reseller));

        activity('reseller')
            ->performedOn($reseller)
            ->causedBy($request->user())
            ->log('reseller.access_revoked');

        return back()->with('status', 'Přístup resellerovi byl odebrán.');
    }

    public function reject(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $prev = $reseller->status;

        $reseller->update(['status' => 'rejected']);

        $reseller->user?->notify(new ResellerRejectedNotification($reseller));

        activity('reseller')
            ->performedOn($reseller)
            ->causedBy($request->user())
            ->withProperties(['previous_status' => $prev])
            ->log('reseller.rejected');

        return back()->with('status', 'Reseller byl zamítnut.');
    }

    public function suspend(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $prev = $reseller->status;

        $reseller->update(['status' => 'suspended']);

        $resellerPermission = \Spatie\Permission\Models\Permission::findOrCreate('access-reseller', 'web');
        if ($reseller->user?->hasPermissionTo($resellerPermission)) {
            $reseller->user->revokePermissionTo($resellerPermission);
        }

        $reseller->user?->notify(new ResellerSuspendedNotification($reseller));

        activity('reseller')
            ->performedOn($reseller)
            ->causedBy($request->user())
            ->withProperties(['previous_status' => $prev])
            ->log('reseller.suspended');

        return back()->with('status', 'Reseller byl pozastaven.');
    }

    public function updateMarkup(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $validated = $request->validate([
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $reseller->update([
            'markup_percent' => (float) $validated['markup_percent'],
        ]);

        activity('reseller')
            ->performedOn($reseller)
            ->causedBy($request->user())
            ->withProperties(['markup_percent' => $validated['markup_percent']])
            ->log('reseller.markup_updated');

        return back()->with('status', 'Markup byl aktualizován.');
    }
}
