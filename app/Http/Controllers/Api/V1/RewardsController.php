<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Loyalty\Commands\AccountLoyaltyCommand;
use Onhost\Domain\Loyalty\Commands\LoyaltyCommand;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Settings\SettingsStore;

/** The loyalty programme: points, level, badges, referrals, missions and the streak; staff set the levels, award points and grant the streak discount. */
final class RewardsController extends ApiController
{
    public function show(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));

        return response()->json(['data' => $loyalty->summary($organization->id, (string) ($request->user()?->locale ?? 'cs'))]);
    }

    /** The invite programme (audit §5j-2): the code, the link, who came and what it earned; the code is allocated by the POST once. */
    public function referral(Request $request, ReferralService $referrals): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        if ($organization->referral_code === null) {
            return response()->json(['data' => ['code' => null, 'link' => null, 'reward' => $referrals->summary($organization)['reward'] ?? null, 'counts' => ['pending' => 0, 'rewarded' => 0, 'refused' => 0], 'referrals' => []]]);
        }

        return response()->json(['data' => $referrals->summary($organization)]);
    }

    public function referralCode(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new AccountLoyaltyCommand($organization->id, $this->idempotencyKey($request, 'loyalty.referral.code'), ['op' => 'referral.code']), $this->api->context($request, $organization), 201);
    }

    /** Monthly missions and the on-time streak (audit §5j-3). */
    public function missions(Request $request, MissionService $missions): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));

        return response()->json(['data' => $missions->summary($organization, null, (string) ($request->user()?->locale ?? 'cs'))]);
    }

    public function evaluateMissions(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new AccountLoyaltyCommand($organization->id, $this->idempotencyKey($request, 'loyalty.missions:'.now()->format('YmdHi')), ['op' => 'missions.evaluate', 'locale' => (string) ($request->user()?->locale ?? 'cs')]), $this->api->context($request, $organization));
    }

    /** Finance grants the streak discount (0 removes it). */
    public function approveStreak(Request $request, string $organization): JsonResponse
    {
        $data = $request->validate(['percent' => ['required', 'numeric', 'min:0', 'max:30'], 'note' => ['nullable', 'string', 'max:200'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new LoyaltyCommand($this->idempotencyKey($request, 'loyalty.streak:'.$organization.':'.now()->format('YmdHi')), ['op' => 'streak.approve', 'organization_id' => $organization, 'percent' => (float) $data['percent'], 'note' => $data['note'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    /** The missions catalogue (audit §5k-5): the table in force (seasonal rows included), the checks a mission may use, the defaults. */
    public function missionCatalogue(Request $request, MissionService $missions): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.manage', CommandScope::global());

        return response()->json(['data' => ['missions' => $missions->catalogue(null, true), 'checks' => MissionService::CHECKS, 'defaults' => $missions->defaults(), 'custom' => is_array(app(SettingsStore::class)->get(MissionService::CATALOGUE_SETTING))]]);
    }

    public function setMissionCatalogue(Request $request): JsonResponse
    {
        $data = $request->validate(['missions' => ['present', 'array', 'max:20'], 'missions.*.key' => ['required', 'string', 'max:30'], 'missions.*.cs' => ['nullable', 'string', 'max:80'], 'missions.*.en' => ['nullable', 'string', 'max:80'], 'missions.*.points' => ['nullable', 'integer', 'min:0', 'max:1000'], 'missions.*.hint_cs' => ['nullable', 'string', 'max:160'], 'missions.*.hint_en' => ['nullable', 'string', 'max:160'], 'missions.*.check' => ['nullable', 'string', 'max:30'], 'missions.*.params' => ['nullable', 'array'], 'missions.*.active_from' => ['nullable', 'date'], 'missions.*.active_to' => ['nullable', 'date'], 'missions.*.badge' => ['nullable', 'string', 'max:30'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new LoyaltyCommand($this->idempotencyKey($request, 'loyalty.missions:'.now()->format('YmdHi')), ['op' => 'missions', 'missions' => $data['missions']]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    /** Referrals for finance (audit §5l-4): held ones by default, any state on request; scores and signals included. */
    public function referrals(Request $request, ReferralService $referrals): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.manage', CommandScope::global());
        $state = (string) $request->query('state', 'held');
        $query = Referral::query()->orderByDesc('created_at');
        if ($state !== 'all') {
            $query->where('state', $state);
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn ($r) => $referrals->present($r))->values()->all(), 'weights' => $referrals->weights(), 'hold_score' => ReferralService::HOLD_SCORE, 'refuse_score' => ReferralService::REFUSE_SCORE]);
    }

    public function reviewReferral(Request $request, string $referral): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:release,reject'], 'note' => ['nullable', 'string', 'max:200']]);

        return $this->dispatch(new LoyaltyCommand($this->idempotencyKey($request, 'referral.review:'.$referral), ['op' => 'referral.review', 'referral_id' => $referral, 'decision' => $data['decision'], 'note' => $data['note'] ?? null]), $this->api->context($request, null, $data['note'] ?? null));
    }

    /** Campaigns (audit §5l-5): bundles of missions with a shared badge, a window and a start mail. */
    public function campaigns(Request $request, MissionService $missions): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.manage', CommandScope::global());

        return response()->json(['data' => ['campaigns' => $missions->campaigns(null, true), 'missions' => array_column($missions->catalogue(null, true), 'key')]]);
    }

    /** Prices a campaign before it opens (audit §5n-5): nothing is stored. */
    public function forecastCampaign(Request $request, MissionService $missions): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.manage', CommandScope::global());
        $data = $request->validate(['key' => ['nullable', 'string', 'max:40'], 'missions' => ['required', 'array', 'min:1', 'max:20'], 'missions.*' => ['string', 'max:40'], 'cs' => ['nullable', 'string', 'max:120'], 'en' => ['nullable', 'string', 'max:120'], 'badge' => ['nullable', 'string', 'max:40'], 'active_from' => ['nullable', 'date'], 'active_to' => ['nullable', 'date']]);

        return response()->json(['data' => $missions->campaignForecast($data)]);
    }

    public function campaignAnalytics(Request $request, MissionService $missions, string $campaign): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.manage', CommandScope::global());

        return response()->json(['data' => $missions->campaignAnalytics($campaign)]);
    }

    public function setCampaigns(Request $request): JsonResponse
    {
        $data = $request->validate(['campaigns' => ['present', 'array', 'max:10'], 'campaigns.*.key' => ['required', 'string', 'max:30'], 'campaigns.*.cs' => ['nullable', 'string', 'max:80'], 'campaigns.*.en' => ['nullable', 'string', 'max:80'], 'campaigns.*.missions' => ['required', 'array', 'min:1'], 'campaigns.*.missions.*' => ['string', 'max:30'], 'campaigns.*.badge' => ['nullable', 'string', 'max:30'], 'campaigns.*.active_from' => ['required', 'date'], 'campaigns.*.active_to' => ['nullable', 'date'], 'campaigns.*.mail' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new LoyaltyCommand($this->idempotencyKey($request, 'loyalty.campaigns:'.now()->format('YmdHi')), ['op' => 'campaigns', 'campaigns' => $data['campaigns']]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function levels(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.manage', CommandScope::global());

        return response()->json(['data' => ['levels' => $loyalty->levels(), 'rules' => (array) config('onhost.loyalty.points', [])]]);
    }

    public function setLevels(Request $request): JsonResponse
    {
        $data = $request->validate(['levels' => ['required', 'array', 'min:1', 'max:10'], 'levels.*.key' => ['required', 'string', 'max:20'], 'levels.*.name' => ['nullable', 'string', 'max:40'], 'levels.*.min' => ['required', 'integer', 'min:0'], 'levels.*.reward_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new LoyaltyCommand($this->idempotencyKey($request, 'loyalty.levels:'.now()->format('YmdHi')), ['op' => 'levels', 'levels' => $data['levels'], 'reason' => $data['reason'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function award(Request $request): JsonResponse
    {
        $data = $request->validate(['organization_id' => ['required', 'string', 'max:40'], 'points' => ['required', 'integer', 'min:1', 'max:10000'], 'note' => ['nullable', 'string', 'max:200'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new LoyaltyCommand($this->onceKey($request, 'loyalty.award:'.$data['organization_id']), ['op' => 'award'] + $data), $this->api->context($request, null, $data['reason'] ?? null), 201);
    }
}
