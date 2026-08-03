<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Marketplace\Models\AppInstallation;
use App\Domains\Marketplace\Models\MarketplaceApp;
use App\Domains\Marketplace\Services\AppInstaller;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    public function index(Service $service): View
    {
        $this->authorize('view', $service);

        $apps = MarketplaceApp::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $installed = AppInstallation::query()
            ->where('service_id', $service->id)
            ->whereIn('status', ['installed', 'installing', 'pending'])
            ->pluck('status', 'marketplace_app_id')
            ->map(fn (string $s): string => $s);

        return view('panel.marketplace.index', compact('service', 'apps', 'installed'));
    }

    public function install(
        Request $request,
        Service $service,
        MarketplaceApp $app,
        CreditLedger $ledger,
        AppInstaller $installer,
    ): RedirectResponse
    {
        $this->authorize('view', $service);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['install' => 'Instalace je dostupná pouze pro aktivní služby.']);
        }

        if (!$app->is_active) {
            return back()->withErrors(['install' => 'Aplikace není dostupná.']);
        }

        // Idempotent — if already installed/installing, skip
        $existing = AppInstallation::query()
            ->where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->whereIn('status', ['installed', 'installing', 'pending'])
            ->first();

        if ($existing !== null) {
            return back()->with('status', "{$app->name} je již nainstalována nebo se instaluje.");
        }

        if (! $app->isInstallable()) {
            return back()->withErrors([
                'install' => "{$app->name} zatím nemá připravený instalační balíček.",
            ]);
        }

        // Paid add-on: charge the customer's credit up front. Fail fast — we
        // must not install if we cannot bill.
        $customer = $service->customer;

        if ($app->isPaid()) {
            if ($customer === null) {
                return back()->withErrors(['install' => 'Účet nemá zákaznický profil pro platbu.']);
            }

            $price   = $app->priceMoney($customer->preferred_currency);
            $balance = $ledger->getBalance($customer);

            if ($balance->isLessThan($price)) {
                return back()->withErrors([
                    'install' => 'Nedostatek kreditu pro instalaci (' . MoneyFormatter::format($price) . '). Dobijte prosím kredit.',
                ]);
            }
        }

        // Row + charge in one transaction. The balance check above is advisory
        // (a concurrent purchase can invalidate it between check and charge);
        // the ledger's own locking is authoritative, so an overdraw throws here
        // and must not leave a half-created installation behind.
        try {
            $installation = DB::transaction(function () use ($service, $app, $customer, $ledger): AppInstallation {
                $installation = AppInstallation::create([
                    'service_id'         => $service->id,
                    'marketplace_app_id' => $app->id,
                    'status'             => 'installing',
                ]);

                if ($app->isPaid() && $customer !== null) {
                    $ledger->deduct(
                        $customer,
                        $app->priceMoney($customer->preferred_currency),
                        "Marketplace: {$app->name}",
                        $installation,
                    );
                    $installation->forceFill(['price_halere_paid' => $app->price_halere])->save();
                }

                return $installation;
            });
        } catch (InsufficientCreditException) {
            return back()->withErrors([
                'install' => 'Nedostatek kreditu pro instalaci. Dobijte prosím kredit a zkuste to znovu.',
            ]);
        }

        $result = $installer->install($service, $app, $installation);

        $service->provisioningTasks()->create([
            'operation'    => 'install_app',
            // The task mirrors what actually happened: a refused (dry-run) call
            // is pending work, not a success.
            'status'       => match (true) {
                ! $result['ok']      => TaskStatus::Failed,
                $result['dry_run']   => TaskStatus::Pending,
                default              => TaskStatus::Success,
            },
            'attempts'     => 1,
            'max_attempts' => 1,
            'payload'      => ['app_slug' => $app->slug, 'installation_id' => $installation->id],
            'result'       => [
                'app'      => $app->slug,
                'path'     => $installation->install_path,
                'database' => $result['database'],
                'dry_run'  => $result['dry_run'],
            ],
            'started_at'   => now(),
            'finished_at'  => now(),
        ]);

        // A failed install must not be a paid one — give the credit straight back.
        if (! $result['ok'] && $app->isPaid() && $customer !== null) {
            $ledger->refund(
                $customer,
                $app->priceMoney($customer->preferred_currency),
                "Vrácení: neúspěšná instalace {$app->name}",
                $installation,
            );

            return back()->withErrors([
                'install' => ($result['error'] ?? 'Instalace selhala.') . ' Kredit byl vrácen.',
            ]);
        }

        if (! $result['ok']) {
            return back()->withErrors(['install' => $result['error'] ?? 'Instalace selhala.']);
        }

        activity('marketplace')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['app_slug' => $app->slug, 'dry_run' => $result['dry_run']])
            ->log('marketplace.app_installed');

        return back()->with('status', $result['dry_run']
            ? "{$app->name}: instalace připravena, ale provisioning je v simulovaném režimu — na server se zatím nic nenahrálo."
            : "{$app->name} byla úspěšně nainstalována do {$installation->install_path}.");
    }

    public function remove(Request $request, Service $service, MarketplaceApp $app, AppInstaller $installer): RedirectResponse
    {
        $this->authorize('view', $service);

        $installation = AppInstallation::query()
            ->where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->whereIn('status', ['installed', 'installing', 'pending'])
            ->first();

        if ($installation === null) {
            return back()->withErrors(['remove' => 'Instalace nebyla nalezena.']);
        }

        // Delete the files too — marking the row "removed" while the app stays
        // live on the site is how you end up with an unpatched CMS nobody owns.
        $result = $installer->uninstall($service, $installation);

        if (! $result['ok']) {
            return back()->withErrors(['remove' => $result['error'] ?? 'Odinstalace selhala.']);
        }

        $installation->update(['status' => 'removed']);

        activity('marketplace')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties([
                'app_slug'      => $app->slug,
                'dry_run'       => $result['dry_run'],
                'files_removed' => $result['files_removed'],
            ])
            ->log('marketplace.app_removed');

        return back()->with('status', $result['files_removed']
            ? "{$app->name} byla odstraněna včetně souborů."
            : "{$app->name} odstraněna z evidence — soubory na serveru zůstávají, smažte je prosím ve správci souborů.");
    }
}
