<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\DomainRegistrationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ServicePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureFactories();
        $this->configureGates();
        $this->configurePolicies();
        $this->configureRateLimiters();
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
    }

    private function configureRateLimiters(): void
    {
        // Public domain availability search — keep WEDOS quota safe (100/h).
        RateLimiter::for('domain-check', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
