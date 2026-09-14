<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
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
 * Points are never money and never expire; staff override the level table in system settings and may award points
 * by hand (audited). Nothing here can reduce a customer's credit.
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
        $before = $this->points($organizationId);
        $levelBefore = $this->levelFor($before);
        try {
            DB::transaction(fn () => LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => $rule, 'reference' => mb_substr($reference, 0, 120), 'points' => $points, 'note' => $note !== null ? mb_substr($note, 0, 200) : null])); // savepoint: an already-counted rule never aborts the paid-order transaction (PostgreSQL)
        } catch (QueryException $e) { // unique (organization, rule, reference): already counted
            return ['awarded' => false, 'points' => 0, 'total' => $before, 'level' => $levelBefore, 'level_up' => null];
        }
        $total = $before + $points;
        $level = $this->levelFor($total);
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
        $level = $this->levelFor($total);
        $next = null;
        foreach ($this->levels() as $candidate) {
            if ($candidate['min'] > $total) {
                $next = $candidate + ['missing' => $candidate['min'] - $total];
                break;
            }
        }
        $badges = LoyaltyBadge::query()->where('organization_id', $organizationId)->orderBy('earned_at')->get()->map(fn (LoyaltyBadge $b) => ['badge' => $b->badge, 'name' => self::BADGES[$b->badge][$locale] ?? self::BADGES[$b->badge]['cs'] ?? $b->badge, 'earned_at' => $b->earned_at?->toIso8601String()])->all();
        $history = LoyaltyPoint::query()->where('organization_id', $organizationId)->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get()->map(fn (LoyaltyPoint $p) => ['rule' => $p->rule, 'points' => $p->points, 'note' => $p->note, 'at' => $p->created_at?->toIso8601String()])->all();

        return ['points' => $total, 'level' => $level, 'next' => $next, 'levels' => $this->levels(), 'badges' => $badges, 'history' => $history, 'rules' => (array) config('onhost.loyalty.points', [])];
    }
}
