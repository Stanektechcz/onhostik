<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Notifications\TwoFactorDisabledNotification;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class HandleTwoFactorAuthenticationDisabled
{
    public function handle(TwoFactorAuthenticationDisabled $event): void
    {
        /** @var \App\Models\User $user */
        $user = $event->user;

        activity()->causedBy($user)->log('two_factor_disabled');

        $user->notify(new TwoFactorDisabledNotification());
    }
}
