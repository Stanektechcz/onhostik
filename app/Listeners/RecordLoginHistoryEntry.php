<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final class RecordLoginHistoryEntry
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        UserLoginHistory::create([
            'user_id'    => $event->user->id,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
