<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function show(DomainRegistration $domain): View
    {
        $domain->load('service.customer.user', 'service.product');

        $tasks = ProvisioningTask::query()
            ->where('service_id', $domain->service_id)
            ->whereIn('operation', ['register_domain', 'renew_domain', 'update_nameservers'])
            ->latest('id')
            ->get();

        return view('admin.domain-show', [
            'domain' => $domain,
            'tasks'  => $tasks,
        ]);
    }

    public function toggleAutoRenew(DomainRegistration $domain): RedirectResponse
    {
        $domain->update(['auto_renew' => ! $domain->auto_renew]);

        $msg = $domain->auto_renew
            ? __('panel.domains.auto_renew_enabled')
            : __('panel.domains.auto_renew_disabled');

        return back()->with('status', $msg);
    }

    /** Stream all domains as CSV. */
    public function export(Request $request): StreamedResponse
    {
        $search       = $request->string('q')->toString();
        $expiryFilter = $request->string('expiry')->toString();

        $query = DomainRegistration::query()
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
            ->orderBy('id');

        $filename = 'domeny-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Doména', 'Registrátor', 'Zákazník', 'E-mail',
                'Registrováno', 'Expirace', 'Auto-renew', 'WEDOS ID',
            ], ';');

            $query->chunk(200, function ($domains) use ($out): void {
                foreach ($domains as $domain) {
                    fputcsv($out, [
                        $domain->id,
                        $domain->fqdn(),
                        $domain->registrar ?: '',
                        $domain->service->customer->company_name ?: $domain->service->customer->email,
                        $domain->service->customer->email,
                        $domain->registered_at?->format('d.m.Y') ?? '',
                        $domain->expires_at?->format('d.m.Y') ?? '',
                        $domain->auto_renew ? 'ano' : 'ne',
                        $domain->wedos_domain_id ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
