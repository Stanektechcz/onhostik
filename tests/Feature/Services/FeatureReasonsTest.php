<?php

declare(strict_types=1);

use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Commands\CommandContext;

/*
 * A button a person cannot press is not offered, and what is unavailable says why (Brain cards H412, H413; audit
 * §5ah). The feature map was built from the service alone — the same answer for its owner and for somebody who may
 * only look at it. The read-only collaborator saw every button and learnt the truth only as a 403 when they pressed
 * one; and a suspended service looked exactly like a panel that cannot do the thing at all.
 */

it('answers what this person can do with the service, not only what the service offers', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $features = app(ServiceFeatures::class);

    $mine = $features->features($site, $owner);
    expect($mine['backups']['enabled'])->toBeTrue()->and($mine['php']['enabled'])->toBeTrue()
        ->and($features->features($site)['php']['enabled'])->toBeTrue(); // without an actor nothing is filtered

    // somebody who may look at the organization and nothing more
    $guest = $this->customer(['email' => 'kouk@example.cz']);
    app(OrganizationService::class)->attachMember($org, $guest, 'viewer', CommandContext::system('test'), true);

    $theirs = $features->features($site, $guest->fresh());
    expect($theirs['php'])->toMatchArray(['enabled' => false, 'reason' => ServiceFeatures::REASON_PERMISSION])
        ->and($theirs['backups'])->toMatchArray(['enabled' => false, 'reason' => ServiceFeatures::REASON_PERMISSION])
        ->and($theirs['terminate'])->toMatchArray(['enabled' => false, 'reason' => ServiceFeatures::REASON_PERMISSION])
        ->and($features->actions($site, $guest->fresh()))->toBe([]); // nothing is offered that would come back 403
});

it('says the service is suspended instead of pretending the panel cannot do it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $site->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();

    $features = app(ServiceFeatures::class)->features($site->fresh(), $owner);
    // against the old code both of these were a bare `enabled: false` — indistinguishable from "this panel has no PHP switch"
    expect($features['php'])->toMatchArray(['enabled' => false, 'reason' => ServiceFeatures::REASON_STATE])
        ->and($features['backups']['reason'] ?? null)->toBe(ServiceFeatures::REASON_STATE)
        ->and($features['resume']['enabled'])->toBeTrue()   // what a suspended service CAN do stays
        ->and($features['suspend']['enabled'])->toBeFalse();
});

it('gives a collaborator exactly the features of the capabilities they were given', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $features = app(ServiceFeatures::class);
    $access = app(ServiceAccessService::class);

    // one person may run the service, another may only put a backup back — the panel shows each of them their own half
    $operator = $this->customer(['email' => 'spravce@example.cz']);
    $restorer = $this->customer(['email' => 'obnova@example.cz']);
    foreach ([$operator, $restorer] as $person) {
        app(OrganizationService::class)->attachMember($org, $person, 'viewer', CommandContext::system('test'), true);
    }
    $access->share($org, $site, $operator->email, ['manage'], $this->contextFor($owner, $org, 'totp'));
    $access->share($org, $site, $restorer->email, ['restore'], $this->contextFor($owner, $org, 'totp'));

    $theirs = $features->features($site, $operator->fresh());
    expect($theirs['php']['enabled'])->toBeTrue()->and($theirs['backups']['enabled'])->toBeTrue()
        ->and($theirs['restore']['reason'] ?? null)->toBe(ServiceFeatures::REASON_PERMISSION) // managing is not restoring
        ->and($theirs['terminate']['reason'] ?? null)->toBe(ServiceFeatures::REASON_PERMISSION); // and it is certainly not cancelling

    $others = $features->features($site, $restorer->fresh());
    expect($others['restore']['enabled'])->toBeTrue()->and($others['php']['reason'] ?? null)->toBe(ServiceFeatures::REASON_PERMISSION)
        ->and($features->actions($site, $restorer->fresh()))->toContain('restore')->not->toContain('php.set');
});
