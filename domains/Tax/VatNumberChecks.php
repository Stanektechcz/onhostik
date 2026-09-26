<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\VatCheckResult;
use Onhost\Providers\Contracts\VatNumberValidator;

/**
 * Asks VIES about an organization's number and records a verdict through the bus (TASK-0031, D31.3). The HTTP call is made
 * here, before and outside the bus transaction: a register that takes eight seconds must not hold a row lock. Only a
 * verdict is recorded — `unknown` writes nothing, publishes nothing and keeps what was known before.
 *
 * What is never asked and never called invalid: a number whose prefix is not an EU member state (a Swiss CHE…, a British
 * GB…) and an organization outside the EU — VIES does not know them and a customer there must not hear their number is
 * wrong. A number with an EU prefix that cannot be one is recorded `invalid` from its shape alone, without a call.
 *
 * Outcomes: valid | invalid | unknown (ask again later) | unavailable (asking again will not help: the check is off at
 * VIES's side, our requester details were refused) | skipped (nothing to ask) | discarded (the number changed meanwhile).
 */
final class VatNumberChecks
{
    public const TRIGGERS = ['vat_id_changed', 'checkout', 'recheck', 'operator'];

    /** The width of the columns that keep the number a verdict is about. */
    private const MAX_STORED = 20;

    public function __construct(private readonly CommandBus $bus, private readonly CacheRepository $cache) {}

    public function check(Organization $organization, string $trigger, ?int $timeoutSeconds = null): string
    {
        $trigger = in_array($trigger, self::TRIGGERS, true) ? $trigger : 'operator';
        if (! (bool) config('onhost.vies.enabled', false)) {
            return 'skipped';
        }
        if ($trigger !== 'operator' && VatStanding::standing($organization)['reason'] === 'staff_override') {
            // a four-eyes decision holds until it ends (review round 1): a customer re-saving the form, a checkout or the monthly
            // re-check must not let a VIES answer replace it — finance set "invalid" precisely because VIES says "valid"
            return 'skipped';
        }
        $subject = VatStanding::subject($organization);
        if ($subject === null || ! $subject->isEuPrefixed() || ! in_array(strtoupper((string) $organization->country), VatNumber::euMembers(), true)) {
            return 'skipped';
        }
        if (strlen($subject->value) > self::MAX_STORED) {
            // a 19–20 digit number typed without a prefix, prefixed with the country: no member state's number is that long,
            // and the evidence columns (vat_validations.vat_id, vat_checked_number) hold 20 — nothing to ask, nothing to keep
            return 'skipped';
        }
        if (! $subject->isWellFormed()) {
            return $this->record($organization, $subject, VatCheckResult::invalid('malformed'), $trigger, 'format');
        }
        $throttle = 'onhost:vies:attempt:'.$organization->id.':'.sha1($subject->value);
        if ($trigger === 'checkout' && $this->cache->has($throttle)) {
            return 'unknown'; // every cart quote would ask again a VIES that has just not answered
        }

        $result = app(VatNumberValidator::class)->check((string) $subject->countryCode(), $subject->number(), $timeoutSeconds ?? (int) config('onhost.vies.timeout_seconds', 8));
        if (! $result->isKnown()) {
            $this->cache->put($throttle, (string) $result->errorCode, now()->addMinutes(max(1, (int) config('onhost.vies.retry_after_minutes', 10))));

            return $result->retryable ? 'unknown' : 'unavailable';
        }

        return $this->record($organization, $subject, $result, $trigger, 'vies');
    }

    /**
     * The quick check before a quote (D31.3b): only for a number the tax decision could use and does not know now, with the
     * short checkout timeout; a failure leaves it unknown (destination VAT with a review flag, decided elsewhere).
     */
    public function refreshBeforeQuote(Organization $organization): Organization
    {
        if (VatStanding::needsCheck($organization)) {
            $this->check($organization, 'checkout', (int) config('onhost.vies.checkout_timeout_seconds', 5));
            $organization->refresh();
        }

        return $organization;
    }

    /** The count an outcome belongs to in an operator's summary: valid | invalid | unknown | skipped. */
    public static function tally(string $outcome): string
    {
        return match ($outcome) {
            'valid', 'invalid' => $outcome,
            'unknown', 'unavailable' => 'unknown',
            default => 'skipped',
        };
    }

    private function record(Organization $organization, VatNumber $subject, VatCheckResult $result, string $trigger, string $source): string
    {
        $outcome = (array) $this->bus->dispatch(new RecordVatCheckCommand($organization->id, 'vat-check:'.$organization->id.':'.$subject->value.':'.Str::ulid(), [
            'number' => $subject->value,
            'status' => $result->status,
            'consultation_number' => $result->consultationNumber,
            'name' => $result->name,
            'address' => $result->address,
            'request_date' => $result->requestDate,
            'requester_vat_id' => mb_substr(strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) config('onhost.vies.requester_vat_id', ''))), 0, 20) ?: null,
            'trigger' => $trigger,
            'source' => $source,
            'error_code' => $result->errorCode,
        ]), CommandContext::system('tax.vies:'.$trigger));

        return ! empty($outcome['recorded']) ? $result->status : 'discarded';
    }
}
