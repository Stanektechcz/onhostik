<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Tax\Models\VatValidation;

/**
 * The doctor's four VIES rows (TASK-0031, D31.8). All read the database only — the doctor never calls VIES — and none
 * blocks a deploy: the check is off by default and a customer waiting for it is charged destination VAT, which is the
 * safe side. Also the organization lists the operator command starts from.
 */
final class VatHealth
{
    /** How old the last VIES answer may be while somebody waits for one. */
    public const ANSWER_MAX_DAYS = 7;

    public function __construct(private readonly AutomationLedger $ledger) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool}> */
    public function checks(): array
    {
        $requester = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) config('onhost.vies.requester_vat_id', '')));
        $enabled = (bool) config('onhost.vies.enabled', false);
        $configured = $enabled && preg_match('/^CZ\d{8,10}$/', $requester) === 1;
        $waiting = $this->waiting();
        $legacy = $this->legacy()->count();
        $last = VatValidation::query()->where('source', 'vies')->max('checked_at');
        $lastAt = $last === null ? null : CarbonImmutable::parse((string) $last);
        $answered = $lastAt !== null && $lastAt->greaterThanOrEqualTo(now()->subDays(self::ANSWER_MAX_DAYS));
        $lapsing = $this->lapsing();
        $recheckOn = $this->ledger->enabled('tax.vies_recheck');

        return [
            $this->row('VIES checks are on and the requester VAT ID is set', $configured, $configured
                ? "requester {$requester}"
                : ($enabled ? '' : 'ONHOST_VIES_ENABLED=false — no VAT number is checked, every EU business customer pays destination VAT; ').($requester === '' ? 'ONHOST_VIES_REQUESTER_VAT_ID (or ONHOST_VAT_ID) is empty — VIES answers without a consultation number' : (preg_match('/^CZ\d{8,10}$/', $requester) === 1 ? "requester {$requester}" : "ONHOST_VIES_REQUESTER_VAT_ID {$requester} is not a Czech VAT ID"))),
            $this->row('no EU business customer with a VAT ID waits for a VIES answer', $waiting === 0 && $legacy === 0,
                "{$waiting} — charged destination VAT with a review flag; {$legacy} legacy (valid/payer written before the check, never verified); onhost:vat:verify lists them"),
            $this->row('VIES answered recently', $answered || $waiting === 0,
                $lastAt === null ? 'no VIES answer recorded yet' : 'last answer '.$lastAt->toIso8601String().($answered ? '' : ' — older than '.self::ANSWER_MAX_DAYS.' days while customers wait')),
            // with VIES on, the re-check belongs to the switch (review round 1): without it every customer verified at the order is
            // charged destination VAT at its first renewal more than 30 days later — red before the first one lapses, not after
            $this->row('no reverse charge lapses while tax.vies_recheck is off', $recheckOn || ($lapsing === 0 && ! $enabled),
                $recheckOn ? 'the monthly re-check is on' : "{$lapsing} VIES-valid organizations checked more than ".self::recheckAfterDays().' days ago'
                    .($enabled ? '; VIES is on and tax.vies_recheck is off — a verified customer pays destination VAT from its first renewal more than '.VatStanding::freshnessDays().' days after the check' : '')
                    .'; switch on tax.vies_recheck or run onhost:vat:verify --apply'),
        ];
    }

    /** EU business customers of another member state with a number whose standing is unknown now. */
    public function waiting(): int
    {
        $count = 0;
        foreach ($this->euBusinesses()->lazyById(500) as $organization) {
            $subject = VatStanding::subject($organization);
            if ($subject !== null && $subject->isEuPrefixed() && VatStanding::effectiveStatus($organization) === VatStanding::UNKNOWN) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Organizations still open whose VAT number the platform has to think about, of another EU state, business class.
     *
     * @return Builder<Organization>
     */
    public function euBusinesses(): Builder
    {
        $supplier = VatNumber::supplierCountry();

        return self::open()->where('customer_class', 'b2b')->whereIn('country', array_values(array_diff(VatNumber::euMembers(), [$supplier])))
            ->where(fn (Builder $q) => $q->where(fn (Builder $v) => $v->whereNotNull('vat_id')->where('vat_id', '!=', ''))->orWhere(fn (Builder $d) => $d->whereNotNull('dic')->where('dic', '!=', '')));
    }

    /**
     * Rows from before the check whose stored value still changes money (valid → reverse charge, payer → self-billing VAT).
     *
     * @return Builder<Organization>
     */
    public function legacy(): Builder
    {
        return self::open()->whereNull('vat_status_source')->whereIn('vat_status', [VatStanding::VALID, 'payer']);
    }

    /**
     * VIES-valid organizations whose answer is older than the re-check age.
     *
     * @return Builder<Organization>
     */
    public function staleValid(): Builder
    {
        return self::open()->where('vat_status', VatStanding::VALID)->where('vat_status_source', 'vies')->where('vat_checked_at', '<', now()->subDays(self::recheckAfterDays()));
    }

    public static function recheckAfterDays(): int
    {
        return max(1, (int) config('onhost.vies.recheck_after_days', 25));
    }

    /** @return Builder<Organization> */
    public static function open(): Builder
    {
        return Organization::query()->where('state', '!=', 'closed')->whereNull('closed_at');
    }

    private function lapsing(): int
    {
        return $this->staleValid()->count();
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool} */
    private function row(string $check, bool $ok, string $detail): array
    {
        return ['area' => 'tax', 'check' => $check, 'ok' => $ok, 'detail' => $detail, 'blocking' => false];
    }
}
