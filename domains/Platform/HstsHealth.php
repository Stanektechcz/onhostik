<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * P11 (phase Q, P-R74 / HS-1): Strict-Transport-Security was sent twice on staging (2026-10-14, finding 3) — by the application
 * and by the aaPanel vhost. The application alone sends it now, as `onhost.security.hsts` says; this row asks the application's
 * own public address once (`APP_URL/up`, no other host, no redirect followed) and counts the header: exactly one, equal to the
 * configured value. Only on staging and in production — elsewhere APP_URL is a developer's machine.
 */
final class HstsHealth
{
    public const CHECK = 'HSTS is sent once and as configured';

    private const TIMEOUT_SECONDS = 5;

    /** @return list<array{area: string, check: string, ok: bool, detail: string, blocking: bool, remedy?: string}> */
    public function checks(): array
    {
        if (! app()->environment(['production', 'staging'])) {
            return [$this->row(true, 'checked only on staging and production (APP_ENV='.app()->environment().')')];
        }
        $expected = SecurityHeaders::hstsValue();
        $url = rtrim((string) config('app.url'), '/').'/up';
        if (! str_starts_with($url, 'https://')) {
            return [$this->row(false, 'APP_URL is not https, so no browser keeps HSTS', 'set APP_URL to the https origin')];
        }
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->withoutRedirecting()->get($url);
        } catch (Throwable $e) {
            return [$this->row(false, 'could not reach '.$url.': '.mb_substr($e->getMessage(), 0, 120), 'check that the site answers on its own address (nginx -t, /up)')];
        }
        $values = array_values(array_filter(array_map('trim', $response->toPsrResponse()->getHeader('Strict-Transport-Security')), fn (string $v) => $v !== ''));
        $count = count($values);
        if ($expected === null) {
            return [$count === 0
                ? $this->row(true, 'off by configuration and not sent')
                : $this->row(false, $count.'× sent although switched off: '.implode(' | ', $values), 'switch the vhost\'s HSTS off in aaPanel (docs/runbooks/hsts.md)')];
        }
        if ($count === 1 && $values[0] === $expected) {
            return [$this->row(true, '1× '.$expected)];
        }
        if ($count === 0) {
            return [$this->row(false, 'not sent (expected '.$expected.')', 'check SecurityHeaders and the proxy in front of the application')];
        }
        if ($count > 1) {
            return [$this->row(false, $count.'× ('.implode(' | ', $values).'), expected once: '.$expected,
                'switch the vhost\'s HSTS off in aaPanel, the application sends it (docs/runbooks/hsts.md)')];
        }

        return [$this->row(false, '1× '.$values[0].', expected '.$expected, 'the web server rewrites the header: switch the vhost\'s HSTS off in aaPanel (docs/runbooks/hsts.md)')];
    }

    /** @return array{area: string, check: string, ok: bool, detail: string, blocking: bool, remedy: string} */
    private function row(bool $ok, string $detail, string $remedy = ''): array
    {
        return ['area' => 'security', 'check' => self::CHECK, 'ok' => $ok, 'detail' => $detail, 'blocking' => false, 'remedy' => $remedy];
    }
}
