<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\NewIpLoginNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

final class TrackSecurityEvent
{
    public function __construct(private readonly Request $request) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user      = $event->user;
        $currentIp = $this->request->ip() ?? '0.0.0.0';

        // Detect new IP: compare against stored last_login_ip BEFORE RecordUserLogin updates it
        $isNewIp = $user->last_login_ip !== null
            && $user->last_login_ip !== $currentIp;

        $eventType = $isNewIp ? 'new_ip_login' : 'login';

        SecurityEvent::create([
            'user_id'    => $user->id,
            'event_type' => $eventType,
            'ip_address' => $currentIp,
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
            'email'      => $user->email,
            'metadata'   => $isNewIp ? ['previous_ip' => $user->last_login_ip] : null,
        ]);

        if ($isNewIp) {
            $user->notify(new NewIpLoginNotification(
                $currentIp,
                (string) $this->request->userAgent(),
            ));
        }
    }

    public function handleFailed(Failed $event): void
    {
        SecurityEvent::create([
            'user_id'    => $event->user instanceof User ? $event->user->id : null,
            'event_type' => 'login_failed',
            'ip_address' => $this->request->ip() ?? '0.0.0.0',
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
            'email'      => $event->credentials['email'] ?? null,
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        SecurityEvent::create([
            'user_id'    => $event->user->id,
            'event_type' => 'logout',
            'ip_address' => $this->request->ip() ?? '0.0.0.0',
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
            'email'      => $event->user->email,
        ]);
    }
}
