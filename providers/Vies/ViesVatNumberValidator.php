<?php

declare(strict_types=1);

namespace Onhost\Providers\Vies;

use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\VatCheckResult;
use Onhost\Providers\Contracts\VatNumberValidator;

/**
 * EU VIES REST `check-vat-number` (TASK-0031, D31.1; docs/provider-adapters/vies.md). The request names our own VAT ID as
 * the requester, so a valid answer carries a consultation number (`requestIdentifier`) — the evidence a tax audit asks
 * for when an invoice carries no VAT. Both answer shapes are read: a 200 with `valid` and sometimes a `userError`, and a
 * refusal `{actionSucceed:false, errorWrappers:[{error, message}]}` that arrives with HTTP 400, 500 or even 200.
 *
 * The breaker hears only what says something about VIES itself (judgedByCaller): one member state that cannot answer
 * (MS_UNAVAILABLE) is not VIES being down, a refused number is VIES working. The answer names the trader, so it is never
 * written to provider_calls (secretResponse) and never logged; the evidence is kept only in vat_validations.
 */
final class ViesVatNumberValidator implements VatNumberValidator
{
    public const PROVIDER = 'vies';

    /** VIES or a member state could not answer now; asking again later can help. */
    public const RETRYABLE = ['MS_UNAVAILABLE', 'TIMEOUT', 'SERVICE_UNAVAILABLE', 'GLOBAL_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ_TIME', 'MS_MAX_CONCURRENT_REQ', 'MS_MAX_CONCURRENT_REQ_TIME'];

    /** Refusals about us, not about the number: our requester details are wrong, or our address is blocked — the doctor's business. */
    public const FINAL = ['INVALID_REQUESTER_INFO', 'VAT_BLOCKED', 'IP_BLOCKED'];

    /** Codes that mean VIES as a whole is unwell (a member state's outage is not). */
    private const VIES_DOWN = ['SERVICE_UNAVAILABLE', 'GLOBAL_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ_TIME'];

    public function __construct(private readonly ProviderHttpClient $http) {}

    public function check(string $countryCode, string $number, int $timeoutSeconds): VatCheckResult
    {
        $country = strtoupper(trim($countryCode));
        $country = $country === 'GR' ? 'EL' : $country; // VIES knows Greece only as EL
        // our own quota before VIES's (review round 1): a flood of guest checkouts must not get our address or requester blocked
        // at VIES — that would end reverse charge for every customer. Beyond it the answer is unknown (destination VAT, retry).
        $this->http->configureBucket(self::PROVIDER, max(1, (int) config('onhost.vies.per_minute', 150)), 60);
        try {
            $response = $this->http->send(new ProviderRequest(
                provider: self::PROVIDER,
                instanceKey: self::PROVIDER,
                method: 'POST',
                url: (string) config('onhost.vies.endpoint'),
                action: 'check-vat-number',
                body: ['countryCode' => $country, 'vatNumber' => $number] + $this->requester(),
                timeoutSeconds: max(1, $timeoutSeconds),
                connectTimeoutSeconds: 3,
                idempotent: true,
                bucket: self::PROVIDER,
                secretResponse: true,
                judgedByCaller: true,
            ));
        } catch (ProviderException $e) {
            // no answer at all: a connection that failed, an open breaker, our own quota — never a verdict about the number
            return VatCheckResult::unknown($e->errorCode->value, true);
        }

        return $this->read($response);
    }

    private function read(ProviderResponse $response): VatCheckResult
    {
        $json = (array) ($response->json() ?? []);
        $userError = is_string($json['userError'] ?? null) ? strtoupper($json['userError']) : null;
        $wrapped = data_get($json, 'errorWrappers.0.error');
        $code = is_string($wrapped) && $wrapped !== '' ? strtoupper($wrapped) : (in_array($userError, [null, 'VALID', 'INVALID'], true) ? null : $userError);
        $requestDate = is_string($json['requestDate'] ?? null) ? $json['requestDate'] : null;

        if ($code !== null) {
            return $this->refusal($code);
        }
        if (is_bool($json['valid'] ?? null)) {
            $this->http->recordSuccess(self::PROVIDER);
            if ($json['valid'] === true) {
                $consultation = is_string($json['requestIdentifier'] ?? null) && trim($json['requestIdentifier']) !== '' ? mb_substr(trim($json['requestIdentifier']), 0, 80) : null;

                return VatCheckResult::valid($consultation, self::disclosed($json['name'] ?? null, 250), self::disclosed($json['address'] ?? null, 500), $requestDate);
            }

            return VatCheckResult::invalid(null, $requestDate);
        }
        if ($userError === 'INVALID') {
            $this->http->recordSuccess(self::PROVIDER);

            return VatCheckResult::invalid(null, $requestDate);
        }
        if ($response->status === 429) {
            return VatCheckResult::unknown('RATE_LIMITED', true);
        }
        if ($response->status >= 500) {
            $this->http->recordFailure(self::PROVIDER);

            return VatCheckResult::unknown('HTTP_'.$response->status, true);
        }

        return VatCheckResult::unknown('UNEXPECTED_ANSWER', true); // an answer nobody documented is not a verdict either
    }

    private function refusal(string $code): VatCheckResult
    {
        if ($code === 'INVALID_INPUT') {
            $this->http->recordSuccess(self::PROVIDER); // VIES answered; the number itself cannot be one

            return VatCheckResult::invalid('invalid_input');
        }
        if (in_array($code, self::VIES_DOWN, true)) {
            $this->http->recordFailure(self::PROVIDER);
        }
        if (in_array($code, self::FINAL, true)) {
            return VatCheckResult::unknown($code, false);
        }

        return VatCheckResult::unknown(mb_substr($code, 0, 40), true); // RETRYABLE and anything new: the number stays unknown, the job asks again later
    }

    /**
     * Our own VAT ID from the configuration, split the way VIES wants it (member state + number without the prefix). Without
     * it VIES still answers, only without a consultation number — the doctor reports the missing setting.
     *
     * @return array{requesterMemberStateCode?:string, requesterNumber?:string}
     */
    private function requester(): array
    {
        $own = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) config('onhost.vies.requester_vat_id', '')));
        if (preg_match('/^([A-Z]{2})([0-9A-Z]{2,12})$/', $own, $m) !== 1) {
            return [];
        }

        return ['requesterMemberStateCode' => $m[1] === 'GR' ? 'EL' : $m[1], 'requesterNumber' => $m[2]];
    }

    /** A trimmed value cut to the column, or null where the member state does not disclose it ("---"). */
    private static function disclosed(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $clean = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $clean === '' || $clean === '---' ? null : mb_substr($clean, 0, $max);
    }
}
