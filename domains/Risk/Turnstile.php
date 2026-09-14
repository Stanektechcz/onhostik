<?php

declare(strict_types=1);

namespace Onhost\Domain\Risk;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Cloudflare Turnstile on registration and checkout (audit §5q-6). The surfaces render the widget when the boot
 * object carries the site key and send its token as `turnstile` (or the `CF-Turnstile-Response` header). The
 * platform verifies it once per request at `siteverify` and keeps the outcome in the request context: registration
 * refuses a missing or failed check (`enforce_register`), the order check scores it as one more risk signal
 * (`turnstile_failed`) — a bot without a token is not blocked, its order waits for a person. Off without keys.
 */
final class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const CONTEXT_KEY = 'turnstile';

    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const MISSING = 'missing';

    public const OFF = 'off';

    public function __construct(private readonly HttpFactory $http) {}

    public function enabled(): bool
    {
        return (string) config('onhost.turnstile.secret', '') !== '' && (string) config('onhost.turnstile.site_key', '') !== '';
    }

    /** Verifies the request's token once and remembers the outcome for the risk check. @return self::PASS|self::FAIL|self::MISSING|self::OFF */
    public function check(Request $request): string
    {
        if (! $this->enabled()) {
            Context::add(self::CONTEXT_KEY, self::OFF);

            return self::OFF;
        }
        $token = (string) ($request->input('turnstile') ?: $request->header('CF-Turnstile-Response', ''));
        $result = $this->verify($token, $request->ip());
        Context::add(self::CONTEXT_KEY, $result);

        return $result;
    }

    /** @return self::PASS|self::FAIL|self::MISSING */
    public function verify(string $token, ?string $ip): string
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 2048) {
            return self::MISSING;
        }
        try {
            $response = $this->http->asForm()->timeout(max(1, (int) config('onhost.turnstile.timeout_seconds', 3)))->connectTimeout(2)->post(self::VERIFY_URL, array_filter(['secret' => (string) config('onhost.turnstile.secret'), 'response' => $token, 'remoteip' => $ip]));
        } catch (Throwable) {
            return self::PASS; // the verifier being unreachable must never lock customers out; the risk check still sees a pass
        }

        return $response->successful() && $response->json('success') === true ? self::PASS : self::FAIL;
    }

    /** The outcome of this request's check (`off` when nothing ran). */
    public static function result(): string
    {
        $value = Context::get(self::CONTEXT_KEY);

        return is_string($value) && $value !== '' ? $value : self::OFF;
    }

    /** Registration refuses a missing or failed check while `enforce_register` is on. */
    public function requireForRegistration(Request $request): void
    {
        $result = $this->check($request);
        if ($result === self::OFF || ! (bool) config('onhost.turnstile.enforce_register', true) || $result === self::PASS) {
            return;
        }
        throw new DomainError('turnstile_required', 'Ověření, že nejste robot, chybí nebo neprošlo. Zkuste to prosím znovu.', 422, ['field' => 'turnstile', 'result' => $result]);
    }
}
