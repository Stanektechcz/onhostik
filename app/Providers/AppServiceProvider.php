<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Listeners\HandleInvoicePaid;
use App\Domains\Partner\Listeners\CreateCommissionOnInvoicePaid;
use App\Listeners\BroadcastNotificationReceived;
use App\Listeners\SendWebPushForNotification;
use App\Listeners\EnforceConcurrentSessionLimit;
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
use App\Domains\Billing\Services\Gateways\GopayGateway;
use App\Domains\Billing\Services\Gateways\StripeGateway;
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
        // All three payment gateways take plain-string constructor args, so
        // they cannot be autowired. Without these bindings the Stripe and
        // GoPay webhook endpoints threw BindingResolutionException on every
        // incoming notification — i.e. no card payment could ever settle.
        $this->app->singleton(ComgateGateway::class, fn () => ComgateGateway::fromConfig());
        $this->app->singleton(StripeGateway::class, fn () => StripeGateway::fromConfig());
        $this->app->singleton(GopayGateway::class, fn () => GopayGateway::fromConfig());
    }

    public function boot(): void
    {
        $this->configureFactories();
        $this->configureGates();
        $this->configurePolicies();
        $this->configureRateLimiters();
        $this->configureEvents();
        $this->configureViewComposers();
        $this->configureSlowQueryLogging();
        $this->configureImpersonationAudit();
        $this->configureMailFromVault();
    }

    /**
     * Point the mailer at admin-managed SMTP credentials when configured,
     * falling back to .env. Deferred to `booted` so config + DB are ready.
     */
    private function configureMailFromVault(): void
    {
        $this->app->booted(function (): void {
            app(\App\Domains\Integrations\Services\MailConfigurator::class)->apply();
        });
    }

    /**
     * Audit G71 — a complete trail of what an admin did while impersonating.
     *
     * The time box (G98) limits how long "log in as customer" stays open, but
     * every action taken during it was logged as caused by the CUSTOMER — so
     * an admin editing a customer's data left a trail that read as the customer
     * doing it themselves. This stamps the impersonating admin's id onto every
     * activity created during an impersonated request, so the audit log always
     * answers "who really did this".
     */
    private function configureImpersonationAudit(): void
    {
        \Spatie\Activitylog\Models\Activity::creating(static function (\Spatie\Activitylog\Models\Activity $activity): void {
            if (! app()->bound('session') || ! session()->has('_impersonated_by')) {
                return;
            }

            /** @var \Illuminate\Support\Collection<string, mixed> $props */
            $props = $activity->properties ?? collect();

            $activity->properties = $props->merge([
                'impersonated_by_admin_id' => session('_impersonated_by'),
            ]);
        });
    }

    /**
     * Audit H81 — surface slow database queries.
     *
     * Without an APM there is no runtime view of which query is dragging a
     * page down; an N+1 or a missing index only shows up once it is slow enough
     * for a customer to complain. This logs any query slower than the
     * configured threshold WITH the request context (request_id/route via the
     * shared log context), so the offender is identifiable, not just "something
     * was slow". Off by default in tests so a slow CI box does not spam logs.
     */
    private function configureSlowQueryLogging(): void
    {
        $thresholdMs = (int) config('database.slow_query_threshold_ms', 0);

        if ($thresholdMs <= 0) {
            return;
        }

        \Illuminate\Support\Facades\DB::whenQueryingForLongerThan(
            $thresholdMs,
            static function (\Illuminate\Database\Connection $connection, \Illuminate\Database\Events\QueryExecuted $event): void {
                \Illuminate\Support\Facades\Log::warning('db.slow_query', [
                    'connection' => $connection->getName(),
                    'time_ms'    => $event->time,
                    // The SQL, not the bindings — bindings can hold PII/secrets.
                    'sql'        => $event->sql,
                ]);
            },
        );
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
        Event::listen(Login::class, EnforceConcurrentSessionLimit::class);
        Event::listen(Failed::class, [TrackSecurityEvent::class, 'handleFailed']);
        Event::listen(Logout::class, [TrackSecurityEvent::class, 'handleLogout']);
        Event::listen(NotificationSent::class, BroadcastNotificationReceived::class);
        Event::listen(NotificationSent::class, SendWebPushForNotification::class);
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
        /*
         | An admin may perform ANY action or edit in the system.
         |
         | Each policy already carried its own before() admin bypass, but that
         | only covered models that HAVE a policy — a new model or gate would
         | silently lock admins out until someone remembered to add it. This
         | makes the rule global and unmissable.
         |
         | Returning null (not false) for non-admins hands the decision back to
         | the normal policy/gate chain rather than denying outright.
         |
         | Note this only governs AUTHORIZATION. Business-state rules (e.g. you
         | cannot edit an already-sent campaign, or refund an uncompleted
         | payment) are enforced with explicit checks in the controllers and
         | are deliberately NOT bypassed here — they protect data integrity,
         | not permissions.
         */
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasRole('admin') ? true : null);

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
        // Composers run on EVERY page — a missing table (mid-deploy, fresh
        // database before migrations) must degrade to "no banner", never
        // take the whole site down.
        View::composer(['layouts.panel', 'layouts.front'], function (\Illuminate\View\View $view): void {
            $isAdmin = str_contains($view->getName(), 'panel');

            try {
                $banners = MaintenanceWindow::currentBanners($isAdmin);
            } catch (\Illuminate\Database\QueryException) {
                $banners = ['active' => null, 'upcoming' => null];
            }

            $view->with('maintenanceActive', $banners['active']);
            $view->with('maintenanceUpcoming', $banners['upcoming']);
        });

        // Cart item count for the navbar cart badge — session-backed, cheap.
        View::composer(['layouts.panel', 'components.panel.header'], function (\Illuminate\View\View $view): void {
            /** @var array<int, int> $cart */
            $cart = session('panel_cart', []);
            $view->with('cartItemCount', array_sum($cart));
        });

        View::composer('layouts.panel', function (\Illuminate\View\View $view): void {
            $user = auth()->user();

            if ($user === null) {
                $view->with('activeAnnouncements', collect());
                return;
            }

            try {
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

                $announcements = SystemAnnouncement::query()
                    ->where('is_published', true)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('target_segment')
                        ->orWhere('target_segment', $customerSegment))
                    ->whereNotIn('id', $dismissed)
                    ->latest('published_at')
                    ->get();
            } catch (\Illuminate\Database\QueryException) {
                $announcements = collect();
            }

            $view->with('activeAnnouncements', $announcements);
        });
    }

    private function configureRateLimiters(): void
    {
        // Public domain availability search — keep WEDOS quota safe (100/h).
        RateLimiter::for('domain-check', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        /*
         | Per-TOKEN API limiting (audit J134).
         |
         | The previous throttle:60,1 keyed on the user, so a customer's five
         | integrations shared one budget and a single misbehaving script
         | starved the rest. Keying on the token isolates them, and the limit
         | itself is configurable per token so a partner can be raised without
         | lifting the ceiling for everyone.
         |
         | Falls back to the user, then the IP, for unauthenticated calls.
         */
        RateLimiter::for('api', function (Request $request): Limit {
            $user  = $request->user();
            $token = $user?->currentAccessToken();

            /*
             | A session-authenticated (stateful) request yields a TransientToken,
             | which has no id — reading ->id on it raised an ErrorException and
             | took the whole request down. There is no per-token budget to look
             | up in that case, so fall back to the user.
             */
            if (! $token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                return Limit::perMinute(30)->by($user !== null ? 'user:' . $user->id : (string) $request->ip());
            }

            // Per-token override lives in api_token_rate_limits; absent (or
            // deactivated) rows fall back to the global default.
            $override = \Illuminate\Support\Facades\DB::table('api_token_rate_limits')
                ->where('token_id', $token->id)
                ->where('is_active', true)
                ->value('requests_per_minute');

            $perMinute = (int) ($override ?? 60);

            return Limit::perMinute(max(1, $perMinute))->by('token:' . $token->id);
        });
    }
}
