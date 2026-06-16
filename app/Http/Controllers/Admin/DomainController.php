<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request): View
    {
        $search         = $request->string('q')->toString();
        $expiryFilter   = $request->string('expiry')->toString(); // 'soon' | 'expired' | ''

        $expiringCount = DomainRegistration::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(30))
            ->whereDate('expires_at', '>=', now())
            ->count();

        $expiredCount = DomainRegistration::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now())
            ->count();

        return view('admin.domains', [
            'domains' => DomainRegistration::query()
                ->with('service.customer')
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($inner) use ($search): void {
                        $inner->where('domain', 'like', "%{$search}%")
                              ->orWhere('tld', 'like', "%{$search}%")
                              ->orWhereHas('service.customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                                  ->orWhere('company_name', 'like', "%{$search}%"));
                    });
                })
                ->when($expiryFilter === 'soon', fn ($q) => $q
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<=', now()->addDays(30))
                    ->whereDate('expires_at', '>=', now()))
                ->when($expiryFilter === 'expired', fn ($q) => $q
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<', now()))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'domainTasks' => ProvisioningTask::query()
                ->where('operation', 'register_domain')
                ->with('service.customer')
                ->latest('id')
                ->paginate(25, ['*'], 'tasks'),
            'totalCount'    => DomainRegistration::query()->count(),
            'activeCount'   => DomainRegistration::query()->whereNotNull('wedos_domain_id')->count(),
            'expiringCount' => $expiringCount,
            'expiredCount'  => $expiredCount,
            'search'        => $search,
            'expiryFilter'  => $expiryFilter,
        ]);
    }
}
