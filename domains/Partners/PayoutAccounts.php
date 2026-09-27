<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\Models\PartnerPayoutAccount;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Where a partner's commission is paid (TASK-0040, permission program IF-14, audit P2).
 *
 * The IBAN used to be a field of every payout request and overwrote `partners.iban` with no step-up: whoever could request a
 * payout in the portal (every `organization.manage` holder, and the automatic payouts after them) decided where the money
 * went. Now the account is its own step — the partner organization's owner alone (`partner.payout_account.manage`, HIGH),
 * with a notice to the owner and the billing contact — and a new account is usable only after a cooling-off of
 * `COOLING_DAYS` (7, program §3), so a stolen session cannot redirect the next payout before anybody reads
 * the mail. An IBAN a payout was paid to before the cut-over (migration 000890) is the confirmed account without a row
 * (grandfathered, program D13): no existing partner has to set anything again, and nothing paid later becomes an account.
 */
final class PayoutAccounts
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** The cooling-off of a new payout account, in days (program §3: "7-day cooling-off"). A decision, not a setting. */
    public const COOLING_DAYS = 7;

    public static function coolingDays(): int
    {
        return self::COOLING_DAYS;
    }

    public static function normalIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    /** ISO 13616: the shape and the mod-97 check digits — a typo is caught before any money is sent to it. */
    public static function validIban(string $iban): bool
    {
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $rest = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $char) {
            $digits = ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
            foreach (str_split($digits) as $digit) {
                $rest = ($rest * 10 + (int) $digit) % 97;
            }
        }

        return $rest === 1;
    }

    /** The country and the last four characters: enough to recognise an account, not enough to use one. */
    public static function mask(?string $iban): ?string
    {
        if ($iban === null || $iban === '') {
            return null;
        }

        return strlen($iban) <= 8 ? '…' : substr($iban, 0, 4).'…'.substr($iban, -4);
    }

    /**
     * The account payouts go to now, and a change still cooling off.
     *
     * @return array{active: array{id: ?string, iban: string, source: string, since: ?Carbon}|null, pending: ?PartnerPayoutAccount}
     */
    public function of(Partner $partner, ?Carbon $at = null): array
    {
        $at ??= now();
        $rows = PartnerPayoutAccount::query()->where('partner_id', $partner->id)->whereNull('cancelled_at')->orderByDesc('usable_from')->orderByDesc('created_at')->get();
        $usable = $rows->first(fn (PartnerPayoutAccount $row) => $row->usable_from <= $at);
        $pending = $rows->first(fn (PartnerPayoutAccount $row) => $row->usable_from > $at);
        if ($usable !== null) {
            return ['active' => ['id' => $usable->id, 'iban' => $usable->iban, 'source' => 'owner', 'since' => $usable->usable_from], 'pending' => $pending];
        }
        $paid = self::grandfathered($partner->id)?->where('requested_at', '<=', $at)->orderByDesc('paid_at')->orderByDesc('requested_at')->first();

        return ['active' => $paid === null ? null : ['id' => null, 'iban' => self::normalIban((string) $paid->iban), 'source' => 'grandfathered', 'since' => $paid->paid_at ?? $paid->requested_at], 'pending' => $pending];
    }

    /**
     * Review round 1 (billing + security HIGH): grandfathering ends at the cut-over. The IBAN of a payout paid before migration
     * 000890 ran is the partner's confirmed account; one paid after it is not — a payout the old code left open carries the IBAN
     * its request typed (audit P2), and paying it (a first payout, or one finance released) must not make that IBAN the account
     * every later payout, automatic ones included, goes to (program §8 row 41, D13: only IBANs already paid when the task lands).
     * The migration writes the moment once; no staff screen writes this key. Without it nothing is grandfathered (the safe side).
     */
    public const GRANDFATHER_SETTING = 'partners.payout_account.grandfathered_before';

    public static function grandfatheredBefore(): ?Carbon
    {
        $value = DB::table('system_settings')->where('key', self::GRANDFATHER_SETTING)->value('value');
        $at = is_string($value) ? json_decode($value, true) : null;

        return is_string($at) && $at !== '' ? Carbon::parse($at) : null;
    }

    /**
     * The IBANs that were the partner's confirmed account at `$at`: an owner account usable by then (and not called off by then),
     * and the IBAN of a payout asked for before `$at` and paid before the cut-over. The pay step and the anomaly look compare a
     * payout's IBAN with this, as of the moment it was asked for.
     *
     * @return list<string>
     */
    public function confirmedAt(string $partnerId, Carbon $at, ?string $exceptPayoutId = null): array
    {
        $paid = self::grandfathered($partnerId)?->where('requested_at', '<', $at)->when($exceptPayoutId !== null, fn ($q) => $q->where('id', '!=', $exceptPayoutId))->pluck('iban') ?? collect();
        $accounts = PartnerPayoutAccount::query()->where('partner_id', $partnerId)->where('usable_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>', $at))->pluck('iban');

        return $paid->merge($accounts)->map(fn ($i) => self::normalIban((string) $i))->filter()->unique()->values()->all();
    }

    /** @return Builder<PartnerPayout>|null the partner's bank transfers paid before the cut-over; null when there is no cut-over */
    private static function grandfathered(string $partnerId): ?Builder
    {
        $before = self::grandfatheredBefore();
        if ($before === null) {
            return null;
        }

        return PartnerPayout::query()->where('partner_id', $partnerId)->where('state', 'paid')->where('method', 'bank_transfer')->whereNotNull('iban')->where('iban', '!=', '')
            ->where(fn ($q) => $q->where('paid_at', '<', $before)->orWhere(fn ($q) => $q->whereNull('paid_at')->where('requested_at', '<', $before)));
    }

    /** @return array<string,mixed> the portal's answer: masked IBANs only */
    public function present(Partner $partner): array
    {
        ['active' => $active, 'pending' => $pending] = $this->of($partner);

        return [
            'active' => $active === null ? null : ['iban_masked' => self::mask($active['iban']), 'source' => $active['source'], 'since' => $active['since']?->toIso8601String()],
            'pending' => $pending === null ? null : ['id' => $pending->id, 'iban_masked' => self::mask($pending->iban), 'usable_from' => $pending->usable_from->toIso8601String(), 'requested_at' => $pending->created_at?->toIso8601String()],
            'cooling_off_days' => self::coolingDays(),
        ];
    }

    /** A new account, usable after the cooling-off; a change still cooling off is replaced. The owner in person only. */
    public function change(Partner $partner, string $iban, CommandContext $context): PartnerPayoutAccount
    {
        $organization = $this->assertOwner($partner, $context);
        $iban = self::normalIban($iban);
        if (! self::validIban($iban)) {
            throw new DomainError('payout_iban_invalid', 'Enter a valid IBAN (Czech IBAN is CZ followed by 22 digits); its check digits must match.', 422, ['field' => 'iban']);
        }
        ['active' => $active, 'pending' => $pending] = $this->of($partner);
        if ($active !== null && $active['iban'] === $iban) {
            throw new DomainError('payout_account_same', 'Payouts already go to this account.', 409, ['field' => 'iban']);
        }
        if ($pending !== null && $pending->iban === $iban) {
            throw new DomainError('payout_account_pending', 'This account is already waiting for its cooling-off to end.', 409, ['usable_from' => $pending->usable_from->toIso8601String()]);
        }
        $days = self::coolingDays();

        return DB::transaction(function () use ($partner, $organization, $iban, $active, $days, $context) {
            Partner::query()->whereKey($partner->id)->lockForUpdate()->first(); // one change at a time per partner
            $replaced = PartnerPayoutAccount::query()->where('partner_id', $partner->id)->whereNull('cancelled_at')->where('usable_from', '>', now())
                ->update(['cancelled_at' => now(), 'cancelled_by' => $context->actorId]);
            $account = PartnerPayoutAccount::query()->create(['partner_id' => $partner->id, 'iban' => $iban, 'source' => 'owner', 'usable_from' => now()->addDays($days), 'requested_by' => $context->actorId]);
            $detail = ['account' => self::mask($iban), 'previous' => self::mask($active['iban'] ?? null), 'usable_from' => $account->usable_from->toIso8601String(), 'replaced_pending' => $replaced];
            $this->audit->record($context->withScope($organization->id), 'partner.payout_account.change', 'succeeded', $detail, 'partner', $partner->id);
            $this->outbox->publish(GenericEvent::of('partner.payout_account.changed', 'partner', $partner->id, $detail + ['days' => $days, 'partner_code' => $partner->code], $organization->id));

            return $account;
        });
    }

    /** Calls off the change still cooling off (the notice says how): payouts keep going to the account they went to. */
    public function cancelPending(Partner $partner, CommandContext $context): void
    {
        $organization = $this->assertOwner($partner, $context);
        $pending = $this->of($partner)['pending'];
        if ($pending === null) {
            throw new DomainError('payout_account_nothing_pending', 'No account change is waiting.', 409);
        }
        DB::transaction(function () use ($partner, $organization, $pending, $context): void {
            $cancelled = PartnerPayoutAccount::query()->whereKey($pending->id)->whereNull('cancelled_at')->update(['cancelled_at' => now(), 'cancelled_by' => $context->actorId]);
            if ($cancelled !== 1) {
                throw new DomainError('payout_account_nothing_pending', 'No account change is waiting.', 409);
            }
            $detail = ['account' => self::mask($pending->iban), 'usable_from' => $pending->usable_from->toIso8601String()];
            $this->audit->record($context->withScope($organization->id), 'partner.payout_account.cancel', 'succeeded', $detail, 'partner', $partner->id);
            $this->outbox->publish(GenericEvent::of('partner.payout_account.cancelled', 'partner', $partner->id, $detail + ['partner_code' => $partner->code], $organization->id));
        });
    }

    /**
     * The permission is owner-only (PermissionCatalog::PARTNER_OWNER_ONLY); this is the second lock, in the domain: a staff
     * account acting on the organization, somebody acting on behalf of another, or a system job never sets the account.
     */
    private function assertOwner(Partner $partner, CommandContext $context): Organization
    {
        $organization = Organization::query()->findOrFail($partner->organization_id);
        if ($context->actorType !== 'user' || $context->onBehalfOfUserId !== null || $context->actorId === null || $context->actorId !== $organization->owner_user_id) {
            throw new DomainError('payout_account_owner_only', 'Only the owner of the partner organization sets where commissions are paid.', 403);
        }

        return $organization;
    }
}
