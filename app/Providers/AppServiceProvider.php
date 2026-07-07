<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Listeners\HandleInvoicePaid;
use App\Domains\Partner\Listeners\CreateCommissionOnInvoicePaid;
use App\Listeners\BroadcastNotificationReceived;
use App\Listeners\LogSentEmail;
use Illuminate\Mail\Events\MessageSent;
use App\Listeners\HandleTwoFactorAuthenticationConfirmed;
use App\Listeners\HandleTwoFactorAuthenticationDisabled;
use App\Listeners\NotifyAdminOnFailedJob;
use App\Listeners\RecordLoginHistoryEntry;
use App\Listeners\RecordUserLogin;
use App\Listeners\TrackSecurityEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed as FortifyTwoFactorConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled as FortifyTwoFactorDisabled;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use App\Domains\Billing\Services\Gateways\ComgateGateway;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\DomainRegistrationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SupportTicketPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Domains\Communication\Models\SystemAnnouncement;
use App\Models\MaintenanceWindow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComgateGateway::class, fn () => ComgateGateway::fromConfig());
    }

    public function boot(): void
    {
        $this->configureFactories();
        $this->configureGates();
        $this->configurePolicies();
        $this->configureRateLimiters();
        $this->configureEvents();
        $this->configureViewComposers();
    }

    /**
     * One event path for every payment source: mock gateway, credit ledger
     * and (later) the real Comgate webhook all fire InvoicePaid.
     */
    private function configureEvents(): void
    {
        Event::listen(InvoicePaid::class, HandleInvoicePaid::class);
        Event::listen(InvoicePaid::class, CreateCommissionOnInvoicePaid::class);
        // TrackSecurityEvent must run BEFORE RecordUserLogin to see the old last_login_ip
        Event::listen(Login::class, [TrackSecurityEvent::class, 'handleLogin']);
        Event::listen(Login::class, RecordUserLogin::class);
        Event::listen(Login::class, RecordLoginHistoryEntry::class);
        Event::listen(Failed::class, [TrackSecurityEvent::class, 'handleFailed']);
        Event::listen(Logout::class, [TrackSecurityEvent::class, 'handleLogout']);
        Event::listen(NotificationSent::class, BroadcastNotificationReceived::class);
        Event::listen(JobFailed::class, NotifyAdminOnFailedJob::class);
        Event::listen(FortifyTwoFactorConfirmed::class, HandleTwoFactorAuthenticationConfirmed::class);
        Event::listen(FortifyTwoFactorDisabled::class, HandleTwoFactorAuthenticationDisabled::class);
        Event::listen(MessageSent::class, LogSentEmail::class);
    }

    /**
     * Domain models live under App\Domains\*\Models, so the default
     * factory-name resolution (App\Models → Database\Factories) is widened
     * to match by class basename.
     */
    private function configureFactories(): void
    {
        Factory::guessFactoryNamesUsing(
            static function (string $modelName): string {
                /** @var class-string<Factory<Model>> $factoryName */
                $factoryName = 'Database\\Factories\\' . class_basename($modelName) . 'Factory';

                return $factoryName;
            },
        );
    }

    private function configureGates(): void
    {
        // Used by routes/panel.php and the panel sidebar (@can('access-admin')).
        Gate::define('access-admin', fn (User $user): bool => $user->hasRole('admin'));
    }

    private function configurePolicies(): void
    {
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(DomainRegistration::class, DomainRegistrationPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
    }

    private function configureViewComposers(): void
    {
        View::composer(['layouts.panel', 'layouts.front'], function (\Illuminate\View\View $view): void {
            $isAdmin = str_contains($view->getName(), 'panel');
            $banners = MaintenanceWindow::currentBanners($isAdmin);
            $view->with('maintenanceActive', $banners['active']);
            $view->with('maintenanceUpcoming', $banners['upcoming']);
        });

        View::composer('layouts.panel', function (\Illuminate\View\View $view): void {
            $user = auth()->user();

            if ($user === null) {
                $view->with('activeAnnouncements', collect());
                return;
            }

            $dismissed = $user->isAdmin()
                ? collect()
                : \Illuminate\Support\Facades\DB::table('announcement_dismissals')
                      ->where('user_id', $user->id)
                      ->pluck('announcement_id');

            $customerSegment = null;
            if (! $user->isAdmin()) {
                $customer = $user->customer;
                $customerSegment = $customer?->segment;
            }

            $view->with('activeAnnouncements', SystemAnnouncement::query()
                ->where('is_published', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('target_segment')
                    ->orWhere('target_segment', $customerSegment))
                ->whereNotIn('id', $dismissed)
                ->latest('published_at')
                ->get());
        });
    }

    private function configureRateLimiters(): void
    {
        // Public domain availability search — keep WEDOS quota safe (100/h).
        RateLimiter::for('domain-check', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
