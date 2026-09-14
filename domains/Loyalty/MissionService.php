<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Carbon\CarbonImmutable;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/**
 * Missions and streaks (audit §5j-3, catalogue in settings §5k-5). Missions are monthly: things a well-run account does
 * (two-factor on every member, a monitor watching, a restore actually tested, invoices paid before they are due, the
 * billing profile complete …); each completed mission earns points once per month, a month with every mission done
 * earns the badge. Staff edit the catalogue in system settings — titles, points, the check behind each mission and a
 * season (from/to) for limited-time missions; the built-in five are the default. The streak counts consecutive months
 * in which every document was paid on time; at the target the customer may be granted a permanent loyalty discount —
 * finance approves it, the quote applies it, nothing happens by itself.
 */
final class MissionService
{
    public const BADGE_ALL = 'mission:month';

    public const BADGE_STREAK = 'streak:target';

    public const CATALOGUE_SETTING = 'loyalty.missions.catalogue';

    public const CAMPAIGNS_SETTING = 'loyalty.missions.campaigns';

    public const ANNOUNCED_SETTING = 'loyalty.missions.campaigns.announced';

    /** the checks a mission may use (`check` of a catalogue entry); `params` refine them */
    public const CHECKS = ['mfa_all', 'monitor', 'restore_test', 'on_time', 'profile', 'backup_done', 'services_min', 'ticket_free'];

    public function __construct(private readonly LoyaltyService $loyalty, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox, private readonly SettingsStore $settings) {}

    /** @return list<array{key:string, cs:string, en:string, points:int, hint_cs:string, hint_en:string, check:string, params:array<string,mixed>, active_from:?string, active_to:?string, badge:?string}> */
    public function defaults(): array
    {
        $points = (array) config('onhost.loyalty.missions', []);
        $p = fn (string $k, int $d) => max(0, (int) ($points[$k] ?? $d));
        $row = fn (string $key, string $cs, string $en, int $pts, string $hcs, string $hen) => ['key' => $key, 'cs' => $cs, 'en' => $en, 'points' => $pts, 'hint_cs' => $hcs, 'hint_en' => $hen, 'check' => $key, 'params' => [], 'active_from' => null, 'active_to' => null, 'badge' => null];

        return [
            $row('mfa_all', 'Dvoufázové přihlášení u všech členů', 'Two-factor sign-in for every member', $p('mfa_all', 40), 'Každý aktivní člen účtu má zapnutý autentikátor.', 'Every active member has an authenticator on.'),
            $row('monitor', 'Monitoring hlídá alespoň jeden web', 'A monitor watches at least one site', $p('monitor', 20), 'Zapněte dostupnostní monitor v záložce Monitoring.', 'Enable an uptime monitor under Monitoring.'),
            $row('restore_test', 'Otestované obnovení ze zálohy', 'A restore from backup tested', $p('restore_test', 60), 'Obnovte zálohu (klidně do stagingu) alespoň jednou za měsíc.', 'Restore a backup (staging is fine) at least once a month.'),
            $row('on_time', 'Všechny doklady zaplacené včas', 'Every document paid on time', $p('on_time', 30), 'Doklady se splatností v měsíci byly zaplacené před splatností.', 'Documents due in the month were paid before the due date.'),
            $row('profile', 'Úplné fakturační údaje', 'Complete billing profile', $p('profile', 10), 'Adresa, město a PSČ na dokladech.', 'Street, city and postcode on the documents.'),
        ];
    }

    /** The catalogue in force: the staff table when there is one (seasonal rows only inside their season), the defaults otherwise. */
    public function catalogue(?CarbonImmutable $now = null, bool $all = false): array
    {
        $now ??= CarbonImmutable::now();
        $stored = $this->settings->get(self::CATALOGUE_SETTING);
        $rows = is_array($stored) && $stored !== [] ? $stored : $this->defaults();
        $out = [];
        foreach ($rows as $row) {
            $row = $this->normalize((array) $row, false);
            if ($row === null) {
                continue;
            }
            $inSeason = ($row['active_from'] === null || CarbonImmutable::parse($row['active_from']) <= $now) && ($row['active_to'] === null || CarbonImmutable::parse($row['active_to'])->endOfDay() >= $now);
            if ($all || $inSeason) {
                $out[] = $row + ['in_season' => $inSeason];
            }
        }

        return $out;
    }

    /** @param  list<array<string,mixed>>  $rows  Replaces the catalogue; an empty list restores the defaults. */
    public function setCatalogue(array $rows, ?string $by = null): array
    {
        if (count($rows) > 20) {
            throw new DomainError('missions_too_many', 'At most twenty missions.', 422, ['field' => 'missions']);
        }
        $clean = [];
        foreach ($rows as $row) {
            $normalized = $this->normalize((array) $row, true);
            if (isset($clean[$normalized['key']])) {
                throw new DomainError('missions_key_duplicate', "Mission {$normalized['key']} is listed twice.", 422, ['field' => 'missions']);
            }
            $clean[$normalized['key']] = $normalized;
        }
        if ($clean === []) {
            $this->settings->forget(self::CATALOGUE_SETTING);
        } else {
            $this->settings->set(self::CATALOGUE_SETTING, array_values($clean), $by);
        }

        return $this->catalogue(null, true);
    }

    /**
     * @return array{month:string, missions:list<array<string,mixed>>, done:int, total:int, badge:bool, streak:array<string,mixed>}
     */
    public function summary(Organization $organization, ?CarbonImmutable $now = null, string $locale = 'cs'): array
    {
        $now ??= CarbonImmutable::now();
        $month = $now->format('Y-m');
        $awarded = LoyaltyPoint::query()->where('organization_id', $organization->id)->where('rule', 'like', 'mission:%')->where('reference', $month)->pluck('created_at', 'rule');
        $missions = [];
        $done = 0;
        foreach ($this->catalogue($now) as $mission) {
            $state = $this->check($organization, $mission, $now);
            $complete = $state['done'];
            $done += $complete ? 1 : 0;
            $missions[] = ['key' => $mission['key'], 'title' => $mission[$locale === 'en' ? 'en' : 'cs'], 'hint' => $mission[$locale === 'en' ? 'hint_en' : 'hint_cs'], 'points' => $mission['points'], 'done' => $complete, 'progress' => $state['progress'], 'seasonal' => $mission['active_from'] !== null || $mission['active_to'] !== null, 'active_to' => $mission['active_to'], 'awarded_at' => isset($awarded['mission:'.$mission['key']]) ? CarbonImmutable::parse((string) $awarded['mission:'.$mission['key']])->toIso8601String() : null];
        }

        return ['month' => $month, 'missions' => $missions, 'done' => $done, 'total' => count($missions), 'badge' => LoyaltyBadge::query()->where('organization_id', $organization->id)->where('badge', self::BADGE_ALL)->exists(), 'campaigns' => $this->campaignProgress($organization, $now, $locale), 'streak' => $this->streak($organization, $now)];
    }

    /** Awards what is complete this month (idempotent per mission and month); the badge when everything is. @return array{awarded:list<string>, badge:bool, streak_reached:bool} */
    public function evaluate(Organization $organization, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $month = $now->format('Y-m');
        $ctx = CommandContext::system('missions')->withScope($organization->id);
        $awarded = [];
        $all = true;
        $catalogue = $this->catalogue($now);
        foreach ($catalogue as $mission) {
            if (! $this->check($organization, $mission, $now)['done']) {
                $all = false;

                continue;
            }
            if ($this->loyalty->award($organization->id, 'mission:'.$mission['key'], $month, $mission['points'], 'Mise: '.$mission['cs'], $ctx)['awarded']) {
                $awarded[] = $mission['key'];
                if (! empty($mission['badge'])) {
                    $this->loyalty->badge($organization->id, 'mission:'.$mission['badge'], $ctx);
                }
            }
        }
        $badge = $catalogue !== [] && $all && $this->loyalty->badge($organization->id, self::BADGE_ALL, $ctx);
        $completed = $this->completeCampaigns($organization, $now, $ctx); // §5l-5: a campaign whose missions were all done inside its window earns its badge once
        if ($awarded !== []) {
            $this->outbox->publish(GenericEvent::of('loyalty.mission.completed', 'organization', $organization->id, ['month' => $month, 'missions' => $awarded, 'all' => $all], $organization->id));
        }
        $streak = $this->streak($organization, $now);
        $reached = $streak['eligible'] && $this->loyalty->badge($organization->id, self::BADGE_STREAK, $ctx);
        if ($reached) {
            $this->outbox->publish(GenericEvent::of('loyalty.streak.reached', 'organization', $organization->id, ['months' => $streak['months'], 'target' => $streak['target'], 'discount_pct' => $streak['proposed_pct']], $organization->id));
        }

        return ['awarded' => $awarded, 'badge' => $badge, 'streak_reached' => $reached, 'campaigns_completed' => $completed];
    }

    // ── campaigns (§5l-5): a bundle of missions with a shared badge, a window and a start mail ──

    /** @return list<array{key:string, cs:string, en:string, missions:list<string>, badge:string, active_from:string, active_to:?string, mail:bool, in_window:bool}> */
    public function campaigns(?CarbonImmutable $now = null, bool $all = false): array
    {
        $now ??= CarbonImmutable::now();
        $out = [];
        foreach ((array) ($this->settings->get(self::CAMPAIGNS_SETTING) ?? []) as $row) {
            $row = $this->normalizeCampaign((array) $row, false, []);
            if ($row === null) {
                continue;
            }
            $inWindow = CarbonImmutable::parse($row['active_from']) <= $now && ($row['active_to'] === null || CarbonImmutable::parse($row['active_to'])->endOfDay() >= $now);
            if ($all || $inWindow) {
                $out[] = $row + ['in_window' => $inWindow];
            }
        }

        return $out;
    }

    /** @param  list<array<string,mixed>>  $rows  Replaces the campaigns; missions must exist in the catalogue (seasonal rows included). */
    public function setCampaigns(array $rows, ?string $by = null): array
    {
        if (count($rows) > 10) {
            throw new DomainError('campaigns_too_many', 'At most ten campaigns.', 422, ['field' => 'campaigns']);
        }
        $known = array_column($this->catalogue(null, true), 'key');
        $clean = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeCampaign((array) $row, true, $known);
            if (isset($clean[$normalized['key']])) {
                throw new DomainError('campaign_key_duplicate', "Campaign {$normalized['key']} is listed twice.", 422, ['field' => 'campaigns']);
            }
            $clean[$normalized['key']] = $normalized;
        }
        if ($clean === []) {
            $this->settings->forget(self::CAMPAIGNS_SETTING);
        } else {
            $this->settings->set(self::CAMPAIGNS_SETTING, array_values($clean), $by);
        }

        return $this->campaigns(null, true);
    }

    /** Announces campaigns whose window opened (once per campaign): one event per active organization; the mail follows when the campaign asks for it. */
    public function announceCampaigns(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $announced = (array) ($this->settings->get(self::ANNOUNCED_SETTING) ?? []);
        $stats = ['campaigns' => 0, 'organizations' => 0];
        foreach ($this->campaigns($now) as $campaign) {
            if (in_array($campaign['key'], $announced, true)) {
                continue;
            }
            $titles = collect($this->catalogue($now, true))->whereIn('key', $campaign['missions'])->pluck('cs')->all();
            foreach (Organization::query()->where('state', 'active')->whereNull('feature_flags->sandbox')->orderBy('created_at')->limit(20000)->get(['id']) as $organization) {
                $this->outbox->publish(GenericEvent::of('loyalty.campaign.started', 'organization', $organization->id, ['campaign' => $campaign['key'], 'title' => $campaign['cs'], 'title_en' => $campaign['en'], 'missions' => $titles, 'badge' => $campaign['badge'], 'until' => $campaign['active_to'], 'mail' => $campaign['mail']], $organization->id));
                $stats['organizations']++;
            }
            $announced[] = $campaign['key'];
            $stats['campaigns']++;
        }
        $this->settings->set(self::ANNOUNCED_SETTING, array_values(array_unique($announced)), 'campaigns.announce');

        return $stats;
    }

    /** @return list<array<string,mixed>> the customer's progress per campaign in its window */
    public function campaignProgress(Organization $organization, CarbonImmutable $now, string $locale = 'cs'): array
    {
        $out = [];
        foreach ($this->campaigns($now) as $campaign) {
            $done = $this->campaignMissionsDone($organization, $campaign);
            $out[] = ['key' => $campaign['key'], 'title' => $campaign[$locale === 'en' ? 'en' : 'cs'], 'missions' => $campaign['missions'], 'done' => $done, 'total' => count($campaign['missions']), 'badge' => $campaign['badge'], 'until' => $campaign['active_to'], 'earned' => LoyaltyBadge::query()->where('organization_id', $organization->id)->where('badge', 'campaign:'.$campaign['badge'])->exists()];
        }

        return $out;
    }

    /**
     * What a campaign did (§5m-5): organizations that completed it, missions awarded inside the window (organizations and
     * points per mission), points in total, and the promo credit of level-ups reached inside the window.
     *
     * @return array<string,mixed>
     */
    public function campaignAnalytics(string $key): array
    {
        $campaign = collect($this->campaigns(null, true))->firstWhere('key', $key);
        if ($campaign === null) {
            throw DomainError::notFound('campaign');
        }
        $from = CarbonImmutable::parse($campaign['active_from'])->startOfDay();
        $to = $campaign['active_to'] !== null ? CarbonImmutable::parse($campaign['active_to'])->endOfDay() : CarbonImmutable::now();
        $missions = [];
        $points = 0;
        foreach ($campaign['missions'] as $mission) {
            $rows = LoyaltyPoint::query()->where('rule', 'mission:'.$mission)->whereBetween('created_at', [$from, $to])->get(['organization_id', 'points']);
            $missions[] = ['key' => $mission, 'organizations' => $rows->pluck('organization_id')->unique()->count(), 'points' => (int) $rows->sum('points')];
            $points += (int) $rows->sum('points');
        }
        $completed = LoyaltyBadge::query()->where('badge', 'campaign:'.$campaign['badge'])->whereBetween('earned_at', [$from, $to->addDays(1)])->count();
        $levels = collect($this->loyalty->levels())->keyBy('key');
        $creditMinor = 0;
        foreach (LoyaltyBadge::query()->where('badge', 'like', 'level:%')->whereBetween('earned_at', [$from, $to])->get(['badge']) as $badge) {
            $creditMinor += (int) ($levels->get(substr($badge->badge, 6))['reward_minor'] ?? 0);
        }
        $announced = (int) Notification::query()->where('event', 'loyalty.campaign.started')->where('ref_type', 'organization')->where('title', 'Nová kampaň: '.$campaign['cs'])->whereBetween('created_at', [$from->subDays(1), $to])->distinct()->count('organization_id');

        return ['campaign' => $campaign, 'window' => ['from' => $from->toDateString(), 'to' => $campaign['active_to']], 'announced' => $announced, 'completed' => $completed, 'missions' => $missions, 'points' => $points, 'level_ups' => ['credit' => Money::minor($creditMinor, (string) config('onhost.loyalty.reward_currency', 'CZK')), 'count' => LoyaltyBadge::query()->where('badge', 'like', 'level:%')->whereBetween('earned_at', [$from, $to])->count()], 'completion_rate' => $announced > 0 ? round($completed / $announced * 100, 1) : null];
    }

    /**
     * What a campaign may cost before it opens (§5n-5): every active organization could complete every mission (the
     * ceiling), the share that completed each mission in the last 90 days says what to expect; points push organizations
     * over level thresholds, and those level-ups carry promo credit from the level table.
     *
     * @param  array<string,mixed>  $draft  a campaign row as PUT /loyalty/campaigns takes it (key optional)
     * @return array<string,mixed>
     */
    public function campaignForecast(array $draft): array
    {
        $draft += ['key' => 'draft', 'active_from' => CarbonImmutable::now()->toDateString()];
        $campaign = $this->normalizeCampaign($draft, true, array_column($this->catalogue(null, true), 'key'));
        if ($campaign === null) {
            throw new DomainError('campaign_invalid', 'A campaign needs at least one mission.', 422, ['field' => 'missions']);
        }
        $catalogue = collect($this->catalogue(null, true))->keyBy('key');
        $organizations = Organization::query()->where('state', 'active')->whereNull('feature_flags->sandbox')->get(['id']);
        $count = $organizations->count();
        $since = CarbonImmutable::now()->subDays(90);
        $floor = max(0.0, min(100.0, (float) config('onhost.loyalty.forecast.default_completion_pct', 25)));
        $missions = [];
        $pointsMax = 0;
        $pointsExpected = 0.0;
        foreach ($campaign['missions'] as $key) {
            $points = (int) ($catalogue->get($key)['points'] ?? 0);
            $done = $count > 0 ? LoyaltyPoint::query()->where('rule', 'mission:'.$key)->where('created_at', '>=', $since)->distinct()->count('organization_id') : 0;
            $rate = $count > 0 && $done > 0 ? min(100.0, round($done / $count * 100, 1)) : $floor;
            $missions[] = ['key' => $key, 'points' => $points, 'historical_completion_pct' => $rate, 'points_max' => $points * $count, 'points_expected' => (int) round($points * $count * $rate / 100)];
            $pointsMax += $points * $count;
            $pointsExpected += $points * $count * $rate / 100;
        }
        $campaignPoints = (int) array_sum(array_column($missions, 'points'));
        $levels = $this->loyalty->levels();
        $creditMax = 0;
        $levelUps = 0;
        $totals = LoyaltyPoint::query()->whereIn('organization_id', $organizations->pluck('id'))->groupBy('organization_id')->selectRaw('organization_id, sum(points) as total')->pluck('total', 'organization_id');
        foreach ($organizations as $organization) {
            $now = (int) ($totals[$organization->id] ?? 0);
            foreach ($levels as $level) {
                if ($level['min'] > $now && $level['min'] <= $now + $campaignPoints) {
                    $creditMax += (int) $level['reward_minor'];
                    $levelUps++;
                }
            }
        }
        $expectedRate = $pointsMax > 0 ? $pointsExpected / $pointsMax : 0.0;
        $currency = (string) config('onhost.loyalty.reward_currency', 'CZK');

        return [
            'campaign' => $campaign, 'organizations' => $count, 'missions' => $missions, 'points_max' => $pointsMax, 'points_expected' => (int) round($pointsExpected),
            'level_ups_max' => $levelUps, 'credit_max' => Money::minor($creditMax, $currency), 'credit_expected' => Money::minor((int) round($creditMax * $expectedRate), $currency), 'expected_completion_pct' => round($expectedRate * 100, 1),
        ];
    }

    /** @return list<string> campaign keys completed now */
    private function completeCampaigns(Organization $organization, CarbonImmutable $now, CommandContext $ctx): array
    {
        $completed = [];
        foreach ($this->campaigns($now) as $campaign) {
            if ($this->campaignMissionsDone($organization, $campaign) !== count($campaign['missions'])) {
                continue;
            }
            if ($this->loyalty->badge($organization->id, 'campaign:'.$campaign['badge'], $ctx)) {
                $completed[] = $campaign['key'];
                $this->outbox->publish(GenericEvent::of('loyalty.campaign.completed', 'organization', $organization->id, ['campaign' => $campaign['key'], 'title' => $campaign['cs'], 'badge' => $campaign['badge']], $organization->id));
            }
        }

        return $completed;
    }

    /** @param  array<string,mixed>  $campaign */
    private function campaignMissionsDone(Organization $organization, array $campaign): int
    {
        $from = CarbonImmutable::parse($campaign['active_from'])->startOfDay();
        $to = $campaign['active_to'] !== null ? CarbonImmutable::parse($campaign['active_to'])->endOfDay() : $from->addYears(10);
        $rules = array_map(fn ($k) => 'mission:'.$k, $campaign['missions']);

        return (int) LoyaltyPoint::query()->where('organization_id', $organization->id)->whereIn('rule', $rules)->whereBetween('created_at', [$from, $to])->distinct()->count('rule');
    }

    /** @param  array<string,mixed>  $row @param  list<string>  $known @return array<string,mixed>|null */
    private function normalizeCampaign(array $row, bool $strict, array $known): ?array
    {
        $key = strtolower(trim((string) ($row['key'] ?? '')));
        $missions = array_values(array_unique(array_map(fn ($m) => strtolower(trim((string) $m)), (array) ($row['missions'] ?? []))));
        $from = trim((string) ($row['active_from'] ?? ''));
        if (! preg_match('/^[a-z0-9_-]{2,30}$/', $key) || $missions === [] || $from === '') {
            if ($strict) {
                throw new DomainError('campaign_invalid', "Campaign {$key}: a key (2–30 lowercase characters), at least one mission and a start date are needed.", 422, ['field' => 'campaigns']);
            }

            return null;
        }
        if ($strict && ($unknown = array_diff($missions, $known)) !== []) {
            throw new DomainError('campaign_mission_unknown', "Campaign {$key}: unknown missions ".implode(', ', $unknown).'.', 422, ['field' => 'campaigns']);
        }
        try {
            $fromDate = CarbonImmutable::parse($from)->toDateString();
            $toDate = isset($row['active_to']) && $row['active_to'] !== '' && $row['active_to'] !== null ? CarbonImmutable::parse((string) $row['active_to'])->toDateString() : null;
        } catch (\Throwable) {
            if ($strict) {
                throw new DomainError('campaign_invalid', "Campaign {$key}: the window dates are not valid dates.", 422, ['field' => 'campaigns']);
            }

            return null;
        }
        if ($strict && $toDate !== null && $fromDate > $toDate) {
            throw new DomainError('campaign_invalid', "Campaign {$key}: the window ends before it starts.", 422, ['field' => 'campaigns']);
        }
        $badge = strtolower(trim((string) ($row['badge'] ?? $key)));

        return ['key' => $key, 'cs' => mb_substr(trim((string) ($row['cs'] ?? $key)), 0, 80), 'en' => mb_substr(trim((string) ($row['en'] ?? $row['cs'] ?? $key)), 0, 80), 'missions' => $missions, 'badge' => preg_match('/^[a-z0-9_-]{2,30}$/', $badge) ? $badge : $key, 'active_from' => $fromDate, 'active_to' => $toDate, 'mail' => (bool) ($row['mail'] ?? true)];
    }

    /** Runs the evaluation for every active organization (scheduled daily; cheap — a few counts per organization). */
    public function evaluateAll(?CarbonImmutable $now = null, int $limit = 5000): array
    {
        $stats = ['organizations' => 0, 'awards' => 0, 'badges' => 0, 'streaks' => 0];
        foreach (Organization::query()->where('state', 'active')->orderBy('created_at')->limit($limit)->get() as $organization) {
            $result = $this->evaluate($organization, $now);
            $stats['organizations']++;
            $stats['awards'] += count($result['awarded']);
            $stats['badges'] += $result['badge'] ? 1 : 0;
            $stats['streaks'] += $result['streak_reached'] ? 1 : 0;
        }

        return $stats;
    }

    /**
     * Consecutive months (ending with the last full month) in which every document due was paid on or before its due date.
     *
     * @return array{months:int, target:int, eligible:bool, proposed_pct:float, discount:?array<string,mixed>}
     */
    public function streak(Organization $organization, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $target = max(1, (int) config('onhost.loyalty.streak.months', 12));
        $months = 0;
        $cursor = $now->startOfMonth()->subMonth();
        for ($i = 0; $i < 60; $i++) {
            $state = $this->monthPaidOnTime($organization->id, $cursor);
            if ($state !== true) {
                break;
            }
            $months++;
            $cursor = $cursor->subMonth();
        }
        $discount = data_get($organization->settings, 'loyalty_discount');

        return ['months' => $months, 'target' => $target, 'eligible' => $months >= $target, 'proposed_pct' => (float) config('onhost.loyalty.streak.discount_pct', 5), 'discount' => is_array($discount) ? $discount : null];
    }

    /** Finance grants the permanent discount (0 removes it); it applies to every future quote of the organization. */
    public function approveStreakDiscount(Organization $organization, float $percent, CommandContext $context, ?string $note = null): Organization
    {
        if ($percent < 0 || $percent > 30) {
            throw new DomainError('loyalty_discount_invalid', 'A loyalty discount is between 0 and 30 %.', 422, ['field' => 'percent']);
        }
        $streak = $this->streak($organization);
        if ($percent > 0 && ! $streak['eligible'] && ! LoyaltyBadge::query()->where('organization_id', $organization->id)->where('badge', self::BADGE_STREAK)->exists()) {
            throw new DomainError('loyalty_streak_not_reached', "The organization has {$streak['months']} on-time months; {$streak['target']} are needed.", 409, ['months' => $streak['months'], 'target' => $streak['target']]);
        }
        $settings = (array) ($organization->settings ?? []);
        if ($percent > 0) {
            $settings['loyalty_discount'] = ['pct' => round($percent, 2), 'approved_by' => $context->actorId, 'approved_at' => now()->toIso8601String(), 'streak_months' => $streak['months'], 'note' => $note];
        } else {
            unset($settings['loyalty_discount']);
        }
        $organization->forceFill(['settings' => $settings])->save();
        $this->audit->record($context->withScope($organization->id), 'loyalty.discount', 'succeeded', ['percent' => $percent, 'streak' => $streak['months'], 'note' => $note], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('loyalty.discount.'.($percent > 0 ? 'granted' : 'removed'), 'organization', $organization->id, ['percent' => $percent, 'months' => $streak['months']], $organization->id));

        return $organization->refresh();
    }

    /** @param  array<string,mixed>  $mission @return array{done:bool, progress:string} */
    private function check(Organization $organization, array $mission, CarbonImmutable $now): array
    {
        $params = (array) ($mission['params'] ?? []);

        return match ($mission['check']) {
            'mfa_all' => (function () use ($organization) {
                $userIds = OrganizationMembership::query()->where('organization_id', $organization->id)->where('state', 'active')->pluck('user_id');
                $users = User::query()->whereIn('id', $userIds)->get();
                $on = $users->filter(fn (User $u) => $u->totp_secret !== null && $u->totp_confirmed_at !== null)->count();

                return ['done' => $users->isNotEmpty() && $on === $users->count(), 'progress' => "{$on}/{$users->count()}"];
            })(),
            'monitor' => (function () use ($organization) {
                $n = UptimeMonitor::query()->where('organization_id', $organization->id)->where('enabled', true)->count();

                return ['done' => $n > 0, 'progress' => (string) $n];
            })(),
            'restore_test' => (function () use ($organization, $now) {
                $n = Operation::query()->where('organization_id', $organization->id)->where('state', Operation::SUCCEEDED)->where('kind', 'like', '%restore%')->where('finished_at', '>=', $now->startOfMonth())->count()
                    + Operation::query()->where('organization_id', $organization->id)->where('state', Operation::SUCCEEDED)->where('desired->action', 'restore')->where('finished_at', '>=', $now->startOfMonth())->count();

                return ['done' => $n > 0, 'progress' => (string) $n];
            })(),
            'backup_done' => (function () use ($organization, $now) { // a completed backup this month (§5k-5)
                $n = Operation::query()->where('organization_id', $organization->id)->where('state', Operation::SUCCEEDED)->where(fn ($q) => $q->where('kind', 'like', '%backup%')->orWhere('desired->action', 'backup'))->where('finished_at', '>=', $now->startOfMonth())->count();

                return ['done' => $n > 0, 'progress' => (string) $n];
            })(),
            'services_min' => (function () use ($organization, $params) { // at least N running services (§5k-5)
                $min = max(1, (int) ($params['min'] ?? 2));
                $n = Service::query()->where('organization_id', $organization->id)->whereIn('state', ['ACTIVE', 'DEGRADED'])->count();

                return ['done' => $n >= $min, 'progress' => "{$n}/{$min}"];
            })(),
            'ticket_free' => (function () use ($organization, $now) { // no support ticket opened this month (§5k-5)
                $n = (int) Ticket::query()->where('organization_id', $organization->id)->where('created_at', '>=', $now->startOfMonth())->count();

                return ['done' => $n === 0, 'progress' => (string) $n];
            })(),
            'on_time' => (function () use ($organization, $now) {
                $state = $this->monthPaidOnTime($organization->id, $now->startOfMonth(), true);

                return ['done' => $state === true, 'progress' => $state === null ? '0' : ($state ? 'ok' : 'late')];
            })(),
            'profile' => ['done' => $organization->street && $organization->city && $organization->postal_code, 'progress' => implode('', [$organization->street ? '✓' : '·', $organization->city ? '✓' : '·', $organization->postal_code ? '✓' : '·'])],
            default => ['done' => false, 'progress' => ''],
        };
    }

    /** @param  array<string,mixed>  $row @return array<string,mixed>|null */
    private function normalize(array $row, bool $strict): ?array
    {
        $key = strtolower(trim((string) ($row['key'] ?? '')));
        $check = strtolower(trim((string) ($row['check'] ?? $key)));
        if (! preg_match('/^[a-z0-9_-]{2,30}$/', $key) || ! in_array($check, self::CHECKS, true)) {
            if ($strict) {
                throw new DomainError('mission_invalid', "Mission {$key}: the key is 2–30 lowercase characters and the check is one of ".implode(', ', self::CHECKS).'.', 422, ['field' => 'missions']);
            }

            return null;
        }
        $points = (int) ($row['points'] ?? 0);
        if ($strict && ($points < 0 || $points > 1000)) {
            throw new DomainError('mission_invalid', "Mission {$key}: points are 0–1000.", 422, ['field' => 'missions']);
        }
        $date = function (mixed $v) use ($strict, $key): ?string {
            if ($v === null || $v === '') {
                return null;
            }
            try {
                return CarbonImmutable::parse((string) $v)->toDateString();
            } catch (\Throwable) {
                if ($strict) {
                    throw new DomainError('mission_invalid', "Mission {$key}: the season dates are not valid dates.", 422, ['field' => 'missions']);
                }

                return null;
            }
        };
        $from = $date($row['active_from'] ?? null);
        $to = $date($row['active_to'] ?? null);
        if ($strict && $from !== null && $to !== null && $from > $to) {
            throw new DomainError('mission_invalid', "Mission {$key}: the season ends before it starts.", 422, ['field' => 'missions']);
        }
        $badge = strtolower(trim((string) ($row['badge'] ?? '')));

        return [
            'key' => $key, 'cs' => mb_substr(trim((string) ($row['cs'] ?? $key)), 0, 80), 'en' => mb_substr(trim((string) ($row['en'] ?? $row['cs'] ?? $key)), 0, 80), 'points' => max(0, min(1000, $points)),
            'hint_cs' => mb_substr(trim((string) ($row['hint_cs'] ?? '')), 0, 160), 'hint_en' => mb_substr(trim((string) ($row['hint_en'] ?? $row['hint_cs'] ?? '')), 0, 160),
            'check' => $check, 'params' => array_intersect_key((array) ($row['params'] ?? []), array_flip(['min'])), 'active_from' => $from, 'active_to' => $to, 'badge' => $badge !== '' && preg_match('/^[a-z0-9_-]{2,30}$/', $badge) ? $badge : null,
        ];
    }

    /** true = every document due in the month was paid by its due date; false = one was late or is still open past due; null = nothing was due. */
    private function monthPaidOnTime(string $organizationId, CarbonImmutable $monthStart, bool $partial = false): ?bool
    {
        $end = $monthStart->endOfMonth();
        $due = Invoice::query()->where('organization_id', $organizationId)->whereIn('type', ['invoice', 'proforma'])->whereNotIn('state', [Invoice::CANCELLED, Invoice::CREDITED])->whereBetween('due_at', [$monthStart, $end])->get(['state', 'due_at', 'paid_at']);
        if ($due->isEmpty()) {
            return $partial ? null : ($this->hadServices($organizationId, $monthStart) ? true : null);
        }
        foreach ($due as $invoice) {
            if ($invoice->paid_at !== null && $invoice->paid_at <= $invoice->due_at) {
                continue;
            }
            if ($invoice->paid_at === null && $partial && $invoice->due_at > now()) {
                continue; // not due yet this month
            }

            return false;
        }

        return true;
    }

    /** A month without documents still counts when the organization had running services (prepaid credit renewals raise no document to pay). */
    private function hadServices(string $organizationId, CarbonImmutable $monthStart): bool
    {
        return Service::query()->withTrashed()->where('organization_id', $organizationId)->where('created_at', '<=', $monthStart->endOfMonth())->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('deleted_at', '>=', $monthStart))->exists();
    }
}
