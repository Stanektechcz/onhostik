<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Redaction\Redactor;

/**
 * The JSON body of a provider failure on the API (bootstrap/app.php renders it; the status comes from the taxonomy code there).
 * Never the vendor payload, the message redacted.
 *
 * Phase D5: the body named the vendor behind the customer's service — `"provider":"ispconfig"`, and again in the message's
 * `[ispconfig:TRANSIENT]` prefix. A customer is told what failed and whether to retry; which panel, registrar or gateway it was
 * is for staff (the VendorNeutralityTest rule, now for error bodies too). It is kept only for a member of staff in staff mode
 * (a /v1/staff/* request without a token, ApiContext::staffMode) — on a customer route a member of staff is the customer.
 */
final class ProviderProblem
{
    /** @return array<string,mixed> */
    public static function body(ProviderException $e, Request $request, int $status, Redactor $redactor): array
    {
        $staff = self::forStaff($request);
        $message = $redactor->redactString($staff ? $e->getMessage() : self::withoutVendor($e));

        return ['error' => 'provider_'.strtolower($e->errorCode->value)]
            + ($staff ? ['provider' => $e->provider] : [])
            + ['message' => $message, 'status' => $status, 'retryable' => $e->isRetryable()];
    }

    private static function forStaff(Request $request): bool
    {
        $user = $request->user();

        return ApiContext::staffMode($request) && $user instanceof User && StaffActor::account($user) && $user->isActive();
    }

    /** The message without the `[provider:CODE] ` prefix ProviderException puts in front of it. */
    private static function withoutVendor(ProviderException $e): string
    {
        $prefix = sprintf('[%s:%s] ', $e->provider, $e->errorCode->value);
        $message = $e->getMessage();

        return str_starts_with($message, $prefix) ? substr($message, strlen($prefix)) : $message;
    }
}
