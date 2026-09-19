<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Access that lives at the panels, not in our own roles (Brain cards H332, H333). Removing a person from an
 * organization takes their ONhost roles away at once — but a collaborator account on a game server is an account of
 * the game panel, keyed by e-mail, and would quietly outlive the membership. Two things happen here:
 *
 *  • `revokeForMember()` runs when a member is removed: every collaborator account with that e-mail on the
 *    organization's game servers is deleted through the ordinary audited `subuser.delete` operation;
 *  • `review()` runs on a schedule: a collaborator whose e-mail belongs to an ONhost account that is *not* a member
 *    of the organization is reported to the organization. It is never removed silently — an outside collaborator
 *    without an ONhost account is a legitimate thing to have, and only the customer knows which is which.
 *
 * FTP, shell and database accounts are named accounts of the site, not of a person, so there is nothing to match
 * them against; they are removed with the service (H346).
 */
final class DelegatedAccessReview
{
    public function __construct(
        private readonly ServiceFeatures $features,
        private readonly ServiceService $services,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * @param  ?list<string>  $serviceIds
     * @return array{services:int, revoked:list<array{service_id:string, email:string, operation_id:?string}>, deferred:list<array{service_id:string, reason:string}>}
     */
    public function revokeForMember(Organization $organization, string $email, CommandContext $context, ?array $serviceIds = null): array
    {
        $email = mb_strtolower(trim($email));
        $report = ['services' => 0, 'revoked' => [], 'deferred' => []];
        if ($email === '') {
            return $report;
        }
        foreach ($this->gameServices($organization->id) as $service) {
            if ($serviceIds !== null && ! in_array($service->id, $serviceIds, true)) {
                continue; // a project role ended: only that project's servers
            }
            $report['services']++;
            try {
                foreach ($this->subusers($service) as $subuser) {
                    if (mb_strtolower((string) $subuser['email']) !== $email) {
                        continue;
                    }
                    $operation = $this->services->requestAction($service, 'subuser.delete', $context->withScope($organization->id), 'member-removed:'.$service->id.':'.substr(hash('sha256', $email.'|'.$subuser['remote_id']), 0, 16), ['remote_id' => (string) $subuser['remote_id']]);
                    $report['revoked'][] = ['service_id' => $service->id, 'email' => $email, 'operation_id' => $operation->id];
                }
            } catch (Throwable $e) { // the panel is down or the server is busy with another operation: the weekly review names what is left
                $report['deferred'][] = ['service_id' => $service->id, 'reason' => mb_substr($e->getMessage(), 0, 160)];
            }
        }

        return $report;
    }

    /**
     * @return array{organizations:int, services:int, findings:int, errors:int}
     */
    public function review(?string $organizationId = null): array
    {
        $stats = ['organizations' => 0, 'services' => 0, 'findings' => 0, 'errors' => 0];
        $byOrganization = [];
        foreach ($this->gameServices($organizationId) as $service) {
            $stats['services']++;
            try {
                $subusers = $this->subusers($service);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            foreach ($subusers as $subuser) {
                $email = mb_strtolower((string) $subuser['email']);
                $user = $email === '' ? null : User::query()->where('email', $email)->first();
                if ($user === null) {
                    continue; // an outside collaborator: nothing of ours to compare with
                }
                if (OrganizationMembership::query()->where('organization_id', $service->organization_id)->where('user_id', $user->id)->exists()) {
                    continue;
                }
                $byOrganization[$service->organization_id][] = ['service_id' => $service->id, 'service' => $service->label ?: ($service->hostname ?: $service->name), 'email' => $email, 'remote_id' => (string) $subuser['remote_id']];
            }
        }
        foreach ($byOrganization as $id => $findings) {
            $stats['organizations']++;
            $stats['findings'] += count($findings);
            $this->outbox->publish(GenericEvent::of('access.review.findings', 'organization', (string) $id, [
                'count' => count($findings), 'findings' => array_slice($findings, 0, 20),
            ], (string) $id));
        }

        return $stats;
    }

    /** @return iterable<Service> */
    private function gameServices(?string $organizationId): iterable
    {
        return Service::query()->where('family', 'game')
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED])
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereNotNull('provider_instance_id')->whereHas('bindings')->cursor();
    }

    /** @return list<array{remote_id:string, email:string}> */
    private function subusers(Service $service): array
    {
        if (empty($this->features->features($service)['subusers']['enabled'])) {
            return [];
        }

        return $this->features->gameTools($this->features->adapterFor($service))->listSubusers($this->features->refFor($service));
    }
}
