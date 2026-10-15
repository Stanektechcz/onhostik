<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Waitlist;

use Illuminate\Support\Str;
use Onhost\Domain\Content\Models\WaitlistSignup;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * E2 (owner decision E2, vlna 2 row 15, variant A): a waitlist with double opt-in. The signup is stored pending and an outbox
 * event `waitlist.signup.requested` asks NotificationRouter to send the confirmation mail; the raw token travels only inside
 * that mail (never through the redacted, durable outbox, never stored — only its sha256). The purpose is the one notice that
 * the product is available; nothing here feeds marketing. Unconfirmed signups are deleted after 30 days, confirmed ones 12
 * months after the confirmation (prune()). The answer to a signup is the same whether the address is new, pending or already
 * confirmed, so the form does not tell anybody who is on the list.
 */
final class WaitlistService
{
    /** The consent texts by version; a signup must name the version the form showed. A changed text is a new version. */
    public const CONSENTS = [
        '2026-10-15' => [
            'cs' => 'Chci dostat e-mail, až bude {produkt} k dispozici. Souhlas mohu kdykoli odvolat.',
            'en' => 'I want to get an e-mail when {produkt} becomes available. I can withdraw this consent at any time.',
        ],
    ];

    public const CURRENT_CONSENT = '2026-10-15';

    public const PENDING_DAYS = 30;

    public const CONFIRMED_MONTHS = 12;

    /** A pending signup asked for again within this many minutes gets no second mail (the per-address rate limit). */
    public const RESEND_MINUTES = 10;

    public const PRODUCT_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    public function __construct(private readonly OutboxPublisher $outbox, private readonly NotificationService $notifications) {}

    /** @return array{state:string} */
    public function signup(string $email, string $product, string $consentVersion, string $locale, ?string $ip): array
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            throw new DomainError('waitlist_email_invalid', 'A valid e-mail is required.', 422, ['field' => 'email']);
        }
        if (preg_match(self::PRODUCT_PATTERN, $product) !== 1) {
            throw new DomainError('waitlist_product_invalid', 'Unknown product.', 422, ['field' => 'product']);
        }
        if ($consentVersion !== self::CURRENT_CONSENT) {
            throw new DomainError('waitlist_consent_outdated', 'The consent text has changed; reload the form.', 422, ['field' => 'consent_version', 'current' => self::CURRENT_CONSENT]);
        }
        $locale = $locale === 'en' ? 'en' : 'cs';
        $signup = WaitlistSignup::query()->where('email', $email)->where('product', $product)->first();
        if ($signup?->state === WaitlistSignup::CONFIRMED) {
            return ['state' => 'pending_confirmation'];
        }
        if ($signup !== null && $signup->token_sent_at !== null && $signup->token_sent_at->gt(now()->subMinutes(self::RESEND_MINUTES))) {
            return ['state' => 'pending_confirmation'];
        }
        $signup ??= new WaitlistSignup(['email' => $email, 'product' => $product, 'state' => WaitlistSignup::PENDING]);
        $signup->forceFill(['locale' => $locale, 'consent_version' => $consentVersion, 'ip_hash' => self::ipHash($ip), 'token_sent_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of('waitlist.signup.requested', 'waitlist_signup', $signup->id, ['product' => $product, 'locale' => $locale]));

        return ['state' => 'pending_confirmation'];
    }

    /** Called by NotificationRouter for `waitlist.signup.requested`: a fresh token, its hash stored, the raw value in the mail only. */
    public function sendConfirmation(string $signupId): void
    {
        $signup = WaitlistSignup::query()->find($signupId);
        if ($signup === null || $signup->state !== WaitlistSignup::PENDING) {
            return;
        }
        $token = Str::random(48);
        $signup->forceFill(['token_hash' => hash('sha256', $token), 'token_sent_at' => now()])->save();
        $base = rtrim((string) config('onhost.portal_url', config('app.url')), '/').'/cekaci-listina';
        $this->notifications->queueMail('waitlist-confirm', $signup->email, [
            'produkt' => $signup->product,
            'souhlas' => self::consentText($signup->consent_version, $signup->locale, $signup->product),
            'url' => $base.'?potvrdit='.rawurlencode($token),
            'odvolat' => $base.'?odvolat='.rawurlencode($token),
            'dni' => (string) self::PENDING_DAYS,
        ], 'waitlist_signup', $signup->id, null, $signup->locale);
    }

    /** @return array{state:string, product:string} */
    public function confirm(string $token): array
    {
        $signup = $this->byToken($token);
        if ($signup->state === WaitlistSignup::PENDING) {
            if ($signup->created_at !== null && $signup->created_at->lt(now()->subDays(self::PENDING_DAYS))) {
                throw $this->invalidToken();
            }
            $signup->forceFill(['state' => WaitlistSignup::CONFIRMED, 'confirmed_at' => now()])->save();
            $this->outbox->publish(GenericEvent::of('waitlist.signup.confirmed', 'waitlist_signup', $signup->id, ['product' => $signup->product, 'consent_version' => $signup->consent_version]));
        }

        return ['state' => WaitlistSignup::CONFIRMED, 'product' => $signup->product];
    }

    /** Withdrawing the consent deletes the signup at once. @return array{withdrawn:bool} */
    public function withdraw(string $token): array
    {
        $signup = $this->byToken($token);
        $id = $signup->id;
        $product = $signup->product;
        $signup->delete();
        $this->outbox->publish(GenericEvent::of('waitlist.signup.withdrawn', 'waitlist_signup', $id, ['product' => $product]));

        return ['withdrawn' => true];
    }

    /** Retention: pending after 30 days, confirmed 12 months after the confirmation. @return array{pending:int, confirmed:int} */
    public function prune(): array
    {
        $pending = WaitlistSignup::query()->where('state', WaitlistSignup::PENDING)->where('created_at', '<', now()->subDays(self::PENDING_DAYS))->delete();
        $confirmed = WaitlistSignup::query()->where('state', WaitlistSignup::CONFIRMED)->where('confirmed_at', '<', now()->subMonths(self::CONFIRMED_MONTHS))->delete();

        return ['pending' => (int) $pending, 'confirmed' => (int) $confirmed];
    }

    public static function consentText(string $version, string $locale, string $product): string
    {
        $texts = self::CONSENTS[$version] ?? self::CONSENTS[self::CURRENT_CONSENT];

        return str_replace('{produkt}', $product, $texts[$locale] ?? $texts['cs']);
    }

    /** Keyed with the app key so the stored value cannot be reversed by hashing the IPv4 space. */
    public static function ipHash(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function byToken(string $token): WaitlistSignup
    {
        if ($token === '' || strlen($token) > 128) {
            throw $this->invalidToken();
        }

        return WaitlistSignup::query()->where('token_hash', hash('sha256', $token))->first() ?? throw $this->invalidToken();
    }

    private function invalidToken(): DomainError
    {
        return new DomainError('waitlist_token_invalid', 'The link is not valid or has expired.', 404, ['field' => 'token']);
    }
}
