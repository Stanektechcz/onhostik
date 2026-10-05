<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\VerifyEmailNotification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Owner decision R5 (audit 2026-10, TASK-0096): an e-mail that is not verified blocks partner payouts and orders above
 * 5 000 Kč, and a person can have the verification mail sent again.
 *
 * Blocked: an order above the limit (every door — panel, guest checkout, the bus under a token), requesting a partner
 * payout (the automatic one too) and paying one out. Kept: sign-in, profile, reading, smaller orders, tickets, the resend.
 *
 * The question is asked where the money moves (CheckoutService::placeOrder, PartnerPayouts), from the stored user row on
 * every call, so verifying lifts the block with the very next request — nothing is cached in a session or a token. A
 * personal token acts as its owner (ApiContext), so it follows the rule; staff, service accounts and the system are not
 * asked (staff work on a customer's behalf, and a service account has no mailbox).
 */
final class EmailVerificationGuard
{
    /** The order limit in minor units per currency, "above" this is blocked; `onhost.identity.unverified_order_limit_minor` (array by currency) overrides it. */
    public const DEFAULT_ORDER_LIMIT_MINOR = ['CZK' => 500000, 'EUR' => 20000];

    public const RESEND_PATH = '/v1/me/email/verification';

    /** A resend at most once a minute and five an hour. */
    private const RESEND_GAP_SECONDS = 60;

    private const RESEND_PER_HOUR = 5;

    public static function orderLimitMinor(string $currency): int
    {
        $configured = config('onhost.identity.unverified_order_limit_minor');
        $limits = is_array($configured) ? $configured + self::DEFAULT_ORDER_LIMIT_MINOR : self::DEFAULT_ORDER_LIMIT_MINOR;
        $currency = strtoupper($currency);

        return (int) ($limits[$currency] ?? $limits['CZK']);
    }

    /** An order of this total (VAT included) is refused when the person placing it has not verified the e-mail. */
    public static function assertMayPlaceOrder(int $totalMinor, string $currency, CommandContext $context): void
    {
        if ($totalMinor <= self::orderLimitMinor($currency)) {
            return;
        }
        $actor = self::person($context);
        if ($actor === null || StaffActor::account($actor) || $actor->email_verified_at !== null) {
            return;
        }
        throw new DomainError('email_unverified', 'Objednávku nad 5 000 Kč můžete odeslat, až ověříte svůj e-mail. Poslali jsme vám ověřovací odkaz, případně si ho nechte poslat znovu.', 403, ['action' => 'order', 'resend' => self::RESEND_PATH]);
    }

    /** A payout goes to a partner whose owner has verified the e-mail; asked of the request, the automatic request and the payment alike. */
    public static function assertMayReceivePayout(Organization $partnerOrganization): void
    {
        $owner = $partnerOrganization->owner_user_id !== null ? User::query()->find($partnerOrganization->owner_user_id) : null;
        if ($owner === null || $owner->email_verified_at !== null) {
            return;
        }
        throw new DomainError('email_unverified', 'Výplata provize je možná, až majitel účtu ověří svůj e-mail. Ověřovací odkaz si můžete nechat poslat znovu.', 403, ['action' => 'payout', 'resend' => self::RESEND_PATH]);
    }

    /** Sends a fresh verification link, replacing the earlier unused ones; refuses a verified address and a hasty repeat. */
    public static function resend(User $user): void
    {
        if ($user->email_verified_at !== null) {
            throw new DomainError('email_already_verified', 'Váš e-mail je už ověřený.', 409);
        }
        $recent = EmailVerificationToken::query()->where('user_id', $user->id)->where('purpose', 'verify')->where('created_at', '>=', now()->subHour())->orderByDesc('created_at')->get();
        $last = $recent->first()?->created_at;
        if ($last !== null && $last->diffInSeconds(now(), true) < self::RESEND_GAP_SECONDS) {
            throw new DomainError('email_verification_throttled', 'Ověřovací e-mail jsme právě poslali; další si můžete vyžádat za minutu.', 429, ['retry_after' => self::RESEND_GAP_SECONDS]);
        }
        if ($recent->count() >= self::RESEND_PER_HOUR) {
            throw new DomainError('email_verification_throttled', 'Ověřovací e-mail jsme za poslední hodinu poslali několikrát; zkuste to později.', 429, ['retry_after' => 3600]);
        }
        self::issue($user);
    }

    /** Creates the single-use link (3 days, the older ones stop working) and mails it; the secret goes by mail only. */
    public static function issue(User $user, ?string $organizationName = null): void
    {
        $token = Str::random(48);
        EmailVerificationToken::query()->where('user_id', $user->id)->where('purpose', 'verify')->whereNull('used_at')->update(['used_at' => now()]);
        EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'purpose' => 'verify', 'expires_at' => now()->addDays(3)]);
        $user->notify(new VerifyEmailNotification($token, $organizationName));
    }

    private static function person(CommandContext $context): ?User
    {
        if (! in_array($context->actorType, ['user', 'ai'], true)) {
            return null;
        }
        $id = $context->onBehalfOfUserId ?? $context->actorId;

        return $id !== null ? User::query()->find($id) : null;
    }
}
