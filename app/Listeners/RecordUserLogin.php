<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final class RecordUserLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->timestamps = false;
        $event->user->update([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ]);
        $event->user->timestamps = true;
    }
}
