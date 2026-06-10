<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Auth views rendered in the Antler front layout.
        Fortify::loginView(fn () => view('front.auth.login'));
        Fortify::registerView(fn () => view('front.auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('front.auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('front.auth.reset-password', ['request' => $request]));

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate($request->string(Fortify::username())->lower() . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
