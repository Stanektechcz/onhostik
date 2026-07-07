<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Notifications\TwoFactorEnabledNotification;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

class HandleTwoFactorAuthenticationConfirmed
{
    public function handle(TwoFactorAuthenticationConfirmed $event): void
    {
        /** @var \App\Models\User $user */
        $user = $event->user;

        activity()->causedBy($user)->log('two_factor_enabled');

        $user->notify(new TwoFactorEnabledNotification());
    }
}
