<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * N13 (variant A): the new customer's onboarding checklist — domain, DNS, first web — derived from what the organization
 * already has; nothing is stored. Hiding it is the person's own preference (`preferences.onboarding_hidden`: organization
 * ids, written through the existing PATCH /v1/me), so it is reversible and needs no table. Two queries.
 */
final class OnboardingChecklist
{
    public const PREFERENCE = 'onboarding_hidden';

    private const GONE = [DomainStateMachine::FAILED, DomainStateMachine::TRANSFERRED_OUT, DomainStateMachine::EXPIRED, DomainStateMachine::REDEMPTION];

    /** @return array{steps: list<array{key:string,label:string,done:bool}>, done:int, total:int, complete:bool, hidden:bool} */
    public function build(string $organizationId, ?User $user, string $locale = 'cs'): array
    {
        $cs = $locale !== 'en';
        $domains = Domain::query()->where('organization_id', $organizationId)->whereNotIn('state', self::GONE)->limit(100)->get(['id', 'state', 'dns_provider', 'dns_zone_id']);
        $hasDomain = $domains->isNotEmpty();
        $hasDns = $domains->contains(fn (Domain $d) => $d->state === DomainStateMachine::ACTIVE && $d->usesOnhostDns());
        $hasWeb = Service::query()->where('organization_id', $organizationId)->where('family', 'web')
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->exists();

        $steps = [
            ['key' => 'domain', 'label' => $cs ? 'Zaregistrujte nebo převeďte doménu' : 'Register or transfer a domain', 'done' => $hasDomain],
            ['key' => 'dns', 'label' => $cs ? 'Nastavte DNS u ONhost' : 'Set up DNS with ONhost', 'done' => $hasDns],
            ['key' => 'web', 'label' => $cs ? 'Spusťte první web' : 'Launch your first website', 'done' => $hasWeb],
        ];
        $done = count(array_filter($steps, fn (array $s) => $s['done']));

        return ['steps' => $steps, 'done' => $done, 'total' => count($steps), 'complete' => $done === count($steps), 'hidden' => self::hiddenFor($user, $organizationId)];
    }

    public static function hiddenFor(?User $user, string $organizationId): bool
    {
        $hidden = data_get((array) ($user->preferences ?? []), self::PREFERENCE);

        return $hidden === true || (is_array($hidden) && in_array($organizationId, $hidden, true));
    }
}
