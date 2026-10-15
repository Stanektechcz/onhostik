<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Organizations\OnboardingChecklist;

/*
 * N13 (variant A): the onboarding checklist is derived (no table), scoped to the organization asked for, hidden through the
 * person's own preferences, and costs at most three queries.
 */

beforeEach(fn () => Http::preventStrayRequests());

function onboardingSteps(array $data): array
{
    return collect($data['steps'])->mapWithKeys(fn (array $s) => [$s['key'] => $s['done']])->all();
}

it('derives the checklist per organization without leaking another organization’s state', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$other, $otherOrg] = $this->customerWithOrganization();
    featureWebService($otherOrg, 'aapanel');
    Domain::query()->create(['organization_id' => $otherOrg->id, 'fqdn_ascii' => 'cizi-onb.cz', 'fqdn_unicode' => 'cizi-onb.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear()]);

    $this->actingAs($owner, 'sanctum');
    $data = $this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/account/onboarding')->assertOk()->json('data');
    expect(onboardingSteps($data))->toBe(['domain' => false, 'dns' => false, 'web' => false])
        ->and($data['done'])->toBe(0)->and($data['total'])->toBe(3)->and($data['hidden'])->toBeFalse();

    // the other organization cannot be read by naming it (a stranger hears what a missing identifier gets, F12a)
    $this->withHeaders(['X-Organization' => $otherOrg->id])->getJson('/v1/account/onboarding')->assertNotFound();

    $this->flushHeaders();
    $this->actingAs($other, 'sanctum');
    $theirs = $this->withHeaders(['X-Organization' => $otherOrg->id])->getJson('/v1/account/onboarding')->assertOk()->json('data');
    expect(onboardingSteps($theirs))->toBe(['domain' => true, 'dns' => false, 'web' => true])->and($theirs['done'])->toBe(2);
});

it('is hidden per organization through the person’s preferences and stays within three queries', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $owner->forceFill(['preferences' => [OnboardingChecklist::PREFERENCE => [$org->id]]])->save();
    expect(OnboardingChecklist::hiddenFor($owner->fresh(), $org->id))->toBeTrue()
        ->and(OnboardingChecklist::hiddenFor($owner->fresh(), 'org_other'))->toBeFalse();

    $person = $owner->fresh();
    $checklist = app(OnboardingChecklist::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $data = $checklist->build($org->id, $person, 'en');
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(3)->and($data['hidden'])->toBeTrue()->and($data['steps'][0]['label'])->toBe('Register or transfer a domain');
});
