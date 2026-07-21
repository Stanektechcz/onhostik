<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Audit J141: the context attached to every reported exception.
 *
 * Deliberately narrow. An error report is read by whoever is on call and, once
 * a tracker is configured, is stored by a third party — so it carries what is
 * needed to reproduce the failure (who, where, which request) and nothing that
 * would be damaging to leak.
 *
 * Notably absent: the request body and the full query string. Those are where
 * passwords, card details and API keys live. If a specific handler needs a
 * field, it should log that field explicitly and redacted.
 */
final class ErrorContext
{
    /** @return array<string, mixed> */
    public static function current(): array
    {
        $context = [
            'env' => (string) config('app.env'),
        ];

        $request = app()->bound('request') ? request() : null;
        $route   = $request?->route();

        /*
         | A resolved route means this really is a request being served. Keying
         | on runningInConsole() instead was wrong twice over: a queue worker
         | still has a Request object built from argv, and `php artisan test`
         | is console even while it is serving requests.
         */
        if ($request !== null && ($route !== null || ! app()->runningInConsole())) {
            $context['request_id'] = (string) $request->attributes->get('request_id', '');
            $context['method']     = $request->method();
            // Route pattern, not the resolved URL: /panel/faktury/{invoice}
            // groups every failure of one endpoint together, and keeps ids out.
            $context['route']      = $route?->uri() ?? $request->path();
            $context['ip']         = (string) $request->ip();
        }

        $user = Auth::user();

        if ($user !== null) {
            // The id is enough to find the account; the email is personal data
            // that would then live in a third party's error store indefinitely.
            $context['user_id'] = $user->getAuthIdentifier();
        }

        /** @var array<string, mixed> $redacted */
        $redacted = SecretRedactor::redact($context);

        return $redacted;
    }
}
