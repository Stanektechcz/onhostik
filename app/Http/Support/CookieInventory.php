<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Middleware\RememberReferral;

/**
 * The cookies a visitor of the public site can receive, listed on the privacy page under the document text (TASK-0179, staging
 * browser check 2026-10-13 B1). It is an annex, not part of the versioned privacy text: a published version's text and hash stay
 * frozen (LegalDocuments), while names and lifetimes follow the configuration that actually sets them.
 *
 * Besides the application's own cookies the hosting's web firewall (aaPanel WAF, `btwaf`) sets `server_session_<hash>` (the first
 * eight hex digits of an md5 of the site, 10 days, Secure, HttpOnly, SameSite=Lax). The application does not set or read it, but a
 * visitor receives it, so it is listed. `onhost.privacy.hosting_waf_cookie` = false drops the row on a host whose firewall cookie is off.
 */
final class CookieInventory
{
    public const XSRF_COOKIE = 'XSRF-TOKEN';

    public const WAF_COOKIE = 'server_session_<hash>';

    public const WAF_DAYS = 10;

    private const APPLICATION = 'ONhost (aplikace)';

    /** @return list<array{name:string, source:string, purpose:string, lifetime:string}> */
    public static function rows(): array
    {
        $session = self::sessionLifetime();
        $rows = [
            ['name' => (string) config('session.cookie'), 'source' => self::APPLICATION, 'purpose' => 'přihlášení, košík a rozpracovaná objednávka (relace)', 'lifetime' => $session],
            ['name' => self::XSRF_COOKIE, 'source' => self::APPLICATION, 'purpose' => 'ochrana formulářů před podvrženým odesláním (CSRF)', 'lifetime' => $session],
            ['name' => RememberReferral::enabled() ? RememberReferral::COOKIE : '', 'source' => self::APPLICATION, 'purpose' => 'kód doporučení partnera; vzniká jen po otevření odkazu s parametrem ?ref=', 'lifetime' => self::days(RememberReferral::DAYS)],
        ];
        $rows = array_values(array_filter($rows, fn (array $row) => $row['name'] !== '')); // P20: the referral cookie only while it is switched on
        if ((bool) config('onhost.privacy.hosting_waf_cookie', true)) {
            $rows[] = ['name' => self::WAF_COOKIE, 'source' => 'webový firewall serveru (aaPanel WAF), ne aplikace', 'purpose' => 'rozpoznání návštěvy firewallem kvůli ochraně webu před automatizovanými útoky a přetížením; <hash> je 8 znaků odvozených od názvu webu', 'lifetime' => self::days(self::WAF_DAYS)];
        }

        return $rows;
    }

    /** The session and the XSRF cookie live as long as the session (Laravel sets both from `session.lifetime`). */
    private static function sessionLifetime(): string
    {
        if ((bool) config('session.expire_on_close', false)) {
            return 'do zavření prohlížeče';
        }
        $minutes = max(1, (int) config('session.lifetime', 120));
        if ($minutes % 1440 === 0) {
            return self::days(intdiv($minutes, 1440));
        }
        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours.' '.self::plural($hours, 'hodina', 'hodiny', 'hodin');
        }

        return $minutes.' '.self::plural($minutes, 'minuta', 'minuty', 'minut');
    }

    private static function days(int $days): string
    {
        return $days.' '.self::plural($days, 'den', 'dny', 'dní');
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        return $n === 1 ? $one : ($n >= 2 && $n <= 4 ? $few : $many);
    }
}
