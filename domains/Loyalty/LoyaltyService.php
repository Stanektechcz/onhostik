<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Loyalty\Models\LoyaltyRedemption;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/**
 * Loyalty programme (gamification of using the platform well): points for what customers do — paid orders, payments,
 * two-factor sign-in, backups and monitoring switched on, the first service, anniversaries, referrals — add up to
 * levels; reaching a level earns a badge and a promo-credit reward booked through the wallet (idempotent per level).
 * Points are never money; staff override the level table in system settings and may award points by hand (audited). Nothing
 * here can reduce a customer's credit. Since G3 (owner decision G-R2) points can be redeemed for a discount on an order
 * (LoyaltyRedemptions) and expire 24 months after they were credited (LoyaltyExpiry). The level follows the points earned
 * (`standing()`), not what is left to spend: redeeming or letting points expire never takes a level away.
 */
final class LoyaltyService
{
    public const LEVELS_SETTING = 'loyalty.levels';

    public const BADGES = [
        'level:silver' => ['cs' => 'Stříbrná úroveň', 'en' => 'Silver level'], 'level:gold' => ['cs' => 'Zlatá úroveň', 'en' => 'Gold level'], 'level:platinum' => ['cs' => 'Platinová úroveň', 'en' => 'Platinum level'],
        'first-service' => ['cs' => 'První služba', 'en' => 'First service'], 'guardian' => ['cs' => 'Strážce účtu (2FA)', 'en' => 'Account guardian (2FA)'], 'archivist' => ['cs' => 'Archivář (zálohy)', 'en' => 'Archivist (backups)'],
        'watchman' => ['cs' => 'Hlídač (monitoring)', 'en' => 'Watchman (monitoring)'], 'anniversary' => ['cs' => 'Rok s námi', 'en' => 'A year with us'], 'ambassador' => ['cs' => 'Ambasador (doporučení)', 'en' => 'Ambassador (referral)'],
    ];

    public function __construct(private readonly SettingsStore $settings, private readonly WalletService $wallets, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /** @return list<array{key:string,name:string,min:int,reward_minor:int}> ascending by `min` */
    public function levels(): array
    {
        $levels = (array) ($this->settings->get(self::LEVELS_SETTING) ?? config('onhost.loyalty.levels', []));
        $out = [];
        foreach ($levels as $level) {
            if (! is_array($level) || empty($level['key'])) {
                continue;
            }
            $out[] = ['key' => (string) $level['key'], 'name' => (string) ($level['name'] ?? ucfirst((string) $level['key'])), 'min' => max(0, (int) ($level['min'] ?? 0)), 'reward_minor' => max(0, (int) ($level['reward_minor'] ?? 0))];
        }
        usort($out, fn ($a, $b) => $a['min'] <=> $b['min']);

        return $out;
    }

    /** @param  list<array<string,mixed>>  $levels */
    public function setLevels(array $levels, ?string $by = null): array
    {
        if (count($levels) < 1 || count($levels) > 10) {
            throw new DomainError('loyalty_levels_invalid', 'Between one and ten levels are needed.', 422, ['field' => 'levels']);
        }
        $clean = [];
        foreach ($levels as $level) {
            $key = strtolower(trim((string) ($level['key'] ?? '')));
            if (! preg_match('/^[a-z0-9-]{2,20}$/', $key)) {
                throw new DomainError('loyalty_levels_invalid', 'Every level needs a short key (letters, digits, dashes).', 422, ['field' => 'levels']);
            }
            $clean[] = ['key' => $key, 'name' => mb_substr(trim((string) ($level['name'] ?? ucfirst($key))), 0, 40), 'min' => max(0, (int) ($level['min'] ?? 0)), 'reward_minor' => max(0, (int) ($level['reward_minor'] ?? 0))];
        }
        usort($clean, fn ($a, $b) => $a['min'] <=> $b['min']);
        if ($clean[0]['min'] !== 0) {
            throw new DomainError('loyalty_levels_invalid', 'The first level must start at 0 points.', 422, ['field' => 'levels']);
        }
        $this->settings->set(self::LEVELS_SETTING, $clean, $by);

        return $this->levels();
    }

    public function points(string $organizationId): int
    {
        return (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->sum('points');
    }

    /** Rules that move the spendable balance without being earned or taken back (G3): they never change the level. */
    public const SPENDING_RULES = ['redeem', 'redeem.return', 'expiry', 'debt.carry', 'clawback.debt']; // the clawback row took the whole amount off the level already

    /** The points the level is measured by: everything earned, less what was taken back — not what was spent or expired. */
    public function standing(string $organizationId): int
    {
        return (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->whereNotIn('rule', self::SPENDING_RULES)->sum('points');
    }

    /** @return array{key:string,name:string,min:int,reward_minor:int} */
    public function levelFor(int $points): array
    {
        $current = null;
        foreach ($this->levels() as $level) {
            if ($points >= $level['min']) {
                $current = $level;
            }
        }

        return $current ?? ['key' => 'none', 'name' => '—', 'min' => 0, 'reward_minor' => 0];
    }

    /**
     * Awards points once per rule + reference; a level crossed on the way earns its badge and its promo credit.
     *
     * @return array{awarded:bool, points:int, total:int, level:array<string,mixed>, level_up:?string}
     */
    public function award(string $organizationId, string $rule, string $reference, int $points, ?string $note, CommandContext $context): array
    {
        // under a lock on the organization row: an order placed at the same moment cannot reserve the points that pay a debt
        [$awarded, $before] = DB::transaction(function () use ($organizationId, $rule, $reference, $points, $note, $context) {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->first();
            $before = $this->standing($organizationId);
            try {
                DB::transaction(fn () => LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => $rule, 'reference' => mb_substr($reference, 0, 120), 'points' => $points, 'note' => $note !== null ? mb_substr($note, 0, 200) : null])); // savepoint: an already-counted rule never aborts the paid-order transaction (PostgreSQL)
            } catch (QueryException $e) { // unique (organization, rule, reference): already counted
                return [false, $before];
            }
            if ($points > 0) {
                $this->settleDebt($organizationId, 'award:'.$rule.':'.$reference, $context); // a debt of points (G3 review) is paid from the next points first
            }

            return [true, $before];
        });
        $levelBefore = $this->levelFor($before);
        if (! $awarded) {
            return ['awarded' => false, 'points' => 0, 'total' => $this->points($organizationId), 'level' => $levelBefore, 'level_up' => null];
        }
        $level = $this->levelFor($before + $points);
        $total = $this->points($organizationId); // the balance after the award (the level is measured by the standing)
        $levelUp = null;
        if ($points > 0 && $level['key'] !== $levelBefore['key'] && $level['min'] > $levelBefore['min']) {
            $levelUp = $level['key'];
            $this->badge($organizationId, 'level:'.$level['key'], $context);
            if ($level['reward_minor'] > 0) {
                $currency = (string) (Organization::query()->whereKey($organizationId)->value('currency') ?: config('onhost.loyalty.reward_currency', 'CZK'));
                $this->wallets->topup($organizationId, Money::minor($level['reward_minor'], $currency), 'promo', "loyalty:level:{$level['key']}:{$organizationId}", $context->withScope($organizationId), null, "Odměna věrnostního programu · úroveň {$level['name']}", true, null, 'loyalty');
            }
            $this->outbox->publish(GenericEvent::of('loyalty.level_up', 'organization', $organizationId, ['level' => $level['key'], 'name' => $level['name'], 'points' => $total, 'reward' => $level['reward_minor'] > 0 ? Money::minor($level['reward_minor'], (string) (Organization::query()->whereKey($organizationId)->value('currency') ?: 'CZK')) : null], $organizationId));
        }
        $this->audit->record($context->withScope($organizationId), 'loyalty.award', 'succeeded', ['rule' => $rule, 'reference' => $reference, 'points' => $points, 'total' => $total, 'level_up' => $levelUp], 'organization', $organizationId);

        return ['awarded' => true, 'points' => $points, 'total' => $total, 'level' => $level, 'level_up' => $levelUp];
    }

    /** The smallest payment that earns points (owner decision R6), in minor units of its currency; a currency not listed uses `default`. */
    public function minimumPaymentMinor(string $currency): int
    {
        $table = (array) config('onhost.loyalty.min_payment_minor', []);

        return max(1, (int) ($table[strtoupper($currency)] ?? $table['default'] ?? 10000));
    }

    public function qualifies(int $minor, string $currency): bool
    {
        return $minor >= $this->minimumPaymentMinor($currency);
    }

    /**
     * Takes points back (R6: a credit note or chargeback refund gave the money back). A row of its own for the whole amount, so
     * the same reference is taken once and LoyaltyClawback counts what was already taken right. The level reward already booked
     * stays.
     *
     * The balance never goes below zero (G3 review): what the free points (the balance less what unpaid orders reserved) cannot
     * cover is a debt — a `debt.carry` row keeps the balance at what it may be, and the next points the organization gets (earned,
     * freed by a cancelled order, given back by a credit note) pay the debt first (`clawback.debt` rows, settleDebt()).
     *
     * @return array{taken:int, total:int}
     */
    public function clawback(string $organizationId, string $rule, string $reference, int $points, string $note, CommandContext $context): array
    {
        $points = max(0, $points);
        Organization::query()->whereKey($organizationId)->lockForUpdate()->first(); // inside the caller's transaction where there is one (LoyaltyClawback holds it)
        $before = $this->points($organizationId);
        if ($points === 0) {
            return ['taken' => 0, 'total' => $before];
        }
        $free = max(0, $before - $this->reserved($organizationId));
        $short = max(0, $points - $free);
        try {
            DB::transaction(function () use ($organizationId, $rule, $reference, $points, $note, $short) {
                LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => $rule, 'reference' => mb_substr($reference, 0, 120), 'points' => -$points, 'note' => mb_substr($note, 0, 200)]);
                if ($short > 0) {
                    LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => self::CARRY_RULE, 'reference' => mb_substr($rule.':'.$reference, 0, 120), 'points' => $short, 'note' => mb_substr('Dluh '.$short.' bodů: '.$note, 0, 200)]);
                }
            }); // savepoint: an already-taken reference never aborts the caller's transaction (PostgreSQL)
        } catch (QueryException) { // unique (organization, rule, reference): already taken back
            return ['taken' => 0, 'total' => $before];
        }
        $taken = $points - $short;
        $total = $before - $taken;
        $this->audit->record($context->withScope($organizationId), 'loyalty.clawback', 'succeeded', ['rule' => $rule, 'reference' => $reference, 'points' => -$points, 'taken' => $taken, 'debt' => $short, 'total' => $total], 'organization', $organizationId);
        $this->outbox->publish(GenericEvent::of('loyalty.clawback', 'organization', $organizationId, ['points' => $points, 'total' => $total, 'reason' => $note], $organizationId));

        return ['taken' => $taken, 'total' => $total];
    }

    /** A clawback the free points could not cover (positive: it keeps the balance at zero) … */
    public const CARRY_RULE = 'debt.carry';

    /** … and its repayment from the next points (negative). */
    public const DEBT_RULE = 'clawback.debt';

    /** Points owed: clawbacks the free points could not cover, less what later points paid. */
    public function debt(string $organizationId): int
    {
        return max(0, (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->whereIn('rule', [self::CARRY_RULE, self::DEBT_RULE])->sum('points'));
    }

    /** Points held by orders not paid yet (G3); they are neither taken back nor used to pay a debt. */
    public function reserved(string $organizationId): int
    {
        return (int) LoyaltyRedemption::query()->where('organization_id', $organizationId)->where('state', LoyaltyRedemption::RESERVED)->sum('points');
    }

    /**
     * Pays the debt from the free points, once per `$reference` (the award, release or return that brought the points).
     *
     * @return int points paid
     */
    public function settleDebt(string $organizationId, string $reference, CommandContext $context): int
    {
        return DB::transaction(function () use ($organizationId, $reference, $context) {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->first(); // the same lock a reservation takes
            $debt = $this->debt($organizationId);
            $pay = min($debt, max(0, $this->points($organizationId) - $this->reserved($organizationId)));
            if ($pay <= 0) {
                return 0;
            }
            try {
                DB::transaction(fn () => LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => self::DEBT_RULE, 'reference' => mb_substr($reference, 0, 120), 'points' => -$pay, 'note' => 'Splátka dluhu bodů']));
            } catch (QueryException) {
                return 0; // paid from these points already
            }
            $this->audit->record($context->withScope($organizationId), 'loyalty.debt.settle', 'succeeded', ['reference' => $reference, 'points' => -$pay, 'debt' => $debt - $pay], 'organization', $organizationId);

            return $pay;
        });
    }

    /** Grants a badge once; a repeated grant is a no-op. */
    public function badge(string $organizationId, string $badge, CommandContext $context): bool
    {
        try {
            DB::transaction(fn () => LoyaltyBadge::query()->create(['organization_id' => $organizationId, 'badge' => $badge, 'earned_at' => now()])); // savepoint (PostgreSQL)
        } catch (QueryException) {
            return false;
        }
        $this->outbox->publish(GenericEvent::of('loyalty.badge', 'organization', $organizationId, ['badge' => $badge, 'name' => self::BADGES[$badge]['cs'] ?? $badge], $organizationId));

        return true;
    }

    /** @return array<string,mixed> what the panel shows */
    public function summary(string $organizationId, string $locale = 'cs'): array
    {
        $total = $this->points($organizationId);
        $standing = $this->standing($organizationId);
        $level = $this->levelFor($standing);
        $next = null;
        foreach ($this->levels() as $candidate) {
            if ($candidate['min'] > $standing) {
                $next = $candidate + ['missing' => $candidate['min'] - $standing];
                break;
            }
        }
        $badges = LoyaltyBadge::query()->where('organization_id', $organizationId)->orderBy('earned_at')->get()->map(fn (LoyaltyBadge $b) => ['badge' => $b->badge, 'name' => self::BADGES[$b->badge][$locale] ?? self::BADGES[$b->badge]['cs'] ?? $b->badge, 'earned_at' => $b->earned_at?->toIso8601String()])->all();
        $history = LoyaltyPoint::query()->where('organization_id', $organizationId)->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get()->map(fn (LoyaltyPoint $p) => ['rule' => $p->rule, 'points' => $p->points, 'note' => $p->note, 'at' => $p->created_at?->toIso8601String()])->all();

        return ['points' => $total, 'standing' => $standing, 'debt' => $this->debt($organizationId), 'level' => $level, 'next' => $next, 'levels' => $this->levels(), 'badges' => $badges, 'history' => $history, 'rules' => (array) config('onhost.loyalty.points', []),
            'redeem' => app(LoyaltyRedemptions::class)->summary($organizationId), 'expiring' => app(LoyaltyExpiry::class)->upcoming($organizationId)];
    }
}
