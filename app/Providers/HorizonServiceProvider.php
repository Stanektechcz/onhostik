<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::routeMailNotificationsTo((string) config('billing.company_email', 'admin@onhost.cz'));
    }

    /** Only admins (role=admin) can access the Horizon dashboard. */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user): bool {
            return $user->hasRole('admin');
        });
    }
}
