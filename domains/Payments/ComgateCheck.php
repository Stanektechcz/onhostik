<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Illuminate\Support\Str;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Platform\Settings\SettingsStore;
use Onhost\Providers\Payments\Comgate\ComgateMode;
use Onhost\Providers\Payments\Comgate\ComgatePaymentProvider;
use Throwable;

/**
 * The administration's Comgate check (owner decision H-R8, 2026-10-07): ONhost has no Comgate test account yet, so staff need to
 * see — before the first real payment — whether the platform holds the credentials, which mode the gateway is in, and whether
 * the gateway answers. Two checks, both run through `ComgateCheckCommand` (staff permission, step-up, audit):
 *
 *  - `connection`: an authenticated call with no side effect (the merchant's payment methods);
 *  - `payment`: a 1 Kč TEST payment created and read back — always `test: true`, never a payment of the platform.
 *
 * Credentials come from the vault reference `onhost.payments.comgate.secret_ref` (`env://COMGATE` by default, or `db://…`). Missing
 * credentials are reported, no request is sent. Nothing here returns, stores or logs a credential: the result keeps the HTTP
 * status, the gateway's code and a message with any credential value masked; the provider-call log redacts the Authorization
 * header by name.
 */
final class ComgateCheck
{
    public const LAST_CHECK = 'payments.comgate.last_check';

    public const KINDS = ['connection', 'payment'];

    public function __construct(private readonly SettingsStore $settings) {}

    /** @return array<string,mixed> what the administration shows: never a credential */
    public function status(): array
    {
        $state = $this->provider()->credentialState();
        $ref = (string) config('onhost.payments.comgate.secret_ref', 'env://COMGATE');
        $merchant = (string) (config('onhost.payments.comgate.merchant') ?? '');

        return [
            'provider' => 'comgate', 'default_gateway' => config('onhost.payments.default') === 'comgate',
            'credentials' => ['merchant' => $state['merchant'], 'secret' => $state['secret'], 'store' => self::scheme($ref), 'error' => $state['error']],
            'configured' => $state['merchant'] && $state['secret'],
            'test_mode' => ComgateMode::test(), 'test_mode_source' => ComgateMode::source(), 'test_mode_env' => (bool) config('onhost.payments.comgate.test', true),
            'gateway_host' => (string) (parse_url((string) config('onhost.payments.comgate.base_url'), PHP_URL_HOST) ?: ''),
            'recurring' => (bool) config('onhost.payments.comgate.recurring', true),
            'callback_allowlist' => count((array) config('onhost.payments.comgate.callback_allowlist', [])),
            'merchant_hint' => $merchant !== '' ? str_repeat('•', max(0, mb_strlen($merchant) - 3)).mb_substr($merchant, -3) : null,
            'last_check' => $this->settings->get(self::LAST_CHECK),
            'production' => app()->environment('production'),
        ];
    }

    /**
     * Runs one check and keeps its outcome (without any credential) for the administration and the doctor.
     *
     * @return array<string,mixed>
     */
    public function run(string $kind, ?string $actorId = null): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new DomainError('comgate_check_unknown', 'A check is `connection` or `payment`.', 422, ['field' => 'kind']);
        }
        $provider = $this->provider();
        $state = $provider->credentialState();
        $result = ['kind' => $kind, 'at' => now()->toIso8601String(), 'by' => $actorId, 'test_mode' => ComgateMode::test()];
        if (! $state['merchant'] || ! $state['secret']) {
            $missing = array_keys(array_filter(['merchant' => ! $state['merchant'], 'secret' => ! $state['secret']]));
            $result += ['ok' => false, 'error' => 'comgate_credentials_missing', 'missing' => $missing,
                'message' => 'Chybí přihlašovací údaje Comgate ('.implode(', ', $missing).') v úložišti '.self::scheme((string) config('onhost.payments.comgate.secret_ref', 'env://COMGATE')).'://… — nic se neodeslalo.'
                    .($state['error'] !== null ? ' '.$state['error'].'.' : '')];

            return $this->keep($result);
        }
        try {
            if ($kind === 'connection') {
                $probe = $provider->probe();
                $result += ['ok' => $probe['ok'], 'http' => $probe['http'], 'code' => $probe['code'], 'methods' => $probe['methods'],
                    'message' => $probe['ok'] ? "Comgate odpověděl, přihlašovací údaje platí; obchodník má {$probe['methods']} platebních metod." : self::refusal($probe['http'], $probe['code'], $probe['message'])];
                if (! $probe['ok']) {
                    $result['error'] = in_array($probe['http'], [401, 403], true) ? 'comgate_credentials_refused' : 'comgate_unreachable';
                }
            } else {
                $payment = $provider->testPayment('onhost-check-'.Str::lower((string) Str::ulid()));
                $result += ['ok' => true, 'trans_id' => $payment['trans_id'], 'status' => $payment['status'], 'redirect_url' => $payment['redirect_url'],
                    'message' => "Testovací platba 1 Kč vytvořena (testovací režim brány) a přečtena zpět: stav {$payment['status']}. Do účetnictví platformy se nic nezapsalo."];
            }
        } catch (Throwable $e) {
            $result += ['ok' => false, 'error' => $e instanceof DomainError ? $e->error : 'comgate_unreachable', 'message' => 'Comgate neodpověděl podle očekávání: '.mb_substr($e->getMessage(), 0, 300)];
        }

        return $this->keep($result);
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function keep(array $result): array
    {
        $result['message'] = $this->masked((string) ($result['message'] ?? ''));
        $this->settings->set(self::LAST_CHECK, $result, isset($result['by']) ? (string) $result['by'] : null);

        return $result;
    }

    /** Any credential value that found its way into a message (a gateway echoing what it was sent) is masked. */
    private function masked(string $message): string
    {
        try {
            $values = app(SecretStore::class)->read(SecretRef::parse((string) config('onhost.payments.comgate.secret_ref', 'env://COMGATE')));
        } catch (Throwable) {
            return $message;
        }
        foreach ($values as $value) {
            if (is_string($value) && mb_strlen($value) >= 4) {
                $message = str_replace($value, '***', $message);
            }
        }

        return $message;
    }

    private static function refusal(int $http, ?int $code, string $message): string
    {
        return match (true) {
            in_array($http, [401, 403], true) => "Comgate odmítl přihlašovací údaje (HTTP {$http}). Zkontrolujte ID obchodníka a heslo v úložišti.",
            $http === 404 => 'Comgate adresu nezná (HTTP 404): zkontrolujte COMGATE_BASE_URL (má končit /v2.0).',
            default => "Comgate odpověděl HTTP {$http}".($code !== null ? ", kód {$code}" : '').($message !== '' ? ": {$message}" : '').'.',
        };
    }

    /** The store a reference points at (`env`, `db`, …) — never the reference's own value. */
    private static function scheme(string $ref): string
    {
        return strtolower((string) (strstr($ref, '://', true) ?: 'env'));
    }

    private function provider(): ComgatePaymentProvider
    {
        return app()->make(ComgatePaymentProvider::class); // a fresh instance: a credential changed in the vault is read again
    }
}
