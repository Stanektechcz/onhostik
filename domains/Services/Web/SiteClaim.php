<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Carbon\CarbonImmutable;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Whether anything ever proved that the name a site serves is really its customer's.
 *
 * Audit row 89 made a host name belong to one service, because on a shared node that is the only way a name can be
 * safe. It also handed anyone a weapon: nothing had to be true for a claim to stick. Order the cheapest hosting for
 * `firma.cz`, never point it anywhere, and the real owner of `firma.cz` can never be hosted here — the cart refuses
 * them and there the story ends.
 *
 * Nothing new is asked of an honest customer. The platform already knows three things that prove a name (the domain
 * is registered here, its DNS zone is here, or the name answers with the node that serves it) and the daily DNS
 * check works the third one out anyway, so this rides along with it and asks the resolver nothing extra. A claim
 * that can show none of them past the grace period is an **operator's** problem: a customer cannot undo somebody
 * else's squat, and the platform will not take a live site's name away on a guess. It only says so, once a day.
 */
final class SiteClaim
{
    /** How long a claim may go unproved before the operators hear about it. */
    public const GRACE_DAYS = 30;

    public function __construct(private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit) {}

    public static function graceDays(): int
    {
        return max(1, (int) config('onhost.web_tools.claim_grace_days', self::GRACE_DAYS));
    }

    /**
     * The service's tags with `name_claim` brought up to date, and the operators told when it is time.
     *
     * @param  array<string,mixed>  $tags  the tags the caller is about to save (so one write carries both)
     * @return array<string,mixed>
     */
    public function stamp(Service $service, array $tags): array
    {
        if (! in_array((string) $service->family, SiteNames::WEB, true)) {
            return $tags;
        }
        $name = mb_strtolower(rtrim(trim((string) ($service->hostname ?? '')), '.'));
        if ($name === '') {
            return $tags;
        }
        $before = (array) ($tags['name_claim'] ?? []);
        $proof = SiteNames::proof($service, $name);
        $since = $proof !== null ? null : (string) ($before['unproved_since'] ?? now()->toIso8601String());
        $tags['name_claim'] = [
            'proof' => $proof,
            'checked_at' => now()->toIso8601String(),
            'unproved_since' => $since,                                   // a proved claim forgets it ever was not
            'told_on' => $proof !== null ? null : ($before['told_on'] ?? null),
        ];
        if ($proof === null && CarbonImmutable::parse($since)->addDays(self::graceDays())->isPast()
            && (string) ($tags['name_claim']['told_on'] ?? '') !== now()->toDateString()) {
            $tags['name_claim']['told_on'] = now()->toDateString();
            $this->tell($service, $name, $since);
        }

        return $tags;
    }

    private function tell(Service $service, string $name, string $since): void
    {
        $days = (int) CarbonImmutable::parse($since)->diffInDays(now());
        $context = CommandContext::system('site.claim')->withScope((string) $service->organization_id);
        $detail = ['domain' => $name, 'days' => $days, 'organization_id' => $service->organization_id];
        $this->audit->record($context, 'service.name_unproved', 'succeeded', $detail, 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.name_unproved', 'service', $service->id, $detail + [
            'name' => $service->label ?: $service->name,
        ], (string) $service->organization_id));
    }
}
